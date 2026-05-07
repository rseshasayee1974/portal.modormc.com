<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\AuthorizesModule;
use App\Http\Requests\DispatchStoreRequest;
use App\Models\Dispatch;
use App\Models\DispatchPayment;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class DispatchController extends Controller
{
    use AuthorizesModule;

    protected string $module = 'work_orders';

    private function mapNestedFields(?array $source, array $fields): array
    {
        if (!$source) return [];
        return collect($source)->only($fields)->toArray();
    }

    protected function getNextDispatchDetails($plantId)
    {
        $currentDate = now();
        $startYear = $currentDate->month >= 4 ? $currentDate->year : $currentDate->year - 1;
        $endYear = $startYear + 1;
        $fyString = substr($startYear, -2) . substr($endYear, -2);
        $prefix = "DP-{$fyString}-";
        
        $maxNumber = Dispatch::where('plant_id', $plantId)
            ->where('prefix', $prefix)
            ->max(DB::raw('CAST(dispatch_no AS UNSIGNED)'));
        
        $newNumber = ($maxNumber ?: 0) + 1;
        
        return [
            'prefix' => $prefix,
            'nextNumber' => (string)$newNumber,
        ];
    }
 
    public function dropdowns()
    {
        $plantId = session('active_plant_id');
        $details = $this->getNextDispatchDetails($plantId);
        
        return response()->json([
            'prefix' => $details['prefix'],
            'nextDispatchNo' => $details['nextNumber'],
        ]);
    }
    public function store(DispatchStoreRequest $request)
    {
        $this->authorizeModule('create');
        $validated = $request->validated();

         DB::transaction(function () use ($validated) {
            // try {
                // 1. Prepare Dispatch Data (Flattened financials are now at top level)
                $dispatchData = collect($validated)->except(['weights', 'financials', 'status', 'payment', 'batch_size'])->toArray();
                
                // Merge valid financial fields
                $dispatchData = array_merge($dispatchData, $this->mapNestedFields($validated['financials'] ?? null, [
                    'load_rate', 'load_tax_id', 'load_tax_amount', 'load_untax_amount', 'load_total_amount',
                    'pass_amount', 'discount_amount', 'transport_expenses', 'adjustment_amount', 'round_off'
                ]));

                $dispatchData['plant_id'] = session('active_plant_id');

                if (empty($dispatchData['dispatch_no'])) {
                    $details = $this->getNextDispatchDetails($dispatchData['plant_id']);
                    $dispatchData['prefix'] = $details['prefix'];
                    $dispatchData['dispatch_no'] = $details['nextNumber'];
                }

                // 2. Create Main Dispatch Record
                $dispatch = Dispatch::create($dispatchData);

                // 4. Create Status / Logistical Record (mm_dispatch_statuses)
                $statusData = $validated['status'] ?? [];
                $dispatch->status()->create($statusData);

                // 5. Process Immediate Payment if provided
                if ($dispatch->payment_mode === 'cash' && !empty($validated['payment']) && (float)($validated['payment']['amount'] ?? 0) > 0) {
                    $paymentData = $validated['payment'];
                    $paymentData['dispatch_id'] = $dispatch->id;
                    $paymentData['payment_type'] = 'partial';
                    $paymentData['is_active'] = true;
                    DispatchPayment::create($paymentData);
                }

               

          

                return redirect()->back()->with('success', 'Dispatch processed successfully.');
      
        });
    }

    public function update(DispatchStoreRequest $request, Dispatch $dispatch)
    {
        $this->authorizeModule('edit');
        $validated = $request->validated();
        return DB::transaction(function () use ($validated, $dispatch) {
            // 1. Prepare Dispatch Data
            $dispatchData = collect($validated)->except(['weights', 'financials', 'status', 'payment', 'batch_size'])->toArray();

            $dispatchData = array_merge($dispatchData, $this->mapNestedFields($validated['financials'] ?? null, [
                'load_rate', 'load_tax_id', 'load_tax_amount', 'load_untax_amount', 'load_total_amount',
                'pass_amount', 'discount_amount', 'transport_expenses', 'adjustment_amount', 'round_off'
            ]));

            // 2. Update Main Dispatch Record
            $dispatch->update($dispatchData);

            // 4. Update Status Record
            if (!empty($validated['status'])) {
                $dispatch->status()->updateOrCreate(['dispatch_id' => $dispatch->id], $validated['status']);
            }

            // 5. Update Payment
            if ($dispatch->payment_mode === 'cash' && !empty($validated['payment']) && (float)($validated['payment']['amount'] ?? 0) > 0) {
                 $paymentData = $validated['payment'];
                 $paymentData['dispatch_id'] = $dispatch->id;
                 $paymentData['payment_type'] = 'partial';
                 $paymentData['is_active'] = true;
                 
                 // For now, updateOrCreate first payment (simpler logic for initial dev)
                 $dispatch->payments()->updateOrCreate(
                    ['dispatch_id' => $dispatch->id],
                    $paymentData
                 );
            } elseif ($dispatch->payment_mode === 'credit') {
                // Remove any payments if switched to credit
                $dispatch->payments()->delete();
            }



            return redirect()->back()->with('success', 'Dispatch updated successfully.');
        });
    }

    public function destroy(Dispatch $dispatch)
    {
        $this->authorizeModule('delete');
        $dispatch->delete();
        return redirect()->back()->with('success', 'Dispatch deleted successfully.');
    }

    public function generateInvoice(\Illuminate\Http\Request $request, Dispatch $dispatch)
    {
        $validated = $request->validate([
            'ledger_id' => 'required|exists:mm_ledgers,id',
            'invoice_date' => 'required|date',
        ]);

        return DB::transaction(function () use ($dispatch, $validated) {
            $invoice = \App\Http\Controllers\InvoiceController::createFromSource($dispatch, 'sales', [
                'account_id' => $validated['ledger_id'],
                'invoice_date' => $validated['invoice_date'],
                'partner_id' => $dispatch->customer_id,
                'plant_id' => $dispatch->plant_id,
                'invoice_label' => 'Batching'
            ]);

            // Update dispatch status with generated invoice info
            $dispatch->status()->updateOrCreate(
                ['dispatch_id' => $dispatch->id],
                [
                    'invoice_id'     => $invoice->id,
                    'invoice_number' => $invoice->invoice_number,
                    'invoice_date'   => $invoice->invoice_date,
                    'invoice_status' => 1,
                ]
            );

            return redirect()->back()->with('success', 'Invoice generated successfully: ' . $invoice->invoice_number);
        });
    }
    public function deleteInvoice(Dispatch $dispatch)
    {
        $this->authorizeModule('edit');

        return DB::transaction(function () use ($dispatch) {
            $status = $dispatch->status;
          
            if ($status && $status->invoice_id) {
                $invoice = \App\Models\Invoice::query()->find($status->invoice_id);
                // dd($invoice );
                if ($invoice) {
                    $invoice->is_active = 0;
                    $invoice->save();
                    $invoice->orderTaxes()->update(['status' => 0]);
                    
                    // Cascading soft deletes for invoice items and taxes
                    $invoice->items()->delete();
                    $invoice->orderTaxes()->delete();

                    // Explicitly update and soft delete associated journal entries and their lines
                    \App\Models\JournalEntry::query()->where('ref_module', 'invoice')
                        ->where('ref_id', $invoice->id)
                        ->get()
                        ->each(function ($entry) {
                            // Update lines via query builder to bypass fillable and fire no events
                            \App\Models\JournalEntryLine::query()->where('journal_entry_id', $entry->id)->update([
                                'is_deleted' => 1,
                                'deleted_by' => auth()->id(),
                                'deleted_at' => now(),
                            ]);

                            // Update entry and soft delete
                            \App\Models\JournalEntry::query()->where('id', $entry->id)->update([
                                'is_deleted' => 1,
                                'deleted_by' => auth()->id(),
                                'deleted_at' => now(),
                            ]);
                        });
                    
                    $invoice->delete(); 
                }

                $status->update([
                    'invoice_id'     => null,
                    'invoice_number' => null,
                    'invoice_date'   => null,
                    'invoice_status' => 0,
                ]);
            }

            return redirect()->back()->with('success', 'Invoice deleted and dispatch billing reset.');
        });
    }
}
