<?php

namespace App\Ai\Tools;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use Stringable;
use Illuminate\Support\Facades\DB;

class Modormc implements Tool
{
    /**
     * Get the description of the tool's purpose.
     */
    public function description(): Stringable|string
    {
        return 'Query and calculate Modor RMC database facts: products, accounting ledgers, journal entries, sales summaries, purchase order totals, dispatch quantities, batch run sizes, driver metrics, customer/patron reports, invoice aggregates, uninvoiced ticket counts, transport freight fees, operational expenses, current stock quantities, and today\'s sales/purchase aggregates.';
    }

    /**
     * Declare the parameters/schema the tool expects from the AI.
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'action' => $schema->string()->enum([
                'get_products',
                'get_ledgers',
                'get_ledger_balance',
                'get_journal_entries',
                'get_sales_summary',
                'get_purchase_summary',
                'get_dispatches',
                'get_batches',
                'get_driver_details',
                'get_patron_report',
                'get_invoice_summary',
                'get_transport_expenses',
                'get_expenses_summary',
                'get_current_stock',
                'get_today_summary'
            ])->required()->description('The specific accounting or operational query action to run'),
            'date_from' => $schema->string()->description('Filter starting date (format: YYYY-MM-DD)'),
            'date_to' => $schema->string()->description('Filter ending date (format: YYYY-MM-DD)'),
            'patron_id' => $schema->integer()->description('Specific Customer, Vendor, or Transporter Patron ID'),
            'driver_id' => $schema->integer()->description('Specific Driver Personnel ID'),
            'ledger_id' => $schema->integer()->description('Specific Ledger Account ID'),
            'product_id' => $schema->integer()->description('Specific Product ID'),
            'search' => $schema->string()->description('Text query to search names, titles, codes, or narrations'),
            'limit' => $schema->integer()->description('Maximum number of records to return (defaults to 20)'),
        ];
    }

    /**
     * Safe parameter retrieval helper using ArrayAccess
     */
    protected function getParam(Request $request, string $key, mixed $default = null): mixed
    {
        return isset($request[$key]) ? $request[$key] : $default;
    }

    /**
     * Execute the tool logic with parameters supplied by the AI.
     */
    public function handle(Request $request): Stringable|string
    {
        $action = $this->getParam($request, 'action');
        $limit = (int) $this->getParam($request, 'limit', 20);
        if ($limit <= 0) $limit = 20;

        try {
            switch ($action) {
                case 'get_products':
                    return $this->getProducts($request, $limit);

                case 'get_ledgers':
                    return $this->getLedgers($request, $limit);

                case 'get_ledger_balance':
                    return $this->getLedgerBalance($request);

                case 'get_journal_entries':
                    return $this->getJournalEntries($request, $limit);

                case 'get_sales_summary':
                    return $this->getSalesSummary($request);

                case 'get_purchase_summary':
                    return $this->getPurchaseSummary($request);

                case 'get_dispatches':
                    return $this->getDispatches($request, $limit);

                case 'get_batches':
                    return $this->getBatches($request, $limit);

                case 'get_driver_details':
                    return $this->getDriverDetails($request);

                case 'get_patron_report':
                    return $this->getPatronReport($request, $limit);

                case 'get_invoice_summary':
                    return $this->getInvoiceSummary($request);

                case 'get_transport_expenses':
                    return $this->getTransportExpenses($request);

                case 'get_expenses_summary':
                    return $this->getExpensesSummary($request, $limit);

                case 'get_current_stock':
                    return $this->getCurrentStock($request);

                case 'get_today_summary':
                    return $this->getTodaySummary($request);

                default:
                    return "Error: Invalid action '{$action}' requested.";
            }
        } catch (\Exception $e) {
            return "Exception occurred while executing action '{$action}': " . $e->getMessage();
        }
    }

