<?php

namespace App\Http\Controllers;

use App\Models\FuelLog;
use App\Models\Invoice;
use App\Models\Patron;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use App\Models\Personnel;
use Illuminate\Http\Request;
use Inertia\Inertia;
use App\Http\Controllers\Concerns\AuthorizesModule;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class FuelLogController extends Controller
{
    use AuthorizesModule;
    protected string $module = 'fuel_log';

    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $this->authorizeModule('menu');

        $activePlantId = session('active_plant_id');

        $query = FuelLog::with(['machine', 'driver', 'vendor:id,legal_name', 'invoice:id,prefix,invoice_number,invoice_date,account_id', 'invoice.account:id,title',
            'invoice.postedBillJournals' => fn ($query) => $query->where('plant_id', $activePlantId)->select('id', 'ref_id'),
            'invoice.postedBillJournals.lines' => fn ($query) => $query->where('plant_id', $activePlantId)
                ->select('id', 'journal_entry_id', 'account_id', 'debit_amount', 'credit_amount')->orderBy('id'),
            'invoice.postedBillJournals.lines.ledger:id,title',
        ])
            ->where('plant_id', $activePlantId);

        // Search/Filters
        if ($request->filled('search')) {
            $search = $request->input('search');
            $query->where(function($q) use ($search) {
                $q->where('bill_no', 'like', "%{$search}%")
                  ->orWhere('pump_name', 'like', "%{$search}%")
                  ->orWhere('payment_method', 'like', "%{$search}%")
                  ->orWhereHas('machine', function($mq) use ($search) {
                      $mq->where('registration', 'like', "%{$search}%");
                  })
                  ->orWhereHas('driver', function($dq) use ($search) {
                      $dq->where('first_name', 'like', "%{$search}%")
                        ->orWhere('last_name', 'like', "%{$search}%");
                  });
            });
        }

        // Filter by Machine
        if ($request->filled('machine_id')) {
            $query->where('machine_id', $request->input('machine_id'));
        }

        // Filter by Date Range
        if ($request->filled('start_date') && $request->filled('end_date')) {
            $query->whereBetween('log_date', [
                $request->input('start_date') . ' 00:00:00',
                $request->input('end_date') . ' 23:59:59'
            ]);
        }

        $fuelLogs = $query->latest('log_date')->get();
        foreach ($fuelLogs as $log) {
            $log->invoice?->setAppends(['full_number', 'encrypted_id']);
        }

        // Optimized dropdown data
        $machines = MachinesDropdown(); // Helper from Dropdown.php
        
        $drivers = Personnel::query()
            ->where('plant_id', $activePlantId)
            ->where('status', 'active')
            ->whereRelation('designation', 'name', 'Driver')
            ->orderBy('id')
            ->get()
            ->map(fn ($p) => [
                'id'    => $p->id,
                'label' => trim($p->first_name . ' ' . $p->last_name),
                'value' => $p->id,
            ]);

        // Standard payment options
        $paymentMethods = PaymentMethodsDropdown();
// return response()->json([
//     'drivers' => $drivers,
//     'paymentMethods' => $paymentMethods
// ]);
        $canGenerateBill = $this->canBill('create');

        return Inertia::render('FuelLogs/Index', [
            'fuelLogs' => $fuelLogs,
            'canViewReport' => app(\App\Services\Reports\ReportPermissions::class)->allows('fuel_log'),
            'machines' => $machines,
            'vendors' => toSelectOptions(VendorsDropdown('Vendor'), 'legal_name'),
            'canGenerateBill' => $canGenerateBill,
            'canDeleteBill' => $this->canBill('delete'),
            'accounts' => $canGenerateBill ? toSelectOptions(LedgersDropdown('EXPENSE'), 'title') : [],
            'drivers' => $drivers,
            'paymentMethods' => $paymentMethods,
            'filters' => $request->only(['search', 'machine_id', 'start_date', 'end_date'])
        ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $this->authorizeModule('create');

        $validated = $request->validate([
            'machine_id' => 'required|exists:mm_machines,id',
            'vendor_id' => ['required', 'integer', Rule::exists('mm_patrons', 'id')
                ->where('plant_id', session('active_plant_id'))->whereNull('deleted_at')
                ->where(fn ($query) => $query->whereJsonContains('patron_type', 'Vendor'))],
            'driver_id' => 'nullable|exists:mm_personnels,id',
            'log_date' => 'required|date',
            'quantity' => 'required|numeric|min:0.01',
            'rate_per_liter' => 'required|numeric|min:0.01',
            'odometer_reading' => 'required|numeric|min:0',
            'hourmeter_reading' => 'nullable|numeric|min:0',
            'pump_name' => 'nullable|string|max:250',
            'bill_no' => 'nullable|string|max:150',
            'payment_method' => 'nullable|string|max:100',
            'attachment' => 'nullable|file|image|max:10240',
            'notes' => 'nullable|string',
        ]);

        DB::beginTransaction();
        try {
            $activePlantId = session('active_plant_id');
            $activeEntityId = session('active_entity_id');
            $userId = auth()->id();

            // Handle attachment upload
            $attachmentPath = null;
            if ($request->hasFile('attachment')) {
                $file = $request->file('attachment');
                $targetDir = "fuel_receipts/plant_{$activePlantId}";
                $attachmentPath = $file->store($targetDir, 'public');
            }

            // Odometer validation: warning if odometer reading is less than previous for the vehicle
            $latestLog = FuelLog::where('machine_id', $validated['machine_id'])
                ->where('log_date', '<', $validated['log_date'])
                ->orderBy('log_date', 'desc')
                ->first();

            if ($latestLog && $validated['odometer_reading'] < $latestLog->odometer_reading) {
                DB::rollBack();
                return redirect()->back()->withErrors([
                    'odometer_reading' => "Odometer reading cannot be less than previous reading ({$latestLog->odometer_reading} km) recorded on " . $latestLog->log_date->format('Y-m-d H:i')
                ])->withInput();
            }

            $totalAmount = $validated['quantity'] * $validated['rate_per_liter'];

            FuelLog::create([
                'entity_id' => $activeEntityId,
                'plant_id' => $activePlantId,
                'machine_id' => $validated['machine_id'],
                'vendor_id' => $validated['vendor_id'],
                'driver_id' => $validated['driver_id'] ?? null,
                'log_date' => date('Y-m-d H:i:s', strtotime($validated['log_date'])),
                'quantity' => $validated['quantity'],
                'rate_per_liter' => $validated['rate_per_liter'],
                'total_amount' => $totalAmount,
                'odometer_reading' => $validated['odometer_reading'],
                'hourmeter_reading' => $validated['hourmeter_reading'] ?? null,
                'pump_name' => $validated['pump_name'],
                'bill_no' => $validated['bill_no'],
                'payment_method' => $validated['payment_method'],
                'attachment_path' => $attachmentPath,
                'notes' => $validated['notes'],
                'created_by' => $userId
            ]);

            DB::commit();
            return redirect()->back()->with('success', 'Fuel refuel transaction logged successfully.');
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Fuel log store error: ' . $e->getMessage());
            return redirect()->back()->withErrors(['error' => 'Failed to log refueling transaction: ' . $e->getMessage()]);
        }
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, FuelLog $fuelLog)
    {
        $this->authorizeModule('edit');

        $generateBill = $request->boolean('generate_bill');
        if ($generateBill) {
            $this->authorizeModule('create', 'billing');
        }
        $validated = $request->validate([
            'generate_bill' => ['sometimes', 'boolean'],
            'bill_date' => [Rule::requiredIf($generateBill), 'nullable', 'date_format:Y-m-d'],
            'account_id' => [Rule::requiredIf($generateBill), 'nullable', 'integer', Rule::in(LedgersDropdown('EXPENSE')->pluck('id'))],
            'machine_id' => 'required|exists:mm_machines,id',
            'vendor_id' => ['required', 'integer', Rule::exists('mm_patrons', 'id')
                ->where('plant_id', session('active_plant_id'))->whereNull('deleted_at')
                ->where(fn ($query) => $query->whereJsonContains('patron_type', 'Vendor'))],
            'driver_id' => 'nullable|exists:mm_personnels,id',
            'log_date' => 'required|date',
            'quantity' => 'required|numeric|min:0.01',
            'rate_per_liter' => 'required|numeric|min:0.01',
            'odometer_reading' => 'required|numeric|min:0',
            'hourmeter_reading' => 'nullable|numeric|min:0',
            'pump_name' => 'nullable|string|max:250',
            'bill_no' => 'nullable|string|max:150',
            'payment_method' => 'nullable|string|max:100',
            'attachment' => 'nullable|file|image|max:10240',
            'delete_attachment' => 'nullable|boolean',
            'notes' => 'nullable|string',
        ]);

        $uploadedPath = null;
        DB::beginTransaction();
        try {
            $userId = auth()->id();

            $fuelLog = $this->lockedLog($fuelLog);
            if ($fuelLog->invoice()->exists()) {
                DB::rollBack();
                return back()->withErrors(['error' => 'This fuel entry already has a bill.']);
            }

            // Odometer validation: warning if odometer reading is less than previous for the vehicle
            $latestLog = FuelLog::where('machine_id', $validated['machine_id'])
                ->where('id', '!=', $fuelLog->id)
                ->where('log_date', '<', $validated['log_date'])
                ->orderBy('log_date', 'desc')
                ->first();

            if ($latestLog && $validated['odometer_reading'] < $latestLog->odometer_reading) {
                DB::rollBack();
                return redirect()->back()->withErrors([
                    'odometer_reading' => "Odometer reading cannot be less than previous reading ({$latestLog->odometer_reading} km) recorded on " . $latestLog->log_date->format('Y-m-d H:i')
                ])->withInput();
            }

            $totalAmount = $validated['quantity'] * $validated['rate_per_liter'];

            $fuelLog->update([
                ...\Illuminate\Support\Arr::only($validated, ['bill_date', 'account_id']),
                'machine_id' => $validated['machine_id'],
                'vendor_id' => $validated['vendor_id'],
                'driver_id' => $validated['driver_id'] ?? null,
                'log_date' => date('Y-m-d H:i:s', strtotime($validated['log_date'])),
                'quantity' => $validated['quantity'],
                'rate_per_liter' => $validated['rate_per_liter'],
                'total_amount' => $totalAmount,
                'odometer_reading' => $validated['odometer_reading'],
                'hourmeter_reading' => $validated['hourmeter_reading'] ?? null,
                'pump_name' => $validated['pump_name'],
                'bill_no' => $validated['bill_no'],
                'payment_method' => $validated['payment_method'],
                'notes' => $validated['notes'],
                'updated_by' => $userId
            ]);

            $invoice = $generateBill ? $this->createFuelBill($fuelLog, $fuelLog->account_id, $fuelLog->bill_date) : null;
            if ($request->hasFile('attachment') || $request->boolean('delete_attachment')) {
                $originalPath = $fuelLog->attachment_path;
                $uploadedPath = $request->file('attachment')?->store("fuel_receipts/plant_{$fuelLog->plant_id}", 'public');
                $fuelLog->update(['attachment_path' => $uploadedPath]);
                if ($originalPath) {
                    DB::afterCommit(fn () => Storage::disk('public')->delete($originalPath));
                }
            }
            DB::commit();
            return back()->with('success', $invoice ? "Fuel bill {$invoice->full_number} generated." : 'Fuel transaction updated successfully.');
        } catch (\Exception $e) {
            DB::rollBack();
            if ($uploadedPath) Storage::disk('public')->delete($uploadedPath);
            if ($e instanceof ValidationException) throw $e;
            Log::error('Fuel log update error: ' . $e->getMessage());
            return redirect()->back()->withErrors(['error' => 'Failed to update refueling transaction: ' . $e->getMessage()]);
        }
    }

    private function canBill(string $action): bool
    {
        try {
            $this->authorizeModule($action, 'billing');
            return true;
        } catch (\Symfony\Component\HttpKernel\Exception\HttpException $e) {
            if ($e->getStatusCode() !== 403) throw $e;
            return false;
        }
    }

    private function lockedLog(FuelLog $log): FuelLog
    {
        return FuelLog::where('plant_id', session('active_plant_id'))->whereKey($log->id)->lockForUpdate()->firstOrFail();
    }

    public function generateBill(Request $request, FuelLog $fuelLog)
    {
        $this->authorizeModule('menu');
        $this->authorizeModule('create', 'billing');
        $invoice = DB::transaction(function () use ($fuelLog, $request) {
            $log = $this->lockedLog($fuelLog);
            if ($log->invoice) return $log->invoice;

            $request->mergeIfMissing([
                'account_id' => $log->account_id,
                'bill_date' => $log->bill_date ?? $log->log_date->toDateString(),
            ]);
            $data = $request->validate([
                'account_id' => ['required', 'integer', Rule::in(LedgersDropdown('EXPENSE')->pluck('id'))],
                'bill_date' => ['required', 'date_format:Y-m-d'],
            ]);
            return $this->createFuelBill($log, $data['account_id'], $data['bill_date']);
        });

        return back()->with('success', "Fuel bill {$invoice->full_number} is available in Billing.");
    }

    private function createFuelBill(FuelLog $log, int $accountId, string $billDate): Invoice
    {
        $vendor = Patron::where('plant_id', $log->plant_id)->ofType('Vendor')->find($log->vendor_id);
        if (!$vendor) {
            throw ValidationException::withMessages(['vendor_id' => 'Save a vendor on the fuel entry before generating its bill.']);
        }

        $invoice = Invoice::createWithItems([
            'plant_id' => $log->plant_id,
            'partner_id' => $vendor->id,
            'account_id' => $accountId,
            'round_off' => round(round((float) $log->total_amount) - (float) $log->total_amount, 2),
            'invoice_type' => 'Bill',
            'invoice_label' => 'Manual',
            'ref_title' => "Fuel entry #{$log->id}",
            'notes' => $log->bill_no ? "Fuel receipt: {$log->bill_no}" : null,
            'invoice_date' => $billDate,
            'due_date' => \Illuminate\Support\Carbon::parse($billDate)->addDays((int) $vendor->due_date_days)->toDateString(),
            'status' => Invoice::STATUS_APPROVED,
            'created_by' => auth()->id(),
            'items' => [[
                'item_name' => "Fuel (litres) - entry #{$log->id}",
                'quantity' => $log->quantity,
                'price_unit' => $log->rate_per_liter,
            ]],
        ]);
        $invoice->postToAccounting();
        $log->update(['invoice_id' => $invoice->id, 'bill_date' => $billDate, 'account_id' => $accountId]);

        return $invoice;
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(FuelLog $fuelLog)
    {
        $this->authorizeModule('delete');

        try {
            $userId = auth()->id();

            DB::transaction(function () use ($fuelLog, $userId) {
                $fuelLog = $this->lockedLog($fuelLog);
                if ($fuelLog->invoice()->exists()) {
                    throw ValidationException::withMessages(['error' => 'This fuel entry already has a bill.']);
                }
                $fuelLog->update(['deleted_by' => $userId]);
                $fuelLog->delete();
            });

            return redirect()->back()->with('success', 'Refuel transaction record deleted successfully.');
        } catch (\Exception $e) {
            if ($e instanceof ValidationException) throw $e;
            Log::error('Fuel log delete error: ' . $e->getMessage());
            return redirect()->back()->withErrors(['error' => 'Failed to delete refuel transaction record.']);
        }
    }
}