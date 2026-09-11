<?php

namespace App\Services\Reports;

use App\Models\Invoice;
use App\Models\JournalEntryLine;
use App\Models\Patron;
use App\Models\Payment;
use App\Models\Plant;
use App\Models\PurchaseOrder;
use App\Services\PlantContextService;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class CustomerOutstandingReportService implements ReportServiceInterface
{
    public function __construct(private readonly PlantContextService $ctx) {}

    public function generate(array $params): array
    {
        $plantId  = $params['plant_id'] ?? $this->ctx->plantId() ?? session('active_plant_id');
        $patronId = $params['patron_id'] ?? null;
        $start    = $params['start'] ?? null;
        $end      = $params['end'] ?? null;

        if ($patronId) {
            return $this->generateSinglePatronStatement((int)$patronId, $plantId, $start, $end, $params);
        }

        // 1. Query Sales Invoices (whereNull('deleted_at') and status != 'Cancelled')
        $invoiceQuery = Invoice::query()
            ->where('invoice_type', 'sales')
            ->whereNull('deleted_at')
            ->where(function ($q) {
                $q->whereNull('status')
                  ->orWhere('status', '!=', 'Cancelled');
            });

        if ($plantId) {
            $invoiceQuery->where('plant_id', $plantId);
        }

        if ($patronId) {
            $invoiceQuery->where('partner_id', $patronId);
        }

        if ($end) {
            $invoiceQuery->where('invoice_date', '<=', $end);
        }

        $invoices = $invoiceQuery->orderBy('invoice_date', 'asc')
            ->orderBy('id', 'asc')
            ->get();

        // 2. Fetch all Payments and Receipts from mm_payments where deleted_at IS NULL
        $paymentQuery = \App\Models\Payment::query()
            ->with(['ledger:id,title'])
            ->whereNull('deleted_at')
            ->whereNotNull('patron_id')
            ->where(function ($q) {
                $q->whereNull('status')
                  ->orWhereNotIn('status', ['cancelled', 'rejected', 'failed']);
            });

        if ($plantId) {
            $paymentQuery->where('plant_id', $plantId);
        }

        if ($patronId) {
            $paymentQuery->where('patron_id', $patronId);
        }

        if ($end) {
            $endDateOnly = substr($end, 0, 10);
            $paymentQuery->where('transaction_date', '<=', $endDateOnly);
        }

        $allPaymentsAndReceipts = $paymentQuery->orderBy('transaction_date', 'desc')
            ->orderBy('id', 'desc')
            ->get();

        // 3. Collect distinct patron IDs across invoices and payment/receipt transactions
        $patronIdsFromInvoices = $invoices->pluck('partner_id')->filter()->unique()->all();
        $patronIdsFromPayments = $allPaymentsAndReceipts->pluck('patron_id')->filter()->unique()->all();
        $allPatronIds = array_values(array_unique(array_merge($patronIdsFromInvoices, $patronIdsFromPayments)));

        if ($patronId && !in_array($patronId, $allPatronIds)) {
            $allPatronIds[] = $patronId;
        }

        // 4. Load Customer Master Details (whereNull('deleted_at'))
        $allPatrons = Patron::with(['contacts:id,patron_id,name,mobile,alt_mobile,landline,email,is_primary'])
            ->whereNull('deleted_at')
            ->whereIn('id', $allPatronIds)
            ->get()
            ->keyBy('id');

        // Group invoices and payment transactions by customer
        $invoicesByCustomer = $invoices->groupBy('partner_id');
        $receiptsByCustomer = $allPaymentsAndReceipts->filter(function ($p) {
            $type = strtolower($p->transaction_type ?? '');
            return in_array($type, ['receipt', 'rcpt']) || str_contains($type, 'receipt');
        })->groupBy('patron_id');

        $paymentsByCustomer = $allPaymentsAndReceipts->filter(function ($p) {
            $type = strtolower($p->transaction_type ?? '');
            return in_array($type, ['payment', 'pmt']) || (!str_contains($type, 'receipt') && $type === 'payment');
        })->groupBy('patron_id');

        $today = now()->startOfDay();
        $allOpenInvoices = [];
        $allReceiptsList = [];
        $allPaymentsList = [];
        $customersList   = [];

        // 5. Process each customer
        foreach ($allPatronIds as $cId) {
            $partner = $allPatrons->get($cId);
            $contacts = $partner?->contacts ?? collect();
            $primaryContact = $contacts->firstWhere('is_primary', 1) ?? $contacts->first();

            $customerCode = $partner?->code ?? '-';
            $customerName = $partner?->legal_name ?? ('Customer #' . $cId);
            $phone = $primaryContact?->mobile ?: ($primaryContact?->alt_mobile ?: ($primaryContact?->landline ?? '-'));

            $custInvoices = $invoicesByCustomer->get($cId, collect())->sortBy('invoice_date')->values();
            $custReceipts = $receiptsByCustomer->get($cId, collect())->values();
            $custPayments = $paymentsByCustomer->get($cId, collect())->values();

            // Total Invoiced, Receipts, and Payments
            $totalInvoiced = round((float)$custInvoices->sum(fn($i) => (float)($i->total_amount ?? 0)), 2);
            $totalReceipt  = round((float)$custReceipts->sum(fn($p) => (float)($p->amount ?? 0)), 2);
            $totalPayment  = round((float)$custPayments->sum(fn($p) => (float)($p->amount ?? 0)), 2);

            // Net collections from customer = Receipts - Payments / Refunds
            $netCollections = max(0.00, round($totalReceipt - $totalPayment, 2));

            // Outstanding Balance = Total Invoiced + Total Payments - Total Receipts
            $netOutstanding = round($totalInvoiced + $totalPayment - $totalReceipt, 2);

            $aging0to30    = 0.00;
            $aging31to60   = 0.00;
            $aging61to90   = 0.00;
            $aging90plus   = 0.00;
            $openInvoicesCount = 0;

            $custInvoiceItems     = [];
            $custOpenInvoiceItems = [];
            $custReceiptItems     = [];
            $custPaymentItems     = [];

            // Process Invoices with FIFO allocation of Net Collections
            $remainingCollectionsToAllocate = $netCollections;

            foreach ($custInvoices as $inv) {
                $invTotal = round((float)($inv->total_amount ?? 0), 2);

                if ($remainingCollectionsToAllocate >= $invTotal) {
                    $invPaid = $invTotal;
                    $invBal  = 0.00;
                    $remainingCollectionsToAllocate = round($remainingCollectionsToAllocate - $invTotal, 2);
                } else {
                    $invPaid = $remainingCollectionsToAllocate;
                    $invBal  = round($invTotal - $invPaid, 2);
                    $remainingCollectionsToAllocate = 0.00;
                }

                $dueDate = !empty($inv->due_date) 
                    ? Carbon::parse($inv->due_date)->startOfDay() 
                    : ($inv->invoice_date ? Carbon::parse($inv->invoice_date)->startOfDay() : $today);

                $daysPastDue = (int)$dueDate->diffInDays($today, false);
                $daysOverdue = max(0, $daysPastDue);
                $isOverdue   = $daysOverdue > 0;

                $bracket = match (true) {
                    $daysOverdue > 90 => 'aging_90_plus',
                    $daysOverdue > 60 => 'aging_61_90',
                    $daysOverdue > 30 => 'aging_31_60',
                    default           => 'aging_0_30',
                };

                $invItem = [
                    'id'             => $inv->id,
                    'encrypted_id'   => $inv->encrypted_id,
                    'full_number'    => $inv->full_number ?: ('INV-' . $inv->id),
                    'prefix'         => $inv->prefix,
                    'invoice_number' => $inv->invoice_number,
                    'invoice_date'   => $inv->invoice_date ? Carbon::parse($inv->invoice_date)->format('d/m/Y') : '-',
                    'raw_date'       => $inv->invoice_date ? Carbon::parse($inv->invoice_date)->format('Y-m-d') : '',
                    'due_date'       => $inv->due_date ? Carbon::parse($inv->due_date)->format('d/m/Y') : '-',
                    'raw_due_date'   => $inv->due_date ? Carbon::parse($inv->due_date)->format('Y-m-d') : '',
                    'days_overdue'   => $daysOverdue,
                    'is_overdue'     => $isOverdue,
                    'aging_bucket'   => $bracket,
                    'total_amount'   => $invTotal,
                    'paid_amount'    => $invPaid,
                    'balance_amount' => $invBal,
                    'status'         => $invBal <= 0 ? 'Paid' : ($invPaid > 0 ? 'Partially Paid' : 'Unpaid'),
                    'customer_id'    => $cId,
                    'customer_name'  => $customerName,
                    'customer_code'  => $customerCode,
                ];

                $custInvoiceItems[] = $invItem;

                if ($invBal > 0) {
                    $custOpenInvoiceItems[] = $invItem;
                    $allOpenInvoices[]      = $invItem;
                    $openInvoicesCount++;

                    if ($bracket === 'aging_90_plus') {
                        $aging90plus += $invBal;
                    } elseif ($bracket === 'aging_61_90') {
                        $aging61to90 += $invBal;
                    } elseif ($bracket === 'aging_31_60') {
                        $aging31to60 += $invBal;
                    } else {
                        $aging0to30  += $invBal;
                    }
                }
            }

            // If net balance is zero or customer is in advance
            if ($netOutstanding <= 0) {
                $unallocatedAdvance = abs($netOutstanding);
                $finalOutstanding   = 0.00;
                $aging0to30         = 0.00;
                $aging31to60        = 0.00;
                $aging61to90        = 0.00;
                $aging90plus        = 0.00;
            } else {
                $unallocatedAdvance = 0.00;
                $finalOutstanding   = $netOutstanding;

                // If customer has a net debit from extra payments/refunds without corresponding invoices, bucket in 0-30
                $sumAging = round($aging0to30 + $aging31to60 + $aging61to90 + $aging90plus, 2);
                if ($finalOutstanding > $sumAging) {
                    $aging0to30 = round($aging0to30 + ($finalOutstanding - $sumAging), 2);
                }
            }

            // Process Receipts
            foreach ($custReceipts as $p) {
                $pAmt = round((float)($p->amount ?? 0), 2);
                $rItem = [
                    'id'               => $p->id,
                    'voucher_no'       => $p->reference ?? ('RCPT-' . $p->id),
                    'reference'        => $p->reference,
                    'transaction_date' => $p->transaction_date ? Carbon::parse($p->transaction_date)->format('d/m/Y') : '-',
                    'raw_date'         => $p->transaction_date ? Carbon::parse($p->transaction_date)->format('Y-m-d') : '',
                    'amount'           => $pAmt,
                    'type'             => 'receipt',
                    'mode'             => ucfirst($p->transaction_mode ?: 'Cash'),
                    'account'          => $p->ledger?->title ?? 'Cash/Bank',
                    'status'           => ucfirst($p->status ?: 'paid'),
                    'description'      => $p->description ?? '',
                    'customer_id'      => $cId,
                    'customer_name'    => $customerName,
                    'customer_code'    => $customerCode,
                ];
                $custReceiptItems[] = $rItem;
                $allReceiptsList[]  = $rItem;
            }

            // Process Payments (Refunds/Outflows to customer)
            foreach ($custPayments as $p) {
                $pAmt = round((float)($p->amount ?? 0), 2);
                $pItem = [
                    'id'               => $p->id,
                    'voucher_no'       => $p->reference ?? ('PMT-' . $p->id),
                    'reference'        => $p->reference,
                    'transaction_date' => $p->transaction_date ? Carbon::parse($p->transaction_date)->format('d/m/Y') : '-',
                    'raw_date'         => $p->transaction_date ? Carbon::parse($p->transaction_date)->format('Y-m-d') : '',
                    'amount'           => $pAmt,
                    'type'             => 'payment',
                    'mode'             => ucfirst($p->transaction_mode ?: 'Cash'),
                    'account'          => $p->ledger?->title ?? 'Cash/Bank',
                    'status'           => ucfirst($p->status ?: 'paid'),
                    'description'      => $p->description ?? '',
                    'customer_id'      => $cId,
                    'customer_name'    => $customerName,
                    'customer_code'    => $customerCode,
                ];
                $custPaymentItems[] = $pItem;
                $allPaymentsList[]  = $pItem;
            }

            $customersList[] = [
                'customer_id'         => $partner?->id ?? $cId,
                'customer_code'       => $customerCode,
                'customer_name'       => $customerName,
                'party_name'          => $customerName,
                'gstin'               => $partner?->gstin ?? '-',
                'pan_no'              => $partner?->pan_no ?? '-',
                'contact_person'      => $primaryContact?->name ?? '-',
                'phone'               => $phone,
                'email'               => $primaryContact?->email ?? '-',
                'total_invoiced'      => $totalInvoiced,
                'total_receipt'       => $totalReceipt,
                'total_payment'       => $totalPayment,
                'total_paid'          => $totalReceipt,
                'total_outstanding'   => $finalOutstanding,
                'unallocated_advance' => $unallocatedAdvance,
                'aging_0_30'          => round($aging0to30, 2),
                'aging_31_60'         => round($aging31to60, 2),
                'aging_61_90'         => round($aging61to90, 2),
                'aging_90_plus'       => round($aging90plus, 2),
                'invoices_count'      => count($custInvoiceItems),
                'open_invoices_count' => $openInvoicesCount,
                'receipts_count'      => count($custReceiptItems),
                'payments_count'      => count($custPaymentItems),
                'invoices'            => $custInvoiceItems,
                'open_invoices'       => $custOpenInvoiceItems,
                'receipts'            => $custReceiptItems,
                'payments'            => $custPaymentItems,
            ];
        }

        // Sort customers: customer_name ascending order
        $customersList = collect($customersList)->sortBy(function ($r) {
            return strtolower($r['customer_name'] ?? '');
        })->values()->all();

        // Totals
        $totalInvoiced    = round(collect($customersList)->sum('total_invoiced'), 2);
        $totalReceipt     = round(collect($customersList)->sum('total_receipt'), 2);
        $totalPayment     = round(collect($customersList)->sum('total_payment'), 2);
        $totalOutstanding = round(collect($customersList)->sum('total_outstanding'), 2);
        $totalAging0to30  = round(collect($customersList)->sum('aging_0_30'), 2);
        $totalAging31to60 = round(collect($customersList)->sum('aging_31_60'), 2);
        $totalAging61to90 = round(collect($customersList)->sum('aging_61_90'), 2);
        $totalAging90Plus = round(collect($customersList)->sum('aging_90_plus'), 2);
        $totalOpenInvCount= (int)collect($customersList)->sum('open_invoices_count');
        $customersWithBal = collect($customersList)->where('total_outstanding', '>', 0)->count();

        $plant = $plantId ? Plant::with(['addresses.state'])->whereNull('deleted_at')->find($plantId) : null;
        $patron = $patronId ? Patron::with(['addresses'])->whereNull('deleted_at')->find($patronId) : null;

        return [
            'transactions'               => $customersList,
            'items'                      => $customersList,
            'customer_summary'           => $customersList,
            'open_invoices'              => $allOpenInvoices,
            'all_receipts'               => $allReceiptsList,
            'all_payments'               => $allPaymentsList,
            'total_customers'            => count($customersList),
            'customers_with_balance'     => $customersWithBal,
            'total_invoiced_amount'      => $totalInvoiced,
            'total_receipt_amount'       => $totalReceipt,
            'total_payment_amount'       => $totalPayment,
            'total_paid_amount'          => $totalReceipt,
            'total_outstanding_amount'   => $totalOutstanding,
            'total_amount'               => $totalOutstanding,
            'aging_0_30'                 => $totalAging0to30,
            'aging_31_60'                => $totalAging31to60,
            'aging_61_90'                => $totalAging61to90,
            'aging_90_plus'              => $totalAging90Plus,
            'total_open_invoices'        => $totalOpenInvCount,
            'total_receipts_count'       => count($allReceiptsList),
            'total_payments_count'       => count($allPaymentsList),
            'plant'                      => $plant,
            'patron'                     => $patron,
            'generated_at'               => now()->format('d/m/Y h:i A'),
            'filters'                    => [
                'start'     => $start,
                'end'       => $end,
                'patron_id' => $patronId,
            ],
        ];
    }

    /**
     * Generate a complete, chronological statement of accounts for a single patron.
     */
    public function generateSinglePatronStatement(int $patronId, ?int $plantId, ?string $start, ?string $end, array $params = []): array
    {
        $plantId = $plantId ?? $params['plant_id'] ?? $this->ctx->plantId() ?? session('active_plant_id');

        $patron = Patron::with(['addresses.state', 'contacts'])->whereNull('deleted_at')->find($patronId);

        // Normalize patron address fields for both Vue and Blade views
        if ($patron && $patron->addresses) {
            foreach ($patron->addresses as $addr) {
                $addr->address_line_1 = $addr->address_line_1 ?? $addr->line_1;
                $addr->address_line_2 = $addr->address_line_2 ?? $addr->line_2;
                $addr->postal_code    = $addr->postal_code ?? $addr->zipcode;
            }
        }

        // Primary contact details
        $contacts = $patron?->contacts ?? collect();
        $primaryContact = $contacts->firstWhere('is_primary', 1) ?? $contacts->first();
        $phone = $primaryContact?->mobile ?: ($primaryContact?->alt_mobile ?: ($primaryContact?->landline ?: ($patron?->phone ?? '-')));

        // Plant details
        $plant = $plantId ? Plant::with(['addresses.state'])->whereNull('deleted_at')->find($plantId) : null;
        if ($plant && $plant->addresses) {
            foreach ($plant->addresses as $addr) {
                $addr->address_line_1 = $addr->address_line_1 ?? $addr->line_1;
                $addr->address_line_2 = $addr->address_line_2 ?? $addr->line_2;
                $addr->postal_code    = $addr->postal_code ?? $addr->zipcode;
            }
        }

        $startDateOnly = $start ? substr($start, 0, 10) : null;
        $endDateOnly   = $end ? substr($end, 0, 10) : null;

        // 1. Sales Invoices Query
        $invQuery = Invoice::query()
            ->where('invoice_type', 'sales')
            ->where('partner_id', $patronId)
            ->whereNull('deleted_at')
            ->where(function ($q) {
                $q->whereNull('status')->orWhere('status', '!=', 'Cancelled');
            });

        if ($plantId) {
            $invQuery->where('plant_id', $plantId);
        }

        // 2. Payments & Receipts Query
        $pmtQuery = Payment::query()
            ->with(['ledger:id,title'])
            ->where('patron_id', $patronId)
            ->whereNull('deleted_at')
            ->where(function ($q) {
                $q->whereNull('status')
                  ->orWhereNotIn('status', ['cancelled', 'rejected', 'failed']);
            });

        if ($plantId) {
            $pmtQuery->where('plant_id', $plantId);
        }

        // 3. Journal Entry Lines Query (non-invoice, non-payment entries: manual JVs, adjustments)
        $jelQuery = JournalEntryLine::query()
            ->with(['entry', 'ledger'])
            ->where('partner_id', $patronId)
            ->where('partner_type', 'Patron')
            ->whereNull('deleted_at')
            ->where(fn($q) => $q->where('is_deleted', 0)->orWhereNull('is_deleted'))
            ->whereHas('entry', function ($q) {
                $q->whereNull('deleted_at')
                  ->where(fn($sq) => $sq->where('is_deleted', 0)->orWhereNull('is_deleted'))
                  ->where(function ($sq) {
                      $sq->whereNull('ref_module')
                         ->orWhereNotIn('ref_module', ['invoice', 'payment']);
                  });
            });

        if ($plantId) {
            $jelQuery->where('plant_id', $plantId);
        }

        // 4. Calculate Opening Balance prior to start date
        if ($startDateOnly) {
            $openingInvoiced = (clone $invQuery)
                ->where('invoice_date', '<', $startDateOnly)
                ->sum('total_amount') ?: 0;

            $openingReceipts = (clone $pmtQuery)
                ->where('transaction_date', '<', $startDateOnly)
                ->where(function ($q) {
                    $q->where('transaction_type', 'like', '%receipt%')
                      ->orWhere('transaction_type', 'rcpt');
                })
                ->sum('amount') ?: 0;

            $openingPayments = (clone $pmtQuery)
                ->where('transaction_date', '<', $startDateOnly)
                ->where(function ($q) {
                    $q->where('transaction_type', 'payment')
                      ->orWhere('transaction_type', 'pmt');
                })
                ->sum('amount') ?: 0;

            $openingJel = (clone $jelQuery)
                ->whereHas('entry', fn($q) => $q->where('voucher_date', '<', $startDateOnly))
                ->selectRaw('SUM(debit_amount) - SUM(credit_amount) as bal')
                ->value('bal') ?: 0;

            $openingBalance = round((float)$openingInvoiced - (float)$openingReceipts + (float)$openingPayments + (float)$openingJel, 2);
        } else {
            $openingBalance = 0.00;
        }

        // 5. Fetch transactions within period
        $periodInvoices = (clone $invQuery)
            ->when($startDateOnly, fn($q) => $q->where('invoice_date', '>=', $startDateOnly))
            ->when($endDateOnly, fn($q) => $q->where('invoice_date', '<=', $endDateOnly))
            ->orderBy('invoice_date', 'asc')
            ->orderBy('id', 'asc')
            ->get();

        $periodPayments = (clone $pmtQuery)
            ->when($startDateOnly, fn($q) => $q->where('transaction_date', '>=', $startDateOnly))
            ->when($endDateOnly, fn($q) => $q->where('transaction_date', '<=', $endDateOnly))
            ->orderBy('transaction_date', 'asc')
            ->orderBy('id', 'asc')
            ->get();

        $periodJel = (clone $jelQuery)
            ->when($startDateOnly, fn($q) => $q->whereHas('entry', fn($sq) => $sq->where('voucher_date', '>=', $startDateOnly)))
            ->when($endDateOnly, fn($q) => $q->whereHas('entry', fn($sq) => $sq->where('voucher_date', '<=', $endDateOnly)))
            ->get();

        // 6. Compute Account Summary Totals
        $invoicedTaxTotal    = 0.0;
        $invoicedNonTaxTotal = 0.0;
        $salesDiscount       = 0.0;

        foreach ($periodInvoices as $inv) {
            $invTotal = (float)($inv->total_amount ?? 0);
            if ((float)($inv->tax_amount ?? 0) > 0) {
                $invoicedTaxTotal += $invTotal;
            } else {
                $invoicedNonTaxTotal += $invTotal;
            }
            $salesDiscount += (float)($inv->discount_amount ?? 0);
        }

        $totalInvoiced = round($invoicedTaxTotal + $invoicedNonTaxTotal, 2);

        $receiptsList = $periodPayments->filter(function ($p) {
            $type = strtolower($p->transaction_type ?? '');
            return str_contains($type, 'receipt') || in_array($type, ['receipt', 'rcpt']);
        });

        $paymentsList = $periodPayments->filter(function ($p) {
            $type = strtolower($p->transaction_type ?? '');
            return !str_contains($type, 'receipt') && in_array($type, ['payment', 'pmt']);
        });

        $amountReceived = round((float)$receiptsList->sum('amount'), 2);
        $amountPaid     = round((float)$paymentsList->sum('amount'), 2);

        // Purchases (if customer also acts as vendor)
        $purchased = round((float)PurchaseOrder::where('vendor_id', $patronId)
            ->when($plantId, fn($q) => $q->where('plant_id', $plantId))
            ->whereNull('deleted_at')
            ->when($startDateOnly, fn($q) => $q->where('date_order', '>=', $startDateOnly))
            ->when($endDateOnly, fn($q) => $q->where('date_order', '<=', $endDateOnly))
            ->sum('amount_total'), 2);

        $credits = round((float)$periodJel->sum('credit_amount'), 2);

        // 7. Assemble Unified Chronological Items
        $items = collect();

        foreach ($periodInvoices as $inv) {
            $invTotal = round((float)($inv->total_amount ?? 0), 2);
            $invNum   = $inv->full_number ?: ('INV-' . $inv->id);
            $details  = $invNum;
            if (!empty($inv->remarks)) {
                $details .= "\n" . $inv->remarks;
            }

            $items->push([
                'timestamp'    => $inv->invoice_date ? Carbon::parse($inv->invoice_date)->timestamp : 0,
                'raw_date'     => $inv->invoice_date ? Carbon::parse($inv->invoice_date)->format('Y-m-d') : '',
                'date'         => $inv->invoice_date ? Carbon::parse($inv->invoice_date)->format('d-m-Y') : '-',
                'sort_order'   => 1,
                'id'           => $inv->id,
                'transactions' => 'Sales Invoice',
                'narration'    => 'Invoice ' . $invNum,
                'details'      => $details,
                'type'         => 'INV',
                'voucher_type' => 'SALES',
                'voucher_no'   => $invNum,
                'debit'        => $invTotal,
                'credit'       => 0.0,
                'discount'     => (float)($inv->discount_amount ?? 0),
            ]);
        }

        foreach ($receiptsList as $p) {
            $pAmt    = round((float)($p->amount ?? 0), 2);
            $rcptNum = $p->reference ?: ('RCPT-' . $p->id);
            $details = $rcptNum;
            if ($p->ledger?->title) {
                $details .= "\n" . $p->ledger->title;
            }
            if ($p->transaction_mode) {
                $details .= " (" . ucfirst($p->transaction_mode) . ")";
            }
            if (!empty($p->description)) {
                $details .= "\n" . $p->description;
            }

            $items->push([
                'timestamp'    => $p->transaction_date ? Carbon::parse($p->transaction_date)->timestamp : 0,
                'raw_date'     => $p->transaction_date ? Carbon::parse($p->transaction_date)->format('Y-m-d') : '',
                'date'         => $p->transaction_date ? Carbon::parse($p->transaction_date)->format('d-m-Y') : '-',
                'sort_order'   => 2,
                'id'           => $p->id,
                'transactions' => 'Payment Received',
                'narration'    => 'Receipt ' . $rcptNum,
                'details'      => $details,
                'type'         => 'RCPT',
                'voucher_type' => 'RECEIPT',
                'voucher_no'   => $rcptNum,
                'debit'        => 0.0,
                'credit'       => $pAmt,
                'discount'     => 0.0,
            ]);
        }

        foreach ($paymentsList as $p) {
            $pAmt    = round((float)($p->amount ?? 0), 2);
            $pmtNum  = $p->reference ?: ('PMT-' . $p->id);
            $details = $pmtNum;
            if ($p->ledger?->title) {
                $details .= "\n" . $p->ledger->title;
            }
            if (!empty($p->description)) {
                $details .= "\n" . $p->description;
            }

            $items->push([
                'timestamp'    => $p->transaction_date ? Carbon::parse($p->transaction_date)->timestamp : 0,
                'raw_date'     => $p->transaction_date ? Carbon::parse($p->transaction_date)->format('Y-m-d') : '',
                'date'         => $p->transaction_date ? Carbon::parse($p->transaction_date)->format('d-m-Y') : '-',
                'sort_order'   => 3,
                'id'           => $p->id,
                'transactions' => 'Payment Made',
                'narration'    => 'Payment ' . $pmtNum,
                'details'      => $details,
                'type'         => 'PMT',
                'voucher_type' => 'PAYMENT',
                'voucher_no'   => $pmtNum,
                'debit'        => $pAmt,
                'credit'       => 0.0,
                'discount'     => 0.0,
            ]);
        }

        foreach ($periodJel as $line) {
            $dr      = round((float)($line->debit_amount ?? 0), 2);
            $cr      = round((float)($line->credit_amount ?? 0), 2);
            $vNum    = $line->entry?->voucher_number ?: ('JV-' . $line->id);
            $vType   = strtoupper($line->entry?->voucher_type ?? 'JOURNAL');
            $label   = match (true) {
                str_contains($vType, 'CREDIT') => 'Credit Note',
                str_contains($vType, 'DEBIT')  => 'Debit Note',
                default                        => 'Journal Voucher',
            };
            $details = $vNum . "\n" . ($line->line_narration ?: $line->entry?->narration ?: $label);
            $vDate   = $line->entry?->voucher_date;

            $items->push([
                'timestamp'    => $vDate ? Carbon::parse($vDate)->timestamp : 0,
                'raw_date'     => $vDate ? Carbon::parse($vDate)->format('Y-m-d') : '',
                'date'         => $vDate ? Carbon::parse($vDate)->format('d-m-Y') : '-',
                'sort_order'   => 4,
                'id'           => $line->id,
                'transactions' => $label,
                'narration'    => $vType . ' ' . $vNum,
                'details'      => $details,
                'type'         => $vType,
                'voucher_type' => $vType,
                'voucher_no'   => $vNum,
                'debit'        => $dr,
                'credit'       => $cr,
                'discount'     => 0.0,
            ]);
        }

        // Sort items by raw_date asc, then sort_order asc, then id asc
        $sortedItems = $items->sort(function ($a, $b) {
            if ($a['raw_date'] !== $b['raw_date']) {
                return strcmp($a['raw_date'], $b['raw_date']);
            }
            if ($a['sort_order'] !== $b['sort_order']) {
                return $a['sort_order'] <=> $b['sort_order'];
            }
            return $a['id'] <=> $b['id'];
        })->values();

        // 8. Build Running Balance Rows
        $runningBalance = $openingBalance;
        $rows = [];

        // Row 1: Opening Balance Row
        $rows[] = [
            's_no'                    => 1,
            'date'                    => $startDateOnly ? Carbon::parse($startDateOnly)->format('d-m-Y') : '-',
            'raw_date'                => $startDateOnly ?: '',
            'transactions'            => 'Opening Balance',
            'narration'               => 'Opening Balance',
            'details'                 => 'Opening Balance',
            'type'                    => '-',
            'voucher_type'            => 'OPENING',
            'voucher_no'              => '---',
            'invoice_bill_display'    => '0',
            'receipt_payment_display' => '0',
            'discount_display'        => '0',
            'balance_display'         => ($openingBalance != 0 ? ($openingBalance > 0 ? 'Dr ' : 'Cr ') : '') . '₹ ' . number_format(abs($openingBalance), 2),
            'balance_type'            => $openingBalance >= 0 ? 'Dr' : 'Cr',
            'balance'                 => $openingBalance,
            'debit'                   => 0.0,
            'credit'                  => 0.0,
            'is_opening'              => true,
        ];

        $sNo = 2;
        foreach ($sortedItems as $it) {
            $debit  = (float)$it['debit'];
            $credit = (float)$it['credit'];
            $disc   = (float)$it['discount'];

            $runningBalance = round($runningBalance + $debit - $credit, 2);

            $rows[] = [
                's_no'                    => $sNo++,
                'id'                      => $it['id'],
                'date'                    => $it['date'],
                'raw_date'                => $it['raw_date'],
                'transactions'            => $it['transactions'],
                'narration'               => $it['narration'],
                'details'                 => $it['details'],
                'type'                    => $it['type'],
                'voucher_type'            => $it['voucher_type'],
                'voucher_no'              => $it['voucher_no'],
                'invoice_bill_display'    => $debit > 0 ? '₹ ' . number_format($debit, 2) : '0',
                'receipt_payment_display' => $credit > 0 ? '(₹ ' . number_format($credit, 2) . ')' : '0',
                'discount_display'        => $disc > 0 ? '₹ ' . number_format($disc, 2) : '0',
                'balance_display'         => ($runningBalance != 0 ? ($runningBalance > 0 ? 'Dr ' : 'Cr ') : '') . '₹ ' . number_format(abs($runningBalance), 2),
                'balance_type'            => $runningBalance >= 0 ? 'Dr' : 'Cr',
                'balance'                 => $runningBalance,
                'debit'                   => $debit,
                'credit'                  => $credit,
                'is_opening'              => false,
            ];
        }

        $closingBalance = $runningBalance;

        $accountSummary = [
            'opening_balance'         => (float)$openingBalance,
            'opening_balance_display' => ($openingBalance != 0 ? ($openingBalance > 0 ? 'Dr ' : 'Cr ') : '') . '₹ ' . number_format(abs($openingBalance), 2),
            'invoiced_tax'            => (float)$invoicedTaxTotal,
            'invoiced_tax_display'    => $invoicedTaxTotal > 0 ? '₹ ' . number_format($invoicedTaxTotal, 2) : '0',
            'invoiced_nontax'         => (float)$invoicedNonTaxTotal,
            'invoiced_nontax_display' => $invoicedNonTaxTotal > 0 ? '₹ ' . number_format($invoicedNonTaxTotal, 2) : '0',
            'total_invoiced'          => (float)$totalInvoiced,
            'total_invoiced_display'  => $totalInvoiced > 0 ? '₹ ' . number_format($totalInvoiced, 2) : '0',
            'sales_discount'          => (float)$salesDiscount,
            'sales_discount_display'  => $salesDiscount > 0 ? '₹ ' . number_format($salesDiscount, 2) : '0',
            'purchased'               => (float)$purchased,
            'purchased_display'       => $purchased > 0 ? '₹ ' . number_format($purchased, 2) : '0',
            'amount_received'         => (float)$amountReceived,
            'amount_received_display' => $amountReceived > 0 ? '₹ ' . number_format($amountReceived, 2) : '0',
            'amount_paid'             => (float)$amountPaid,
            'amount_paid_display'     => $amountPaid > 0 ? '₹ ' . number_format($amountPaid, 2) : '0',
            'credits'                 => (float)$credits,
            'credits_display'         => $credits > 0 ? '₹ ' . number_format($credits, 2) : '0',
            'balance_due'             => (float)$closingBalance,
            'balance_due_display'     => ($closingBalance != 0 ? ($closingBalance > 0 ? 'Dr ' : 'Cr ') : '') . '₹ ' . number_format(abs($closingBalance), 2),
            'balance_due_type'        => $closingBalance >= 0 ? 'Dr' : 'Cr',
        ];

        $balanceDueDisplay = ($closingBalance != 0 ? ($closingBalance > 0 ? 'Dr ' : 'Cr ') : '') . '₹ ' . number_format(abs($closingBalance), 2);

        return [
            'is_single_patron'     => true,
            'plant'                => $plant,
            'patron'               => $patron,
            'phone'                => $phone,
            'start'                => $start,
            'end'                  => $end,
            'start_formatted'      => $startDateOnly ? Carbon::parse($startDateOnly)->format('d-m-Y') : '',
            'end_formatted'        => $endDateOnly ? Carbon::parse($endDateOnly)->format('d-m-Y') : '',
            'transactions'         => $rows,
            'ledger_transactions'  => $rows,
            'account_summary'      => $accountSummary,
            'balance_due_display'  => $balanceDueDisplay,
            'balance_due'          => (float)$closingBalance,
            'opening_balance'      => (float)$openingBalance,
            'total_amount'         => (float)$closingBalance,
            'generated_at'         => now()->format('d/m/Y h:i A'),
            'filters'              => $params,
        ];
    }

    public function targetName(array $params): string
    {
        return isset($params['patron_id']) && $params['patron_id']
            ? (Patron::whereNull('deleted_at')->find($params['patron_id'])?->legal_name ?? 'Customer Outstanding Report')
            : 'All Customers Outstanding Report';
    }
}