    protected function getProducts(Request $request, int $limit): string
    {
        $query = \App\Models\Product::with(['category', 'unit'])->where('status', 1);

        if ($this->getParam($request, 'product_id')) {
            $query->where('id', $this->getParam($request, 'product_id'));
        }

        if ($search = $this->getParam($request, 'search')) {
            $query->where(function ($q) use ($search) {
                $q->where('title', 'like', "%{$search}%")
                  ->orWhere('code', 'like', "%{$search}%")
                  ->orWhere('product_type', 'like', "%{$search}%");
            });
        }

        $products = $query->limit($limit)->get()->map(function ($p) {
            return [
                'id' => $p->id,
                'title' => $p->title,
                'code' => $p->code,
                'category' => $p->category?->name,
                'unit' => $p->unit?->name,
                'sales_price' => (float) $p->sales_price,
                'purchase_price' => (float) $p->purchase_price,
                'product_type' => $p->product_type,
            ];
        });

        return json_encode($products);
    }

    protected function getLedgers(Request $request, int $limit): string
    {
        $query = \App\Models\Ledger::with('accountType');

        if ($this->getParam($request, 'ledger_id')) {
            $query->where('id', $this->getParam($request, 'ledger_id'));
        }

        if ($search = $this->getParam($request, 'search')) {
            $query->where(function ($q) use ($search) {
                $q->where('title', 'like', "%{$search}%")
                  ->orWhere('code', 'like', "%{$search}%");
            });
        }

        $ledgers = $query->limit($limit)->get()->map(function ($l) {
            return [
                'id' => $l->id,
                'code' => $l->code,
                'title' => $l->title,
                'account_type' => $l->accountType?->name,
                'is_pnl' => (bool) $l->is_pnl,
                'notes' => $l->notes,
            ];
        });

        return json_encode($ledgers);
    }

    protected function getLedgerBalance(Request $request): string
    {
        $ledgerId = $this->getParam($request, 'ledger_id');
        if (!$ledgerId) {
            return "Error: ledger_id is required to fetch ledger balance.";
        }

        $ledger = \App\Models\Ledger::where('id', $ledgerId)->first();
        if (!$ledger) {
            return "Error: Ledger with ID {$ledgerId} not found.";
        }

        $lineTotals = DB::table('mm_journal_entry_lines')
            ->where('account_id', $ledgerId)
            ->where('is_deleted', 0)
            ->selectRaw('COALESCE(SUM(debit_amount), 0) as total_debit, COALESCE(SUM(credit_amount), 0) as total_credit')
            ->first();

        $balance = $lineTotals->total_debit - $lineTotals->total_credit;

        return json_encode([
            'ledger_id' => $ledger->id,
            'code' => $ledger->code,
            'title' => $ledger->title,
            'total_debit' => (float) $lineTotals->total_debit,
            'total_credit' => (float) $lineTotals->total_credit,
            'net_balance' => (float) $balance,
            'balance_type' => $balance >= 0 ? 'Debit' : 'Credit',
        ]);
    }

    protected function getJournalEntries(Request $request, int $limit): string
    {
        $query = \App\Models\JournalEntry::with('lines.ledger');

        if ($this->getParam($request, 'date_from')) {
            $query->where('posting_date', '>=', $this->getParam($request, 'date_from'));
        }
        if ($this->getParam($request, 'date_to')) {
            $query->where('posting_date', '<=', $this->getParam($request, 'date_to'));
        }

        $entries = $query->orderBy('posting_date', 'desc')->limit($limit)->get()->map(function ($e) {
            return [
                'id' => $e->id,
                'voucher_type' => $e->voucher_type,
                'voucher_number' => $e->voucher_number,
                'voucher_date' => $e->voucher_date?->toDateString(),
                'narration' => $e->narration,
                'total_debit' => (float) $e->total_debit,
                'total_credit' => (float) $e->total_credit,
                'lines' => $e->lines->map(function ($line) {
                    return [
                        'ledger' => $line->ledger?->title,
                        'debit' => (float) $line->debit_amount,
                        'credit' => (float) $line->credit_amount,
                        'line_narration' => $line->line_narration,
                    ];
                })
            ];
        });

        return json_encode($entries);
    }

