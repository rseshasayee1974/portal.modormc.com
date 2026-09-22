<?php

namespace App\Services\Reports;

use App\Services\PlantContextService;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\Batch;
use App\Models\Dispatch;
use App\Models\Expense;
use App\Models\EwaybillDetail;
use App\Models\JournalEntry;
use App\Models\AccountDiscount;
use App\Models\CustomerPO;
use App\Models\Quotation;
use App\Models\SalesOrder;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Collection;

class DeletedReportService implements ReportServiceInterface
{
    public function __construct(private readonly PlantContextService $ctx) {}

    public function generate(array $params): array
    {
        $plantId  = $params['plant_id'] ?? $this->ctx->plantId() ?? session('active_plant_id');
        $start    = $params['start'] ?? now()->subDays(30)->startOfDay()->toDateTimeString();
        $end      = $params['end'] ?? now()->endOfDay()->toDateTimeString();
        $patronId = !empty($params['patron_id']) ? (int) $params['patron_id'] : null;

        $items = collect();

        // 1. INVOICES (Sales Invoices, Credit Notes, Debit Notes)
        try {
            $invQuery = Invoice::onlyTrashed()
                ->where('plant_id', $plantId)
                ->where('invoice_type', '!=', 'bill')
                ->whereBetween('deleted_at', [$start, $end])
                ->with(['partner:id,legal_name']);

            if ($patronId) {
                $invQuery->where('partner_id', $patronId);
            }

            $invQuery->get()->each(function ($inv) use (&$items) {
                $items->push([
                    'id'            => 'invoice_' . $inv->id,
                    'raw_id'        => $inv->id,
                    'entity_type'   => 'Invoice',
                    'entity_key'    => 'invoice',
                    'reference_no'  => $inv->full_number ?: ($inv->invoice_number ?: ('INV-' . $inv->id)),
                    'original_date' => $inv->invoice_date ? Carbon::parse($inv->invoice_date)->format('d-m-Y') : 'N/A',
                    'deleted_at'    => $inv->deleted_at ? Carbon::parse($inv->deleted_at)->format('d-m-Y H:i') : 'N/A',
                    'deleted_at_raw'=> $inv->deleted_at,
                    'deleted_by_id' => $inv->deleted_by,
                    'customer_name' => $inv->partner?->legal_name ?: 'N/A',
                    'amount'        => (float) ($inv->total_amount ?? 0),
                    'notes'         => 'Type: ' . ucfirst($inv->invoice_type ?? 'Invoice') . ($inv->status ? ' | Status: ' . $inv->status : '') . ($inv->notes ? ' | ' . $inv->notes : ''),
                ]);
            });
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::warning("DeletedReportService: Error querying invoices: " . $e->getMessage());
        }

        // 2. BILLS (Purchase Bills)
        try {
            $billQuery = Invoice::onlyTrashed()
                ->where('plant_id', $plantId)
                ->where('invoice_type', '=', 'bill')
                ->whereBetween('deleted_at', [$start, $end])
                ->with(['partner:id,legal_name']);

            if ($patronId) {
                $billQuery->where('partner_id', $patronId);
            }

            $billQuery->get()->each(function ($bill) use (&$items) {
                $items->push([
                    'id'            => 'bill_' . $bill->id,
                    'raw_id'        => $bill->id,
                    'entity_type'   => 'Bill',
                    'entity_key'    => 'bill',
                    'reference_no'  => $bill->full_number ?: ($bill->invoice_number ?: ('BILL-' . $bill->id)),
                    'original_date' => $bill->invoice_date ? Carbon::parse($bill->invoice_date)->format('d-m-Y') : 'N/A',
                    'deleted_at'    => $bill->deleted_at ? Carbon::parse($bill->deleted_at)->format('d-m-Y H:i') : 'N/A',
                    'deleted_at_raw'=> $bill->deleted_at,
                    'deleted_by_id' => $bill->deleted_by,
                    'customer_name' => $bill->partner?->legal_name ?: 'N/A',
                    'amount'        => (float) ($bill->total_amount ?? 0),
                    'notes'         => 'Purchase Bill' . ($bill->status ? ' | Status: ' . $bill->status : '') . ($bill->notes ? ' | ' . $bill->notes : ''),
                ]);
            });
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::warning("DeletedReportService: Error querying bills: " . $e->getMessage());
        }

        // 3. PAYMENTS
        try {
            $paymentQuery = Payment::onlyTrashed()
                ->where('plant_id', $plantId)
                ->where(function ($q) {
                    $q->where('transaction_type', 'payment')
                      ->orWhere('transaction_type', 'Payment');
                })
                ->whereBetween('deleted_at', [$start, $end])
                ->with(['patron:id,legal_name']);

            if ($patronId) {
                $paymentQuery->where('patron_id', $patronId);
            }

            $paymentQuery->get()->each(function ($pay) use (&$items) {
                $items->push([
                    'id'            => 'payment_' . $pay->id,
                    'raw_id'        => $pay->id,
                    'entity_type'   => 'Payment',
                    'entity_key'    => 'payment',
                    'reference_no'  => $pay->reference ?: ('PAY-' . $pay->id),
                    'original_date' => $pay->transaction_date ? Carbon::parse($pay->transaction_date)->format('d-m-Y') : 'N/A',
                    'deleted_at'    => $pay->deleted_at ? Carbon::parse($pay->deleted_at)->format('d-m-Y H:i') : 'N/A',
                    'deleted_at_raw'=> $pay->deleted_at,
                    'deleted_by_id' => $pay->deleted_by,
                    'customer_name' => $pay->patron?->legal_name ?: 'N/A',
                    'amount'        => (float) ($pay->amount ?? 0),
                    'notes'         => 'Mode: ' . ($pay->transaction_mode ?: 'N/A') . ($pay->description ? ' | ' . $pay->description : ''),
                ]);
            });
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::warning("DeletedReportService: Error querying payments: " . $e->getMessage());
        }

        // 4. RECEIPTS
        try {
            $receiptQuery = Payment::onlyTrashed()
                ->where('plant_id', $plantId)
                ->where(function ($q) {
                    $q->where('transaction_type', 'receipt')
                      ->orWhere('transaction_type', 'Receipt');
                })
                ->whereBetween('deleted_at', [$start, $end])
                ->with(['patron:id,legal_name']);

            if ($patronId) {
                $receiptQuery->where('patron_id', $patronId);
            }

            $receiptQuery->get()->each(function ($rec) use (&$items) {
                $items->push([
                    'id'            => 'receipt_' . $rec->id,
                    'raw_id'        => $rec->id,
                    'entity_type'   => 'Receipt',
                    'entity_key'    => 'receipt',
                    'reference_no'  => $rec->reference ?: ('REC-' . $rec->id),
                    'original_date' => $rec->transaction_date ? Carbon::parse($rec->transaction_date)->format('d-m-Y') : 'N/A',
                    'deleted_at'    => $rec->deleted_at ? Carbon::parse($rec->deleted_at)->format('d-m-Y H:i') : 'N/A',
                    'deleted_at_raw'=> $rec->deleted_at,
                    'deleted_by_id' => $rec->deleted_by,
                    'customer_name' => $rec->patron?->legal_name ?: 'N/A',
                    'amount'        => (float) ($rec->amount ?? 0),
                    'notes'         => 'Mode: ' . ($rec->transaction_mode ?: 'N/A') . ($rec->description ? ' | ' . $rec->description : ''),
                ]);
            });
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::warning("DeletedReportService: Error querying receipts: " . $e->getMessage());
        }

        // 5. BATCHES
        try {
            $batchQuery = Batch::onlyTrashed()
                ->where('plant_id', $plantId)
                ->whereBetween('deleted_at', [$start, $end])
                ->with(['salesOrder.customer:id,legal_name']);

            if ($patronId) {
                $batchQuery->whereHas('salesOrder', fn($so) => $so->where('customer_id', $patronId));
            }

            $batchQuery->get()->each(function ($batch) use (&$items) {
                $items->push([
                    'id'            => 'batch_' . $batch->id,
                    'raw_id'        => $batch->id,
                    'entity_type'   => 'Batch',
                    'entity_key'    => 'batch',
                    'reference_no'  => $batch->batch_no ? ('B' . $batch->batch_no) : ('BATCH-' . $batch->id),
                    'original_date' => $batch->start_time ? Carbon::parse($batch->start_time)->format('d-m-Y H:i') : ($batch->created_at ? Carbon::parse($batch->created_at)->format('d-m-Y') : 'N/A'),
                    'deleted_at'    => $batch->deleted_at ? Carbon::parse($batch->deleted_at)->format('d-m-Y H:i') : 'N/A',
                    'deleted_at_raw'=> $batch->deleted_at,
                    'deleted_by_id' => $batch->deleted_by,
                    'customer_name' => $batch->salesOrder?->customer?->legal_name ?: 'N/A',
                    'amount'        => (float) ($batch->batch_size ?? 0),
                    'notes'         => 'Batch Size: ' . number_format($batch->batch_size ?? 0, 2) . ' m³ | Status: ' . Batch::statusLabel((int)$batch->status),
                ]);
            });
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::warning("DeletedReportService: Error querying batches: " . $e->getMessage());
        }

        // 6. DISPATCHES
        try {
            $dispatchQuery = Dispatch::onlyTrashed()
                ->where('plant_id', $plantId)
                ->whereBetween('deleted_at', [$start, $end])
                ->with(['customer:id,legal_name', 'truck:id,registration']);

            if ($patronId) {
                $dispatchQuery->where('customer_id', $patronId);
            }

            $dispatchQuery->get()->each(function ($dispatch) use (&$items) {
                $dispNo = ($dispatch->prefix ?? '') . ($dispatch->dispatch_no ?: $dispatch->id);
                $items->push([
                    'id'            => 'dispatch_' . $dispatch->id,
                    'raw_id'        => $dispatch->id,
                    'entity_type'   => 'Dispatch',
                    'entity_key'    => 'dispatch',
                    'reference_no'  => $dispNo,
                    'original_date' => $dispatch->dispatch_time ? Carbon::parse($dispatch->dispatch_time)->format('d-m-Y H:i') : ($dispatch->created_at ? Carbon::parse($dispatch->created_at)->format('d-m-Y') : 'N/A'),
                    'deleted_at'    => $dispatch->deleted_at ? Carbon::parse($dispatch->deleted_at)->format('d-m-Y H:i') : 'N/A',
                    'deleted_at_raw'=> $dispatch->deleted_at,
                    'deleted_by_id' => $dispatch->deleted_by,
                    'customer_name' => $dispatch->customer?->legal_name ?: 'N/A',
                    'amount'        => (float) ($dispatch->load_total_amount ?: ($dispatch->delivered_qty ?? 0)),
                    'notes'         => 'Qty: ' . number_format($dispatch->delivered_qty ?? 0, 2) . ' m³ | Truck: ' . ($dispatch->truck?->registration ?: 'N/A'),
                ]);
            });
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::warning("DeletedReportService: Error querying dispatches: " . $e->getMessage());
        }

        // 7. EXPENSES
        try {
            $expenseQuery = Expense::onlyTrashed()
                ->where('plant_id', $plantId)
                ->whereBetween('deleted_at', [$start, $end])
                ->with(['customer:id,legal_name', 'vendor:id,legal_name', 'expenseType:id,name']);

            if ($patronId) {
                $expenseQuery->where(function ($q) use ($patronId) {
                    $q->where('customer_id', $patronId)
                      ->orWhere('vendor_id', $patronId);
                });
            }

            $expenseQuery->get()->each(function ($exp) use (&$items) {
                $party = $exp->customer?->legal_name ?: ($exp->vendor?->legal_name ?: 'N/A');
                $items->push([
                    'id'            => 'expense_' . $exp->id,
                    'raw_id'        => $exp->id,
                    'entity_type'   => 'Expense',
                    'entity_key'    => 'expense',
                    'reference_no'  => $exp->ref_no ?: ('EXP-' . $exp->id),
                    'original_date' => $exp->date ? Carbon::parse($exp->date)->format('d-m-Y') : 'N/A',
                    'deleted_at'    => $exp->deleted_at ? Carbon::parse($exp->deleted_at)->format('d-m-Y H:i') : 'N/A',
                    'deleted_at_raw'=> $exp->deleted_at,
                    'deleted_by_id' => $exp->deleted_by,
                    'customer_name' => $party,
                    'amount'        => (float) ($exp->amount ?? 0),
                    'notes'         => ($exp->expenseType?->name ?: 'Expense') . ($exp->note ? ' | ' . $exp->note : ''),
                ]);
            });
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::warning("DeletedReportService: Error querying expenses: " . $e->getMessage());
        }

        // 8. E-WAY BILLS
        try {
            $ewayQuery = EwaybillDetail::where('plant_id', $plantId)
                ->where(function ($q) use ($start, $end) {
                    $q->where(function ($sub) use ($start, $end) {
                        $sub->whereNotNull('ewaybill_cancel_at')
                            ->whereBetween('ewaybill_cancel_at', [$start, $end]);
                    })->orWhere(function ($sub) use ($start, $end) {
                        $sub->where('ewaybill_status', 'CANCELLED')
                            ->whereBetween('modified_at', [$start, $end]);
                    })->orWhere(function ($sub) use ($start, $end) {
                        $sub->whereHas('invoice', fn($inv) => $inv->onlyTrashed()->whereBetween('deleted_at', [$start, $end]));
                    });
                })
                ->with(['invoice.partner:id,legal_name']);

            if ($patronId) {
                $ewayQuery->whereHas('invoice', fn($inv) => $inv->where('partner_id', $patronId));
            }

            $ewayQuery->get()->each(function ($ewb) use (&$items) {
                $cancelTime = $ewb->ewaybill_cancel_at ?: ($ewb->modified_at ?: $ewb->created_at);
                $items->push([
                    'id'            => 'ewaybill_' . $ewb->id,
                    'raw_id'        => $ewb->id,
                    'entity_type'   => 'E-Way Bill',
                    'entity_key'    => 'ewaybill',
                    'reference_no'  => $ewb->ewaybill_no ?: ('EWB-' . $ewb->id),
                    'original_date' => $ewb->ewaybill_date ? Carbon::parse($ewb->ewaybill_date)->format('d-m-Y') : 'N/A',
                    'deleted_at'    => $cancelTime ? Carbon::parse($cancelTime)->format('d-m-Y H:i') : 'N/A',
                    'deleted_at_raw'=> $cancelTime,
                    'deleted_by_id' => $ewb->ewaybill_cancel_by ?: $ewb->modified_by,
                    'customer_name' => $ewb->invoice?->partner?->legal_name ?: 'N/A',
                    'amount'        => (float) ($ewb->invoice?->total_amount ?? 0),
                    'notes'         => 'Status: ' . ($ewb->ewaybill_status ?: 'CANCELLED') . ($ewb->valid_upto ? ' | Valid Upto: ' . $ewb->valid_upto : ''),
                ]);
            });
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::warning("DeletedReportService: Error querying ewaybills: " . $e->getMessage());
        }

        // 9. JOURNAL ENTRIES
        try {
            $jeQuery = JournalEntry::onlyTrashed()
                ->where('plant_id', $plantId)
                ->whereBetween('deleted_at', [$start, $end])
                ->with(['lines.partner:id,legal_name']);

            if ($patronId) {
                $jeQuery->whereHas('lines', fn($l) => $l->where('partner_id', $patronId));
            }

            $jeQuery->get()->each(function ($je) use (&$items) {
                $partyName = $je->lines->firstWhere('partner_id', '!=', null)?->partner?->legal_name ?: 'N/A';
                $items->push([
                    'id'            => 'journal_' . $je->id,
                    'raw_id'        => $je->id,
                    'entity_type'   => 'Journal Entry',
                    'entity_key'    => 'journal_entry',
                    'reference_no'  => $je->voucher_number ?: ('JV-' . $je->id),
                    'original_date' => $je->voucher_date ? Carbon::parse($je->voucher_date)->format('d-m-Y') : 'N/A',
                    'deleted_at'    => $je->deleted_at ? Carbon::parse($je->deleted_at)->format('d-m-Y H:i') : 'N/A',
                    'deleted_at_raw'=> $je->deleted_at,
                    'deleted_by_id' => $je->deleted_by,
                    'customer_name' => $partyName,
                    'amount'        => (float) ($je->total_debit ?: ($je->total_credit ?? 0)),
                    'notes'         => ($je->narration_label ?: $je->voucher_type) . ($je->narration ? ' | ' . $je->narration : ''),
                ]);
            });
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::warning("DeletedReportService: Error querying journal entries: " . $e->getMessage());
        }

        // 10. ACCOUNT DISCOUNTS
        try {
            $discQuery = AccountDiscount::onlyTrashed()
                ->where('plant_id', $plantId)
                ->whereBetween('deleted_at', [$start, $end])
                ->with(['partner:id,legal_name']);

            if ($patronId) {
                $discQuery->where('partner_id', $patronId);
            }

            $discQuery->get()->each(function ($disc) use (&$items) {
                $items->push([
                    'id'            => 'discount_' . $disc->id,
                    'raw_id'        => $disc->id,
                    'entity_type'   => 'Discount',
                    'entity_key'    => 'discount',
                    'reference_no'  => $disc->reference_number ?: ('DISC-' . $disc->id),
                    'original_date' => $disc->date ? Carbon::parse($disc->date)->format('d-m-Y') : 'N/A',
                    'deleted_at'    => $disc->deleted_at ? Carbon::parse($disc->deleted_at)->format('d-m-Y H:i') : 'N/A',
                    'deleted_at_raw'=> $disc->deleted_at,
                    'deleted_by_id' => $disc->deleted_by,
                    'customer_name' => $disc->partner?->legal_name ?: 'N/A',
                    'amount'        => (float) ($disc->amount ?? 0),
                    'notes'         => ($disc->primary_type ?: '') . ' Discount' . ($disc->note ? ' | ' . $disc->note : ''),
                ]);
            });
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::warning("DeletedReportService: Error querying discounts: " . $e->getMessage());
        }

        // 11. ADDITIONAL ENTITIES (Customer PO, Quotation, Sales Order)
        try {
            // Customer POs
            $poQuery = CustomerPO::onlyTrashed()
                ->where('plant_id', $plantId)
                ->whereBetween('deleted_at', [$start, $end])
                ->with(['customer:id,legal_name']);

            if ($patronId) {
                $poQuery->where('customer_id', $patronId);
            }

            $poQuery->get()->each(function ($po) use (&$items) {
                $items->push([
                    'id'            => 'po_' . $po->id,
                    'raw_id'        => $po->id,
                    'entity_type'   => 'Customer PO',
                    'entity_key'    => 'customer_po',
                    'reference_no'  => $po->po_number ?: ('PO-' . $po->id),
                    'original_date' => $po->po_date ? Carbon::parse($po->po_date)->format('d-m-Y') : 'N/A',
                    'deleted_at'    => $po->deleted_at ? Carbon::parse($po->deleted_at)->format('d-m-Y H:i') : 'N/A',
                    'deleted_at_raw'=> $po->deleted_at,
                    'deleted_by_id' => $po->deleted_by,
                    'customer_name' => $po->customer?->legal_name ?: 'N/A',
                    'amount'        => (float) ($po->total_amount ?? 0),
                    'notes'         => 'Status: ' . ($po->status ?: 'N/A'),
                ]);
            });

            // Quotations
            $quoteQuery = Quotation::onlyTrashed()
                ->where('plant_id', $plantId)
                ->whereBetween('deleted_at', [$start, $end])
                ->with(['customer:id,legal_name']);

            if ($patronId) {
                $quoteQuery->where('customer_id', $patronId);
            }

            $quoteQuery->get()->each(function ($q) use (&$items) {
                $items->push([
                    'id'            => 'quotation_' . $q->id,
                    'raw_id'        => $q->id,
                    'entity_type'   => 'Quotation',
                    'entity_key'    => 'quotation',
                    'reference_no'  => $q->quotation_no ?: ('QT-' . $q->id),
                    'original_date' => $q->quotation_date ? Carbon::parse($q->quotation_date)->format('d-m-Y') : 'N/A',
                    'deleted_at'    => $q->deleted_at ? Carbon::parse($q->deleted_at)->format('d-m-Y H:i') : 'N/A',
                    'deleted_at_raw'=> $q->deleted_at,
                    'deleted_by_id' => $q->deleted_by,
                    'customer_name' => $q->customer?->legal_name ?: 'N/A',
                    'amount'        => (float) ($q->grand_total ?? 0),
                    'notes'         => 'Status: ' . ($q->status ?: 'N/A'),
                ]);
            });

            // Sales Orders
            $soQuery = SalesOrder::onlyTrashed()
                ->where('plant_id', $plantId)
                ->whereBetween('deleted_at', [$start, $end])
                ->with(['customer:id,legal_name']);

            if ($patronId) {
                $soQuery->where('customer_id', $patronId);
            }

            $soQuery->get()->each(function ($so) use (&$items) {
                $items->push([
                    'id'            => 'so_' . $so->id,
                    'raw_id'        => $so->id,
                    'entity_type'   => 'Sales Order',
                    'entity_key'    => 'sales_order',
                    'reference_no'  => ($so->prefix ?? '') . ($so->order_no ?: $so->id),
                    'original_date' => $so->order_date ? Carbon::parse($so->order_date)->format('d-m-Y') : 'N/A',
                    'deleted_at'    => $so->deleted_at ? Carbon::parse($so->deleted_at)->format('d-m-Y H:i') : 'N/A',
                    'deleted_at_raw'=> $so->deleted_at,
                    'deleted_by_id' => $so->deleted_by,
                    'customer_name' => $so->customer?->legal_name ?: 'N/A',
                    'amount'        => (float) ($so->total_amount ?? 0),
                    'notes'         => 'Status: ' . ($so->status ?: 'N/A'),
                ]);
            });
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::warning("DeletedReportService: Error querying orders/quotations: " . $e->getMessage());
        }

        // Map deleted_by usernames
        $deletedByIds = $items->pluck('deleted_by_id')->filter()->unique()->values()->all();
        $usersMap = [];
        if (!empty($deletedByIds)) {
            $usersMap = User::whereIn('id', $deletedByIds)
                ->get(['id', 'username', 'email'])
                ->keyBy('id')
                ->map(fn($u) => $u->username ?: $u->email ?: ('User #' . $u->id))
                ->toArray();
        }

        $sortedItems = $items->map(function ($row) use ($usersMap) {
            $byId = $row['deleted_by_id'] ?? null;
            $row['deleted_by'] = $byId && isset($usersMap[$byId]) ? $usersMap[$byId] : ($byId ? ('User #' . $byId) : 'System');
            return $row;
        })->sortByDesc('deleted_at_raw')->values();

        // Breakdown stats by entity type
        $typeCounts = [];
        $typeAmounts = [];
        foreach ($sortedItems as $row) {
            $t = $row['entity_type'];
            $typeCounts[$t] = ($typeCounts[$t] ?? 0) + 1;
            $typeAmounts[$t] = ($typeAmounts[$t] ?? 0) + (float)($row['amount'] ?? 0);
        }

        return [
            'transactions'   => $sortedItems->all(),
            'items'          => $sortedItems->all(),
            'total_deleted'  => $sortedItems->count(),
            'total_amount'   => (float) $sortedItems->sum('amount'),
            'type_counts'    => $typeCounts,
            'type_amounts'   => $typeAmounts,
        ];
    }

    public function targetName(array $params): string
    {
        return 'Deleted Report';
    }
}
