<?php

namespace App\Http\Controllers;

use App\Models\FuelLog;
use App\Models\Machine;
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

        $query = FuelLog::with(['machine', 'driver'])
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
        return Inertia::render('FuelLogs/Index', [
            'fuelLogs' => $fuelLogs,
            'machines' => $machines,
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
                return redirect()->back()->withErrors([
                    'odometer_reading' => "Odometer reading cannot be less than previous reading ({$latestLog->odometer_reading} km) recorded on " . $latestLog->log_date->format('Y-m-d H:i')
                ])->withInput();
            }

            $totalAmount = $validated['quantity'] * $validated['rate_per_liter'];

            FuelLog::create([
                'entity_id' => $activeEntityId,
                'plant_id' => $activePlantId,
                'machine_id' => $validated['machine_id'],
                'driver_id' => $validated['driver_id'],
                'log_date' => date('Y-m-d H:i:s', strtotime($validated['log_date'])),
                'quantity' => $validated['quantity'],
                'rate_per_liter' => $validated['rate_per_liter'],
                'total_amount' => $totalAmount,
                'odometer_reading' => $validated['odometer_reading'],
                'hourmeter_reading' => $validated['hourmeter_reading'],
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

        $validated = $request->validate([
            'machine_id' => 'required|exists:mm_machines,id',
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

        DB::beginTransaction();
        try {
            $userId = auth()->id();

            // Handle attachment upload and deletion
            $attachmentPath = $fuelLog->attachment_path;
            if ($request->input('delete_attachment') == true) {
                if ($fuelLog->attachment_path) {
                    Storage::disk('public')->delete($fuelLog->attachment_path);
                }
                $attachmentPath = null;
            }

            if ($request->hasFile('attachment')) {
                // Delete old one if exists
                if ($fuelLog->attachment_path) {
                    Storage::disk('public')->delete($fuelLog->attachment_path);
                }
                $file = $request->file('attachment');
                $targetDir = "fuel_receipts/plant_{$fuelLog->plant_id}";
                $attachmentPath = $file->store($targetDir, 'public');
            }

            // Odometer validation: warning if odometer reading is less than previous for the vehicle
            $latestLog = FuelLog::where('machine_id', $validated['machine_id'])
                ->where('id', '!=', $fuelLog->id)
                ->where('log_date', '<', $validated['log_date'])
                ->orderBy('log_date', 'desc')
                ->first();

            if ($latestLog && $validated['odometer_reading'] < $latestLog->odometer_reading) {
                return redirect()->back()->withErrors([
                    'odometer_reading' => "Odometer reading cannot be less than previous reading ({$latestLog->odometer_reading} km) recorded on " . $latestLog->log_date->format('Y-m-d H:i')
                ])->withInput();
            }

            $totalAmount = $validated['quantity'] * $validated['rate_per_liter'];

            $fuelLog->update([
                'machine_id' => $validated['machine_id'],
                'driver_id' => $validated['driver_id'],
                'log_date' => date('Y-m-d H:i:s', strtotime($validated['log_date'])),
                'quantity' => $validated['quantity'],
                'rate_per_liter' => $validated['rate_per_liter'],
                'total_amount' => $totalAmount,
                'odometer_reading' => $validated['odometer_reading'],
                'hourmeter_reading' => $validated['hourmeter_reading'],
                'pump_name' => $validated['pump_name'],
                'bill_no' => $validated['bill_no'],
                'payment_method' => $validated['payment_method'],
                'attachment_path' => $attachmentPath,
                'notes' => $validated['notes'],
                'updated_by' => $userId
            ]);

            DB::commit();
            return redirect()->back()->with('success', 'Fuel transaction updated successfully.');
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Fuel log update error: ' . $e->getMessage());
            return redirect()->back()->withErrors(['error' => 'Failed to update refueling transaction: ' . $e->getMessage()]);
        }
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(FuelLog $fuelLog)
    {
        $this->authorizeModule('delete');

        try {
            $userId = auth()->id();

            // Soft delete
            $fuelLog->update(['deleted_by' => $userId]);
            $fuelLog->delete();

            return redirect()->back()->with('success', 'Refuel transaction record deleted successfully.');
        } catch (\Exception $e) {
            Log::error('Fuel log delete error: ' . $e->getMessage());
            return redirect()->back()->withErrors(['error' => 'Failed to delete refuel transaction record.']);
        }
    }
}