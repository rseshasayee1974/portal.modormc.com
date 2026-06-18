<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\AuthorizesModule;
use App\Http\Requests\DispatchStoreRequest;
use App\Models\Dispatch;
use App\Models\Batch;
use App\Models\DispatchPayment;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Log;

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
                
                // Merge weights fields
                if (!empty($validated['weights'])) {
                    $dispatchData['empty_weight_truck'] = $validated['weights']['empty_weight_truck'] ?? null;
                    $dispatchData['loaded_weight_truck'] = $validated['weights']['loaded_weight_truck'] ?? null;
                    $dispatchData['empty_time'] = $validated['weights']['empty_weight_time_load'] ?? null;
                    $dispatchData['load_time'] = $validated['weights']['loaded_weight_time_load'] ?? null;
                    $dispatchData['net_weight'] = (float)($dispatchData['loaded_weight_truck'] ?? 0) - (float)($dispatchData['empty_weight_truck'] ?? 0);
                }

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
Log::info($dispatch);
                // 4. Create/Update Status / Logistical Record (mm_dispatch_statuses)
                $statusData = $validated['status'] ?? [];
                $statusData['plant_id'] = $dispatch->plant_id;
                $dispatch->status()->updateOrCreate(['dispatch_id' => $dispatch->id], $statusData);

                // 5. Process Immediate Payment if provided
                if ($dispatch->payment_mode === 'cash' && !empty($validated['payment']) && (float)($validated['payment']['amount'] ?? 0) > 0) {
                    $paymentData = $validated['payment'];
                    $paymentData['dispatch_id'] = $dispatch->id;
                    $paymentData['payment_type'] = 'partial';
                    $paymentData['is_active'] = true;
                    DispatchPayment::create($paymentData);
                }

               

          

                // Send Notification
                $dispatch->load(['customer.contacts']);
                $primaryContact = $dispatch->customer->contacts()->where('is_primary', 1)->first() 
                                ?? $dispatch->customer->contacts()->first();
                
                if ($primaryContact?->email) {
                    \Illuminate\Support\Facades\Notification::route('mail', $primaryContact->email)
                        ->notify(new \App\Notifications\DispatchCompletedNotification($dispatch));
                }

                return redirect()->back()->with('success', 'Dispatch processed successfully.');
      
        });
    }

    public function update(DispatchStoreRequest $request, Dispatch $dispatch)
    {
        $this->authorizeModule('edit');
        // dd( $dispatch->all());
        $user = auth()->user();
        if ($user && $user->hasRole('Trip Operator')) {
            $isDataPresented = (float)$dispatch->load_rate > 0 || 
                               $dispatch->dispatch_status !== 'Draft' || 
                               ($dispatch->status && $dispatch->status->invoice_status == 1) ||
                               $dispatch->payments()->exists();
                               
            if ($isDataPresented) {
                abort(403, 'Access Denied: You do not have permission to edit this trip as the data is already presented.');
            }
        }

        $validated = $request->validated();
        
        return DB::transaction(function () use ($validated, $dispatch) {
            // 1. Prepare Dispatch Data
            $dispatchData = collect($validated)->except(['weights', 'financials', 'status', 'payment', 'batch_size'])->toArray();

            // Merge weights fields
            if (!empty($validated['weights'])) {
                $dispatchData['empty_weight_truck'] = $validated['weights']['empty_weight_truck'] ?? null;
                $dispatchData['loaded_weight_truck'] = $validated['weights']['loaded_weight_truck'] ?? null;
                $dispatchData['empty_time'] = $validated['weights']['empty_weight_time_load'] ?? null;
                $dispatchData['load_time'] = $validated['weights']['loaded_weight_time_load'] ?? null;
                $dispatchData['net_weight'] = (float)($dispatchData['loaded_weight_truck'] ?? 0) - (float)($dispatchData['empty_weight_truck'] ?? 0);
            }

            $dispatchData = array_merge($dispatchData, $this->mapNestedFields($validated['financials'] ?? null, [
                'load_rate', 'load_tax_id', 'load_tax_amount', 'load_untax_amount', 'load_total_amount',
                'pass_amount', 'discount_amount', 'transport_expenses', 'adjustment_amount', 'round_off'
            ]));

            // 2. Update Main Dispatch Record
            $dispatch->update($dispatchData);
            Log::info("Dispatch updated successfully: ".$dispatch);

            // 4. Update Status Record
            if (!empty($validated['status'])) {
                $statusData = $validated['status'];
                $statusData['plant_id'] = $dispatch->plant_id;
                $dispatch->status()->updateOrCreate(['dispatch_id' => $dispatch->id], $statusData);
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
            $invoice = \App\Models\Invoice::createFromSource($dispatch, 'sales', [
                'account_id' => $validated['ledger_id'],
                'invoice_date' => $validated['invoice_date'],
                'partner_id' => $dispatch->customer_id,
                'plant_id' => $dispatch->plant_id,
                'invoice_label' => 'Dispatch'
            ]);
            $dispatch->invoice($invoice);

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
 
                $dispatch->resetInvoice();
            }

            return redirect()->back()->with('success', 'Invoice deleted and dispatch billing reset.');
        });
    }

    public function whatsappUrl(Dispatch $dispatch)
    {
        $this->authorizeModule('view');
        
        $url = $dispatch->getWhatsAppUrl();
        if (!$url) {
            return response()->json(['error' => 'Primary contact mobile number not found for customer.'], 422);
        }
        
        return response()->json(['url' => $url]);
    }
}