    protected function getSalesSummary(Request $request): string
    {
        $dateFrom = $this->getParam($request, 'date_from');
        $dateTo   = $this->getParam($request, 'date_to');

        // Dispatch concrete sales
        $dispatchQuery = DB::table('mm_dispatches')->whereNull('deleted_at');
        if ($dateFrom) $dispatchQuery->where('dispatch_time', '>=', $dateFrom);
        if ($dateTo)   $dispatchQuery->where('dispatch_time', '<=', $dateTo . ' 23:59:59');

        $dispatchStats = $dispatchQuery->selectRaw('
            COUNT(*) as total_dispatches,
            COALESCE(SUM(delivered_qty), 0) as total_qty_m3,
            COALESCE(SUM(load_total_amount), 0) as total_amount
        ')->first();

        // Billed Sales Invoices
        $invoiceQuery = DB::table('mm_invoices')->where('invoice_type', 'sales')->whereNull('deleted_at');
        if ($dateFrom) $invoiceQuery->where('invoice_date', '>=', $dateFrom);
        if ($dateTo)   $invoiceQuery->where('invoice_date', '<=', $dateTo);

        $invoiceStats = $invoiceQuery->selectRaw('
            COUNT(*) as total_invoices,
            COALESCE(SUM(total_amount), 0) as total_amount,
            COALESCE(SUM(paid_amount), 0) as total_paid,
            COALESCE(SUM(balance_amount), 0) as total_balance
        ')->first();

        return json_encode([
            'period' => ['from' => $dateFrom, 'to' => $dateTo],
            'dispatched_sales' => [
                'count' => $dispatchStats->total_dispatches,
                'quantity_m3' => (float) $dispatchStats->total_qty_m3,
                'amount' => (float) $dispatchStats->total_amount,
            ],
            'billed_sales' => [
                'count' => $invoiceStats->total_invoices,
                'amount' => (float) $invoiceStats->total_amount,
                'paid' => (float) $invoiceStats->total_paid,
                'outstanding_balance' => (float) $invoiceStats->total_balance,
            ]
        ]);
    }

    protected function getPurchaseSummary(Request $request): string
    {
        $dateFrom = $this->getParam($request, 'date_from');
        $dateTo   = $this->getParam($request, 'date_to');

        $poQuery = DB::table('mm_purchase_orders')->whereNull('deleted_at');
        if ($dateFrom) $poQuery->where('date_order', '>=', $dateFrom);
        if ($dateTo)   $poQuery->where('date_order', '<=', $dateTo);

        $poStats = $poQuery->selectRaw('
            COUNT(*) as total_orders,
            COALESCE(SUM(amount_total), 0) as total_amount,
            COALESCE(SUM(amount_untaxed), 0) as total_untaxed,
            COALESCE(SUM(amount_tax), 0) as total_tax
        ')->first();

        $itemsQuery = DB::table('mm_purchase_order_items')
            ->join('mm_purchase_orders', 'mm_purchase_order_items.order_id', '=', 'mm_purchase_orders.id')
            ->whereNull('mm_purchase_orders.deleted_at')
            ->whereNull('mm_purchase_order_items.deleted_at');
        if ($dateFrom) $itemsQuery->where('mm_purchase_orders.date_order', '>=', $dateFrom);
        if ($dateTo)   $itemsQuery->where('mm_purchase_orders.date_order', '<=', $dateTo);

        $itemsStats = $itemsQuery->selectRaw('
            COALESCE(SUM(product_quantity), 0) as total_qty,
            COALESCE(SUM(received_quantity), 0) as total_received_qty
        ')->first();

        return json_encode([
            'period' => ['from' => $dateFrom, 'to' => $dateTo],
            'purchase_orders' => [
                'count' => $poStats->total_orders,
                'total_amount' => (float) $poStats->total_amount,
                'total_untaxed' => (float) $poStats->total_untaxed,
                'total_tax' => (float) $poStats->total_tax,
                'total_quantity' => (float) $itemsStats->total_qty,
                'total_received_quantity' => (float) $itemsStats->total_received_qty,
            ]
        ]);
    }

    protected function getDispatches(Request $request, int $limit): string
    {
        $dateFrom = $this->getParam($request, 'date_from');
        $dateTo   = $this->getParam($request, 'date_to');
        $patronId = $this->getParam($request, 'patron_id');

        $query = \App\Models\Dispatch::with(['customer', 'driver', 'truck', 'mixDesign'])
            ->whereNull('deleted_at');

        if ($dateFrom)  $query->where('dispatch_time', '>=', $dateFrom);
        if ($dateTo)    $query->where('dispatch_time', '<=', $dateTo . ' 23:59:59');
        if ($patronId)  $query->where('customer_id', $patronId);

        if ($search = $this->getParam($request, 'search')) {
            $query->where(function ($q) use ($search) {
                $q->where('dispatch_no', 'like', "%{$search}%")
                  ->orWhereHas('customer', function ($sub) use ($search) {
                      $sub->where('legal_name', 'like', "%{$search}%");
                  });
            });
        }

        $dispatches = $query->orderBy('dispatch_time', 'desc')->limit($limit)->get()->map(function ($d) {
            return [
                'id' => $d->id,
                'dispatch_no' => $d->dispatch_no,
                'dispatch_time' => $d->dispatch_time?->toDateTimeString(),
                'customer' => $d->customer?->legal_name,
                'mix_design' => $d->mixDesign?->design_name,
                'delivered_qty' => (float) $d->delivered_qty,
                'load_rate' => (float) $d->load_rate,
                'load_total_amount' => (float) $d->load_total_amount,
                'transport_expenses' => (float) $d->transport_expenses,
                'driver' => $d->driver?->first_name . ' ' . $d->driver?->last_name,
                'truck' => $d->truck?->name ?: $d->truck?->plate_number,
            ];
        });

        return json_encode($dispatches);
    }

    protected function getBatches(Request $request, int $limit): string
    {
        $dateFrom = $this->getParam($request, 'date_from');
        $dateTo   = $this->getParam($request, 'date_to');

        $query = \App\Models\Batch::with(['operator', 'workOrder'])->whereNull('deleted_at');

        if ($dateFrom) $query->where('start_time', '>=', $dateFrom);
        if ($dateTo)   $query->where('start_time', '<=', $dateTo . ' 23:59:59');

        $batches = $query->orderBy('start_time', 'desc')->limit($limit)->get()->map(function ($b) {
            return [
                'id' => $b->id,
                'batch_no' => $b->batch_no,
                'batch_size' => (float) $b->batch_size,
                'start_time' => $b->start_time?->toDateTimeString(),
                'end_time' => $b->end_time?->toDateTimeString(),
                'operator' => $b->operator?->first_name . ' ' . $b->operator?->last_name,
                'work_order_no' => $b->workOrder?->work_order_no,
            ];
        });

        return json_encode($batches);
    }

    protected function getDriverDetails(Request $request): string
    {
        $driverId = $this->getParam($request, 'driver_id');
        $query = \App\Models\Driver::with(['personnel']);

        if ($driverId) {
            $query->where('id', $driverId);
        }

        $drivers = $query->get()->map(function ($d) {
            $dispatchQuery = DB::table('mm_dispatches')
                ->where('driver_id', $d->personnel_id)
                ->whereNull('deleted_at');

            return [
                'id' => $d->id,
                'name' => $d->personnel ? ($d->personnel->first_name . ' ' . $d->personnel->last_name) : 'Unknown Personnel',
                'personnel_id' => $d->personnel_id,
                'license_number' => $d->license_number,
                'license_expiry' => $d->license_expiry_date?->toDateString(),
                'license_type' => $d->license_type,
                'status' => $d->status,
                'completed_dispatches_count' => (clone $dispatchQuery)->count(),
                'total_delivered_qty_m3' => (float) (clone $dispatchQuery)->sum('delivered_qty'),
            ];
        });

        return json_encode($drivers);
    }

    protected function getPatronReport(Request $request, int $limit): string
    {
        $patronId = $this->getParam($request, 'patron_id');
        $query = \App\Models\Patron::with(['ledger']);

        if ($patronId) $query->where('id', $patronId);

        if ($search = $this->getParam($request, 'search')) {
            $query->where(function ($q) use ($search) {
                $q->where('legal_name', 'like', "%{$search}%")
                  ->orWhere('code', 'like', "%{$search}%");
            });
        }

        $patrons = $query->limit($limit)->get()->map(function ($p) {
            $invoiceQuery = DB::table('mm_invoices')
                ->where('partner_id', $p->id)
                ->whereNull('deleted_at');

            $invoiceStats = $invoiceQuery->selectRaw('
                COALESCE(SUM(total_amount), 0) as total_billed,
                COALESCE(SUM(paid_amount), 0) as total_paid,
                COALESCE(SUM(balance_amount), 0) as outstanding_balance
            ')->first();

            $ledgerBalance = 0.0;
            if ($p->ledger_id) {
                $ledgerTotals = DB::table('mm_journal_entry_lines')
                    ->where('account_id', $p->ledger_id)
                    ->where('is_deleted', 0)
                    ->selectRaw('COALESCE(SUM(debit_amount), 0) as total_debit, COALESCE(SUM(credit_amount), 0) as total_credit')
                    ->first();
                $ledgerBalance = $ledgerTotals->total_debit - $ledgerTotals->total_credit;
            }

            return [
                'id' => $p->id,
                'code' => $p->code,
                'legal_name' => $p->legal_name,
                'types' => $p->patron_type,
                'gstin' => $p->gstin,
                'pan_no' => $p->pan_no,
                'total_billed' => (float) $invoiceStats->total_billed,
                'total_paid' => (float) $invoiceStats->total_paid,
                'invoice_outstanding' => (float) $invoiceStats->outstanding_balance,
                'ledger_id' => $p->ledger_id,
                'ledger_balance' => (float) $ledgerBalance,
            ];
        });

        return json_encode($patrons);
    }

    protected function getInvoiceSummary(Request $request): string
    {
        $dateFrom = $this->getParam($request, 'date_from');
        $dateTo   = $this->getParam($request, 'date_to');

        $salesQuery = DB::table('mm_invoices')->where('invoice_type', 'sales')->whereNull('deleted_at');
        if ($dateFrom) $salesQuery->where('invoice_date', '>=', $dateFrom);
        if ($dateTo)   $salesQuery->where('invoice_date', '<=', $dateTo);

        $salesStats = $salesQuery->selectRaw("
            COUNT(*) as count,
            COALESCE(SUM(CASE WHEN status = 'draft' THEN total_amount ELSE 0 END), 0) as draft_amount,
            COALESCE(SUM(CASE WHEN status = 'approved' THEN total_amount ELSE 0 END), 0) as approved_amount,
            COALESCE(SUM(CASE WHEN status = 'paid' THEN total_amount ELSE 0 END), 0) as paid_amount,
            COALESCE(SUM(total_amount), 0) as total_amount,
            COALESCE(SUM(paid_amount), 0) as actual_paid,
            COALESCE(SUM(balance_amount), 0) as outstanding_balance
        ")->first();

        $billQuery = DB::table('mm_invoices')->where('invoice_type', 'bill')->whereNull('deleted_at');
        if ($dateFrom) $billQuery->where('invoice_date', '>=', $dateFrom);
        if ($dateTo)   $billQuery->where('invoice_date', '<=', $dateTo);

        $billStats = $billQuery->selectRaw('
            COUNT(*) as count,
            COALESCE(SUM(total_amount), 0) as total_amount,
            COALESCE(SUM(paid_amount), 0) as actual_paid,
            COALESCE(SUM(balance_amount), 0) as outstanding_balance
        ')->first();

        // Uninvoiced Dispatches
        $uninvoicedQuery = DB::table('mm_dispatch_statuses')
            ->join('mm_dispatches', 'mm_dispatch_statuses.dispatch_id', '=', 'mm_dispatches.id')
            ->whereNull('mm_dispatches.deleted_at')
            ->where(function ($q) {
                $q->whereNull('mm_dispatch_statuses.invoice_id')
                  ->orWhere('mm_dispatch_statuses.invoice_status', 0);
            });
        if ($dateFrom) $uninvoicedQuery->where('mm_dispatches.dispatch_time', '>=', $dateFrom);
        if ($dateTo)   $uninvoicedQuery->where('mm_dispatches.dispatch_time', '<=', $dateTo . ' 23:59:59');

        $uninvoicedCount = $uninvoicedQuery->count();
        $uninvoicedQty   = $uninvoicedQuery->sum('mm_dispatches.delivered_qty');

        return json_encode([
            'period' => ['from' => $dateFrom, 'to' => $dateTo],
            'sales_invoices' => [
                'total_count' => $salesStats->count,
                'total_amount' => (float) $salesStats->total_amount,
                'draft_amount' => (float) $salesStats->draft_amount,
                'approved_amount' => (float) $salesStats->approved_amount,
                'paid_amount' => (float) $salesStats->paid_amount,
                'collected_amount' => (float) $salesStats->actual_paid,
                'outstanding_balance' => (float) $salesStats->outstanding_balance,
            ],
            'purchase_bills' => [
                'total_count' => $billStats->count,
                'total_amount' => (float) $billStats->total_amount,
                'paid_amount' => (float) $billStats->actual_paid,
                'outstanding_balance' => (float) $billStats->outstanding_balance,
            ],
            'uninvoiced_dispatches' => [
                'count' => $uninvoicedCount,
                'delivered_qty_m3' => (float) $uninvoicedQty,
            ]
        ]);
    }

    protected function getTransportExpenses(Request $request): string
    {
        $dateFrom = $this->getParam($request, 'date_from');
        $dateTo   = $this->getParam($request, 'date_to');

        $dispatchFinancialsQuery = DB::table('mm_dispatch_financials')
            ->join('mm_dispatches', 'mm_dispatch_financials.dispatch_id', '=', 'mm_dispatches.id')
            ->whereNull('mm_dispatches.deleted_at');
        if ($dateFrom) $dispatchFinancialsQuery->where('mm_dispatches.dispatch_time', '>=', $dateFrom);
        if ($dateTo)   $dispatchFinancialsQuery->where('mm_dispatches.dispatch_time', '<=', $dateTo . ' 23:59:59');

        $dispatchTransportStats = $dispatchFinancialsQuery->selectRaw('
            COALESCE(SUM(transport_amount), 0) as total_transport_base,
            COALESCE(SUM(transport_total_amount), 0) as total_transport_with_tax,
            COALESCE(SUM(transport_expenses), 0) as total_transport_expenses
        ')->first();

        $tripFinancialsQuery = DB::table('mm_trip_financials')
            ->join('mm_trips', 'mm_trip_financials.trip_id', '=', 'mm_trips.id')
            ->whereNull('mm_trips.deleted_at');
        if ($dateFrom) $tripFinancialsQuery->where('mm_trips.created_at', '>=', $dateFrom);
        if ($dateTo)   $tripFinancialsQuery->where('mm_trips.created_at', '<=', $dateTo . ' 23:59:59');

        $tripTransportStats = $tripFinancialsQuery->selectRaw('
            COALESCE(SUM(transport_rate * COALESCE(transport_unit, 1)), 0) as total_trip_transport_base,
            COALESCE(SUM(transport_expenses), 0) as total_trip_transport_expenses
        ')->first();

        return json_encode([
            'period' => ['from' => $dateFrom, 'to' => $dateTo],
            'dispatch_transport_financials' => [
                'base_freight_amount' => (float) $dispatchTransportStats->total_transport_base,
                'tax_freight_amount' => (float) ($dispatchTransportStats->total_transport_with_tax - $dispatchTransportStats->total_transport_base),
                'total_freight_amount' => (float) $dispatchTransportStats->total_transport_with_tax,
                'extra_freight_expenses' => (float) $dispatchTransportStats->total_transport_expenses,
            ],
            'trip_transport_financials' => [
                'base_transport_amount' => (float) $tripTransportStats->total_trip_transport_base,
                'extra_transport_expenses' => (float) $tripTransportStats->total_trip_transport_expenses,
            ]
        ]);
    }

    protected function getExpensesSummary(Request $request, int $limit): string
    {
        $dateFrom = $this->getParam($request, 'date_from');
        $dateTo   = $this->getParam($request, 'date_to');

        $query = \App\Models\Expense::with(['expenseType', 'vendor'])->whereNull('deleted_at');
        if ($dateFrom) $query->where('date', '>=', $dateFrom);
        if ($dateTo)   $query->where('date', '<=', $dateTo);

        if ($search = $this->getParam($request, 'search')) {
            $query->where(function ($q) use ($search) {
                $q->where('ref_no', 'like', "%{$search}%")
                  ->orWhere('note', 'like', "%{$search}%")
                  ->orWhereHas('expenseType', function ($sub) use ($search) {
                      $sub->where('name', 'like', "%{$search}%");
                  });
            });
        }

        $expenses = $query->orderBy('date', 'desc')->limit($limit)->get();
        $expensesList = $expenses->map(function ($ex) {
            return [
                'id' => $ex->id,
                'ref_no' => $ex->ref_no,
                'amount' => (float) $ex->amount,
                'date' => $ex->date?->toDateString(),
                'expense_type' => $ex->expenseType?->name,
                'vendor' => $ex->vendor?->legal_name,
                'note' => $ex->note,
            ];
        });

        $groupQuery = DB::table('mm_expenses')
            ->join('mm_expense_types', 'mm_expenses.expense_type_id', '=', 'mm_expense_types.id')
            ->whereNull('mm_expenses.deleted_at')
            ->selectRaw('mm_expense_types.name as expense_type, COALESCE(SUM(mm_expenses.amount), 0) as total_amount, COUNT(*) as count');
        if ($dateFrom) $groupQuery->where('mm_expenses.date', '>=', $dateFrom);
        if ($dateTo)   $groupQuery->where('mm_expenses.date', '<=', $dateTo);

        $groupedExpenses = $groupQuery->groupBy('mm_expense_types.name')->get();

        return json_encode([
            'period' => ['from' => $dateFrom, 'to' => $dateTo],
            'total_expense_amount' => (float) $expensesList->sum('amount'),
            'expenses_by_type' => $groupedExpenses->map(function ($g) {
                return [
                    'expense_type' => $g->expense_type,
                    'total_amount' => (float) $g->total_amount,
                    'count' => $g->count,
                ];
            }),
            'recent_expenses' => $expensesList,
        ]);
    }

    protected function getCurrentStock(Request $request): string
    {
        $query = \App\Models\Quantity::with(['product.unit'])
            ->whereIn('id', function($q) {
                $q->select(DB::raw('MAX(id)'))
                  ->from('mm_quantity')
                  ->whereNull('deleted_at')
                  ->groupBy('product_id');
            });

        $latestQuantities = $query->get()->map(function($q) {
            return [
                'product_id' => $q->product_id,
                'product_name' => $q->product?->title,
                'code' => $q->product?->code,
                'unit' => $q->product?->unit?->name,
                'stock_quantity' => (float) $q->quantity,
                'last_updated' => $q->date?->toDateString(),
            ];
        });

        return json_encode($latestQuantities);
    }

    protected function getTodaySummary(Request $request): string
    {
        $today = date('Y-m-d');

        $dispatchQuery = DB::table('mm_dispatches')->whereNull('deleted_at')->whereDate('dispatch_time', $today);
        $dispatchStats = $dispatchQuery->selectRaw('COUNT(*) as count, COALESCE(SUM(delivered_qty), 0) as total_qty_m3, COALESCE(SUM(load_total_amount), 0) as total_amount')->first();

        $invoiceQuery = DB::table('mm_invoices')->where('invoice_type', 'sales')->whereNull('deleted_at')->whereDate('invoice_date', $today);
        $invoiceStats = $invoiceQuery->selectRaw('COUNT(*) as count, COALESCE(SUM(total_amount), 0) as total_amount')->first();

        $poQuery = DB::table('mm_purchase_orders')->whereNull('deleted_at')->whereDate('date_order', $today);
        $poStats = $poQuery->selectRaw('COUNT(*) as count, COALESCE(SUM(amount_total), 0) as total_amount')->first();

        $billQuery = DB::table('mm_invoices')->where('invoice_type', 'bill')->whereNull('deleted_at')->whereDate('invoice_date', $today);
        $billStats = $billQuery->selectRaw('COUNT(*) as count, COALESCE(SUM(total_amount), 0) as total_amount')->first();

        return json_encode([
            'date' => $today,
            'today_concrete_dispatches' => [
                'count' => $dispatchStats->count,
                'quantity_m3' => (float) $dispatchStats->total_qty_m3,
                'total_amount' => (float) $dispatchStats->total_amount,
            ],
            'today_sales_invoices' => [
                'count' => $invoiceStats->count,
                'total_amount' => (float) $invoiceStats->total_amount,
            ],
            'today_purchase_orders' => [
                'count' => $poStats->count,
                'total_amount' => (float) $poStats->total_amount,
            ],
            'today_purchase_bills' => [
                'count' => $billStats->count,
                'total_amount' => (float) $billStats->total_amount,
            ],
        ]);
    }
}
