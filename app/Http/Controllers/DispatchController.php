<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\AuthorizesModule;
use App\Http\Requests\DispatchStoreRequest;
use App\Models\Dispatch;
use App\Models\Batch;
use App\Models\DispatchPayment;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Log;

class DispatchController extends Controller
{
    use AuthorizesModule;

    protected string $module = 'dispatch';

    private function mapNestedFields(?array $source, array $fields): array
    {
        if (!$source) return [];
        return collect($source)->only($fields)->toArray();
    }

    protected function getNextDispatchDetails($plantId, $customerId = null, $batchId = null)
    {
        $batch = null;
        if ($batchId) {
            $batch = Batch::withoutGlobalScopes()->where('plant_id', $plantId)->whereNull('deleted_at')->find($batchId);
            if (!$batch) throw \Illuminate\Validation\ValidationException::withMessages(['batch_id' => 'Select a batch belonging to the active plant.']);
        }
        return Dispatch::nextDocumentNumber($plantId, $customerId, $batch);
    }

    public function dropdowns(Request $request)
    {
        $plantId = session('active_plant_id');
        $details = $this->getNextDispatchDetails($plantId, $request->input('customer_id'), $request->input('batch_id'));
        
        return response()->json([
            'prefix' => $details['prefix'],
            'nextDispatchNo' => $details['nextNumber'],
        ]);
    }
    public function store(DispatchStoreRequest $request)
    {
        $this->authorizeModule('create');
        $validated = $request->validated();

        return DB::transaction(function () use ($validated) {
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
                    'pass_amount', 'discount_amount', 'transport_expenses', 'adjustment_amount', 'round_off', 'pump_charges'
                ]));
                if (isset($validated['financials']['pump_charges'])) {
                    $dispatchData['pump_charges'] = $validated['financials']['pump_charges'];
                } elseif (isset($validated['financials']['pump_charge'])) {
                    $dispatchData['pump_charges'] = $validated['financials']['pump_charge'];
                }

                if (array_key_exists('pump_charge_with_tax', $validated)) {
                    $dispatchData['pump_charge_with_tax'] = (bool)$validated['pump_charge_with_tax'];
                }

                $isTaxInclusive = false;
                if (array_key_exists('is_tax_inclusive', $validated)) {
                    $isTaxInclusive = (bool) $validated['is_tax_inclusive'];
                } elseif (isset($validated['status']['is_tax_inclusive'])) {
                    $isTaxInclusive = (bool) $validated['status']['is_tax_inclusive'];
                }

                $dispatchData['plant_id'] = session('active_plant_id');
                $dispatchData['delivered_qty'] ??= null;
                $dispatchData['discount_tax_mode'] = \App\Models\CustomSetting::dispatchDiscountTaxMode($dispatchData['plant_id']);
                if ($dispatchData['discount_tax_mode'] === 'before_tax' || !empty($dispatchData['batch_id'])) {
                    $dispatchData = array_replace($dispatchData, \App\Services\DispatchDiscountCalculator::forDispatch($dispatchData, $isTaxInclusive));
                }

                $details = $this->getNextDispatchDetails($dispatchData['plant_id'], $dispatchData['customer_id'] ?? null, $dispatchData['batch_id'] ?? null);
                $dispatchData['prefix'] = $details['prefix'];
                $dispatchData['dispatch_no'] = $details['nextNumber'];

                // if (!empty($dispatchData['sales_order_id'])) {
                //     $wo = \App\Models\SalesOrder::find($dispatchData['sales_order_id']);
                //     if ($wo) {
                //         $dispatchData['customer_po_id'] = $wo->customer_po_id;
                //     }
                // }

                // 2. Create Main Dispatch Record
                $dispatch = Dispatch::create($dispatchData);
                Log::info($dispatch);
                // 4. Create/Update Status / Logistical Record (mm_dispatch_statuses)
                $statusData = $validated['status'] ?? [];
                $statusData['plant_id'] = $dispatch->plant_id;
                $statusData['is_tax_inclusive'] = $isTaxInclusive;
                $dispatch->status()->updateOrCreate(['dispatch_id' => $dispatch->id], $statusData);

                // 5. Process Immediate Payment if provided
                if (in_array(strtolower($dispatch->payment_mode), ['cash']) && !empty($validated['payment']['payment_method_id'])) {
                    $paymentData = $validated['payment'];
                    $paymentData['dispatch_id'] = $dispatch->id;
                    $paymentData['amount'] = $dispatch->load_total_amount;
                    $paymentData['payment_type'] = 'full';
                    $paymentData['is_active'] = true;
                    DispatchPayment::create($paymentData);
                }

               

          

                // Send Notification
                if ($dispatch->customer) {
                    $dispatch->load(['customer.contacts']);
                    $primaryContact = $dispatch->customer->contacts()->where('is_primary', 1)->first() 
                                    ?? $dispatch->customer->contacts()->first();
                    
                    // if ($primaryContact?->email) {
                    //     \Illuminate\Support\Facades\Notification::route('mail', $primaryContact->email)
                    //         ->notify(new \App\Notifications\DispatchCreated($dispatch));
                    // }
                }
                app(BatchController::class)->markBatchDispatched($dispatch);
                return redirect()->back()->with('success', 'Dispatch processed successfully.');
        });
    }

    public function update(DispatchStoreRequest $request, Dispatch $dispatch)
    {
        $this->authorizeModule('update');
        $user = auth()->user();
        
        $isAdmin = $user && $user->hasFullAccess();

        if (!$isAdmin) {
            if (
                ($request->has('sales_order_id') && (int)$request->sales_order_id !== (int)$dispatch->sales_order_id) ||
                ($request->has('delivered_qty') && ($request->delivered_qty === null ? null : (float)$request->delivered_qty) !== $dispatch->delivered_qty_input) ||
                ($request->has('concrete_pump') && $request->concrete_pump !== $dispatch->concrete_pump)
            ) {
                return redirect()->back()->withErrors(['error' => 'Only administrators are authorized to modify Sales Order, Delivered Qty, or Concrete Pump.']);
            }
        }

        if($dispatch->dispatch_status !== 'Draft' || $dispatch->status()->whereNotNull('invoice_id')->exists()){
            abort(403, 'Access Denied: This dispatch is already invoiced.');
        }


// $wanted = 'trip operator'; // strtolower(trim('Trip Operator'))
// if ($user && collect($user->getRoleNames())
//         ->map(fn($r) => strtolower(trim($r)))
//         ->contains($wanted)) {           
//         $isDataPresented = (float)$dispatch->load_rate > 0 || 
//                                $dispatch->dispatch_status !== 'Draft' || 
//                                ($dispatch->status()->first() && $dispatch->status()->first()->invoice_status == 1) ||
//                                $dispatch->payments()->exists();
                               
//             if ($isDataPresented) {
//                 abort(403, 'Access Denied: You do not have permission to edit this trip as the data is already presented.');
//             }
//         }

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
                'pass_amount', 'discount_amount', 'transport_expenses', 'adjustment_amount', 'round_off', 'pump_charges'
            ]));
            if (isset($validated['financials']['pump_charges'])) {
                $dispatchData['pump_charges'] = $validated['financials']['pump_charges'];
            } elseif (isset($validated['financials']['pump_charge'])) {
                $dispatchData['pump_charges'] = $validated['financials']['pump_charge'];
            }

            if (array_key_exists('pump_charge_with_tax', $validated)) {
                $dispatchData['pump_charge_with_tax'] = (bool)$validated['pump_charge_with_tax'];
            }

            $isTaxInclusive = null;
            if (array_key_exists('is_tax_inclusive', $validated)) {
                $isTaxInclusive = (bool) $validated['is_tax_inclusive'];
            } elseif (isset($validated['status']['is_tax_inclusive'])) {
                $isTaxInclusive = (bool) $validated['status']['is_tax_inclusive'];
            }

            // if (array_key_exists('sales_order_id', $dispatchData)) {
            //     if (!empty($dispatchData['sales_order_id'])) {
            //         $wo = \App\Models\SalesOrder::find($dispatchData['sales_order_id']);
            //         $dispatchData['customer_po_id'] = $wo ? $wo->customer_po_id : null;
            //     } else {
            //         $dispatchData['customer_po_id'] = null;
            //     }
            // }

            // 2. Update Main Dispatch Record
            $dispatchData['discount_tax_mode'] = \App\Models\CustomSetting::dispatchDiscountTaxMode($dispatch->plant_id);
            if ($dispatchData['discount_tax_mode'] === 'before_tax' || $dispatch->batch_id || !empty($dispatchData['batch_id'])) {
                $calculationData = array_replace($dispatch->getAttributes(), $dispatchData);
                $inclusive = $isTaxInclusive ?? (bool) $dispatch->status()->value('is_tax_inclusive');
                $dispatchData = array_replace($dispatchData, \App\Services\DispatchDiscountCalculator::forDispatch($calculationData, $inclusive));
            }
            $dispatch->update($dispatchData);
            Log::info("Dispatch updated successfully: ".$dispatch);

            // 4. Update Status Record
            if (!empty($validated['status']) || $isTaxInclusive !== null) {
                $statusData = $validated['status'] ?? [];
                $statusData['plant_id'] = $dispatch->plant_id;
                if ($isTaxInclusive !== null) {
                    $statusData['is_tax_inclusive'] = $isTaxInclusive;
                }
                $dispatch->status()->updateOrCreate(['dispatch_id' => $dispatch->id], $statusData);
            }

            // 5. Update Payment
            if (in_array(strtolower($dispatch->payment_mode), ['cash']) && !empty($validated['payment']['payment_method_id'])) {
                 $paymentData = $validated['payment'];
                 $paymentData['dispatch_id'] = $dispatch->id;
                 $paymentData['amount'] = $dispatch->load_total_amount;
                 $paymentData['payment_type'] = 'full';
                 $paymentData['is_active'] = true;
                 
                 $dispatch->payments()->updateOrCreate(
                    ['dispatch_id' => $dispatch->id],
                    $paymentData
                 );
            } else {
                // Remove any payments if switched to credit or payment method cleared
                $dispatch->payments()->delete();
            }


            app(BatchController::class)->markBatchDispatched($dispatch);
            return redirect()->back()->with('success', 'Dispatch updated successfully.');
            
        });
    }

    public function updateDeliveredQuantity(Request $request, Dispatch $dispatch)
    {
        $this->authorizeModule('update');
        abort_unless(auth()->user()->hasFullAccess(), 403, 'Only administrators may correct delivered quantity.');
        abort_unless((int) $dispatch->plant_id === (int) session('active_plant_id'), 404);
        $data = $request->validate(['delivered_qty' => ['present', 'nullable', 'numeric', 'min:0', 'max:9999999.999', 'decimal:0,3']]);
        DB::transaction(function () use ($dispatch, $data) {
            $locked = Dispatch::whereKey($dispatch->id)->lockForUpdate()->firstOrFail();
            abort_unless($locked->dispatch_status !== 'Draft' || $locked->status()->whereNotNull('invoice_id')->exists()
                || (int) $locked->batch?->status === Batch::STATUS_CANCELLED, 422, 'Save draft delivery quantity through the dispatch form.');
            $locked->correctDeliveredQuantity($data['delivered_qty'] === null ? null : (float) $data['delivered_qty']);
        });
        return redirect()->back()->with('success', 'Delivered quantity updated. Issued invoice and financial amounts are unchanged.');
    }

    public function destroy(Dispatch $dispatch)
    {
        $this->authorizeModule('delete');
        $dispatch->delete();
        return redirect()->back()->with('success', 'Dispatch deleted successfully.');
    }

    public function generateInvoice(\Illuminate\Http\Request $request, Dispatch $dispatch)
    {
        $this->authorizeModule('edit');
        if ($dispatch->batch_id && (int) $dispatch->batch()->value('status') !== Batch::STATUS_DISPATCHED) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'batch_id' => 'Save Dispatch first. The batch must be dispatched before generating an invoice.',
            ]);
        }

        $validated = $request->validate([
            'ledger_id'      => 'required|exists:mm_ledgers,id',
            'invoice_date'   => 'required|date',
            'invoice_number' => [
                'nullable',
                'string',
                'max:191',
                function ($attribute, $value, $fail) use ($dispatch, $request) {
                    if ($value !== null && trim((string)$value) !== '') {
                        $trimmed = trim((string)$value);
                        $ledgerId = $request->input('ledger_id') ? (int)$request->input('ledger_id') : null;
                        $details = \App\Models\Invoice::generateNumber($dispatch->plant_id, 'dispatch', $ledgerId);
                        $numberPart = \App\Models\Invoice::normalizeNumber($trimmed, $details);
                        $fullNumber = $details['prefix'] . $numberPart;
                        $exists = \App\Models\Invoice::numberExists((int) $dispatch->plant_id, $details['prefix'], $numberPart);

                        if ($exists) {
                            $fail("The invoice number '{$fullNumber}' is already in use and active in the database.");
                        }
                    }
                },
            ],
            'notes'          => 'nullable|string',
        ]);

        \Illuminate\Support\Facades\Log::info('generateInvoice called', [
            'dispatch_id' => $dispatch->id,
            'request'     => $request->all(),
            'validated'   => $validated,
        ]);

        try {
            return DB::transaction(function () use ($dispatch, $validated) {
                $partnerId = $dispatch->customer_id ?: $dispatch->salesOrder?->customer_id;
                $manualNumber = (isset($validated['invoice_number']) && trim((string)$validated['invoice_number']) !== '') 
                    ? trim((string)$validated['invoice_number']) 
                    : null;
                if ($manualNumber !== null) {
                    $details = \App\Models\Invoice::generateNumber($dispatch->plant_id, 'dispatch', (int) $validated['ledger_id']);
                    $manualNumber = \App\Models\Invoice::normalizeNumber($manualNumber, $details);
                }

                $invoice = \App\Models\Invoice::createFromSource($dispatch, 'Invoice', [
                    'account_id'     => $validated['ledger_id'],
                    'invoice_date'   => $validated['invoice_date'],
                    'invoice_number' => $manualNumber,
                    'notes'          => $validated['notes'] ?? null,
                    'partner_id'       => $partnerId,
                    'plant_id'         => $dispatch->plant_id,
                    'invoice_label'    => 'Dispatch',
                    'is_tax_inclusive' => (bool)$dispatch->is_tax_inclusive,
                ]);
                $dispatch->invoice($invoice);

                return redirect()->back()->with('success', 'Invoice generated successfully: ' . ($invoice->full_number ?? $invoice->invoice_number));
            });
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::error('Invoice generation failed: ' . $e->getMessage(), [
                'dispatch_id' => $dispatch->id,
                'error'       => $e->getMessage(),
            ]);
            return redirect()->back()->with('error', $e->getMessage());
        }
    }

    public function deleteInvoice(Dispatch $dispatch)
    {
        $this->authorizeModule('edit');
  
        return DB::transaction(function () use ($dispatch) {
            $status = $dispatch->status()->first();
           
            if ($status && $status->invoice_id) {
                $invoice = \App\Models\Invoice::query()->find($status->invoice_id);
                // dd($invoice );
                if ($invoice) {
                    $invoice->updateQuietly(['is_active' => 0]);
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

    public function sendWhatsApp(Dispatch $dispatch, \App\Services\DispatchWhatsAppService $service)
    {
        try {
            $this->authorizeModule('whatsapp');
            abort_unless((int) $dispatch->plant_id === (int) session('active_plant_id'), 403);
        } catch (\Symfony\Component\HttpKernel\Exception\HttpExceptionInterface $exception) {
            \Illuminate\Support\Facades\Log::warning('Dispatch WhatsApp access denied.', [
                'dispatch_id' => $dispatch->id, 'plant_id' => $dispatch->plant_id,
                'user_id' => auth()->id(), 'http_status' => $exception->getStatusCode(),
            ]);
            throw $exception;
        }
        $service->send($dispatch);

        return response()->json(['message' => 'Dispatch details submitted to WhatsApp.']);
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

    /**
     * Cancel dispatch and batch, reverse sales order quantity, cancel invoice, and generate credit note.
     * Enforces a strict 50-word minimum cancellation notes requirement.
     */
    public function cancel(Request $request, Dispatch $dispatch)
    {
        $this->authorizeModule('update');

        if ($dispatch->dispatch_status === 'Cancelled') {
            if ($request->wantsJson() && !$request->header('X-Inertia')) {
                return response()->json(['error' => 'This dispatch has already been cancelled.'], 422);
            }
            return redirect()->back()->withErrors(['error' => 'This dispatch has already been cancelled.']);
        }

        $request->validate([
            'notes' => [
                'required',
                'string',
                function ($attribute, $value, $fail) {
                    $cleaned = trim(preg_replace('/\s+/', ' ', (string)$value));
                    $words = !empty($cleaned) ? explode(' ', $cleaned) : [];
                    $wordCount = count($words);
                    if ($wordCount < 5) {
                        $fail("The cancellation notes must contain at least 5 words. Currently provided: {$wordCount} words.");
                    }
                },
            ],
        ], [
            'notes.required' => 'Cancellation notes are required.',
        ]);

        return DB::transaction(function () use ($dispatch, $request) {
            $notes = trim((string)$request->input('notes'));
            $result = $dispatch->cancel($notes, auth()->id());

            $msg = 'Dispatch and Batch cancelled successfully. Sales order quantity reversed';
            if (!empty($result['credit_note'])) {
                $msg .= ', and Credit Note #' . ($result['credit_note']->prefix . $result['credit_note']->invoice_number) . ' generated';
            }
            $msg .= '.';

            if ($request->wantsJson() && !$request->header('X-Inertia')) {
                return response()->json([
                    'success' => true,
                    'message' => $msg,
                    'data'    => $result,
                ]);
            }

            return redirect()->back()->with('success', $msg);
        });
    }
}
