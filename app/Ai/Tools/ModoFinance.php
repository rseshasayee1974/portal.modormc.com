<?php

namespace App\Ai\Tools;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use Stringable;
use Illuminate\Support\Facades\DB;

class ModoFinance implements Tool
{
    /**
     * Resolved plant_id scope: null = all plants (SaaS Owner), int = scoped plant.
     */
    protected ?int $plantId;

    /**
     * Whether the current user is a SaaS Owner / Super Admin.
     */
    protected bool $isSaasOwner;

    public function __construct()
    {
        $user = auth()->user();
        $this->isSaasOwner = $user && $user->isSystemAdmin();

        if ($this->isSaasOwner) {
            $this->plantId = null; // All plants visible
        } else {
            $this->plantId = (int) session('active_plant_id') ?: null;
        }
    }

    /**
     * Get the description of the tool's purpose.
     */
    public function description(): Stringable|string
    {
        return 'Verify, calculate and analyze ready-mix concrete plant accounting and financial metrics: revenue streams, expense classifications, profit and loss statements, cash flow status, financial ratios, cost centers, accounts receivable aging, and standardized accounting guidelines.';
    }

    /**
     * Declare the parameters/schema the tool expects from the AI.
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'action' => $schema->string()->enum([
                'get_revenue_streams',
                'get_expense_classification',
                'get_profit_loss',
                'get_cash_flow',
                'get_financial_ratios',
                'get_cost_centers',
                'get_ar_aging',
                'get_accounting_guide'
            ])->required()->description('The specific financial or accounting report action to run'),
            'topic' => $schema->string()->enum([
                'prepaid_accrued',
                'deferred_revenue',
                'bank_reconciliation',
                'depreciation',
                'customer_refund',
                'tax_implications',
                'inventory_costs',
                'internal_controls',
                'indirect_costs',
                'capital_expenditures',
                'liabilities',
                'closing_books'
            ])->description('Optional topic filter for general accounting treatment guide/policy questions'),
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

        try {
            switch ($action) {
                case 'get_revenue_streams':
                    return $this->getRevenueStreams();

                case 'get_expense_classification':
                    return $this->getExpenseClassification();

                case 'get_profit_loss':
                    return $this->getProfitLoss();

                case 'get_cash_flow':
                    return $this->getCashFlow();

                case 'get_financial_ratios':
                    return $this->getFinancialRatios();

                case 'get_cost_centers':
                    return $this->getCostCenters();

                case 'get_ar_aging':
                    return $this->getArAging();

                case 'get_accounting_guide':
                    return $this->getAccountingGuide($this->getParam($request, 'topic'));

                default:
                    return "Error: Invalid action '{$action}' requested.";
            }
        } catch (\Exception $e) {
            return "Exception occurred while executing action '{$action}': " . $e->getMessage();
        }
    }

    protected function getRevenueStreams(): string
    {
        $query = DB::table('mm_invoice_items')
            ->join('mm_invoices', 'mm_invoice_items.invoice_id', '=', 'mm_invoices.id')
            ->where('mm_invoices.invoice_type', 'sales')
            ->whereNull('mm_invoices.deleted_at')
            ->whereNull('mm_invoice_items.deleted_at');

        if ($this->plantId !== null) {
            $query->where('mm_invoices.plant_id', $this->plantId);
        }

        $revenue = $query
            ->selectRaw('COALESCE(mm_invoice_items.item_name, "Ready-Mix Concrete") as product_name, SUM(mm_invoice_items.subtotal) as total_revenue, COUNT(*) as count')
            ->groupBy('product_name')
            ->orderByDesc('total_revenue')
            ->get()
            ->map(function($r) {
                return [
                    'revenue_stream' => $r->product_name,
                    'total_sales_amount' => (float)$r->total_revenue,
                    'invoice_line_count' => $r->count,
                ];
            });

        return json_encode([
            'plant_scope' => $this->isSaasOwner ? 'All Plants' : "Plant #{$this->plantId}",
            'revenue_streams' => $revenue,
        ]);
    }

    protected function getExpenseClassification(): string
    {
        $query = \App\Models\ExpenseType::with('ledger')->whereNull('deleted_at');

        if ($this->plantId !== null) {
            $query->where('plant_id', $this->plantId);
        }

        $classifications = $query->get()->map(function($et) {
            return [
                'expense_type' => $et->name,
                'ledger_code' => $et->ledger?->code ?: 'N/A',
                'ledger_title' => $et->ledger?->title ?: 'N/A',
                'status' => $et->status ? 'Active' : 'Inactive',
            ];
        });

        return json_encode([
            'plant_scope' => $this->isSaasOwner ? 'All Plants' : "Plant #{$this->plantId}",
            'expense_classifications' => $classifications,
        ]);
    }

    protected function getProfitLoss(): string
    {
        // Sales revenue by month
        $salesQ = DB::table('mm_invoices')->where('invoice_type', 'sales')->whereNull('deleted_at');
        if ($this->plantId !== null) $salesQ->where('plant_id', $this->plantId);
        $salesByMonth = $salesQ->selectRaw("DATE_FORMAT(invoice_date, '%Y-%m') as month, SUM(subtotal) as total_sales")
            ->groupBy('month')->get()->pluck('total_sales', 'month');

        // Material purchases by month
        $billsQ = DB::table('mm_invoices')->where('invoice_type', 'bill')->whereNull('deleted_at');
        if ($this->plantId !== null) $billsQ->where('plant_id', $this->plantId);
        $billsByMonth = $billsQ->selectRaw("DATE_FORMAT(invoice_date, '%Y-%m') as month, SUM(subtotal) as total_bills")
            ->groupBy('month')->get()->pluck('total_bills', 'month');

        // Other expenses by month
        $expQ = DB::table('mm_expenses')->whereNull('deleted_at');
        if ($this->plantId !== null) $expQ->where('plant_id', $this->plantId);
        $expensesByMonth = $expQ->selectRaw("DATE_FORMAT(date, '%Y-%m') as month, SUM(amount) as total_expenses")
            ->groupBy('month')->get()->pluck('total_expenses', 'month');

        $months = collect(array_merge(
            array_keys($salesByMonth->toArray()),
            array_keys($billsByMonth->toArray()),
            array_keys($expensesByMonth->toArray())
        ))->unique()->sort()->values();

        $pnL = $months->map(function($month) use ($salesByMonth, $billsByMonth, $expensesByMonth) {
            $sales    = (float)($salesByMonth[$month] ?? 0.0);
            $bills    = (float)($billsByMonth[$month] ?? 0.0);
            $expenses = (float)($expensesByMonth[$month] ?? 0.0);
            $net      = $sales - ($bills + $expenses);
            return [
                'month' => $month,
                'revenue_sales' => $sales,
                'material_purchases' => $bills,
                'operating_expenses' => $expenses,
                'net_profit_loss' => $net,
            ];
        });

        return json_encode([
            'plant_scope' => $this->isSaasOwner ? 'All Plants' : "Plant #{$this->plantId}",
            'profit_loss' => $pnL,
        ]);
    }

    protected function getCashFlow(): string
    {
        $ledgerQuery = \App\Models\Ledger::whereHas('accountType', function($q) {
            $q->where('title', 'like', '%bank%')->orWhere('title', 'like', '%cash%');
        });
        if ($this->plantId !== null) $ledgerQuery->where('plant_id', $this->plantId);

        $cashBankLedgers = $ledgerQuery->get();

        $cashFlowStatus = $cashBankLedgers->map(function($ledger) {
            $lines = DB::table('mm_journal_entry_lines')
                ->where('account_id', $ledger->id)
                ->where('is_deleted', 0)
                ->selectRaw('COALESCE(SUM(debit_amount), 0) as total_debit, COALESCE(SUM(credit_amount), 0) as total_credit')
                ->first();
            $balance = $lines->total_debit - $lines->total_credit;
            return [
                'ledger_code' => $ledger->code,
                'account_name' => $ledger->title,
                'type' => $ledger->accountType?->title,
                'current_balance' => (float)$balance,
                'status' => $balance >= 0 ? 'Positive (Asset)' : 'Overdraft (Liability)',
            ];
        });

        return json_encode([
            'plant_scope' => $this->isSaasOwner ? 'All Plants' : "Plant #{$this->plantId}",
            'cash_flow' => $cashFlowStatus,
        ]);
    }

    protected function getFinancialRatios(): string
    {
        // Helper: get ledger IDs for a given account type keyword, scoped to plant
        $getLedgerIds = function(string $keyword) {
            $q = \App\Models\Ledger::whereHas('accountType', function($q) use ($keyword) {
                $q->where('title', 'like', "%{$keyword}%");
            });
            if ($this->plantId !== null) $q->where('plant_id', $this->plantId);
            return $q->pluck('id');
        };

        $getJournalBalance = function($ledgerIds) {
            if ($ledgerIds->isEmpty()) return [0.0, 0.0];
            $lines = DB::table('mm_journal_entry_lines')
                ->whereIn('account_id', $ledgerIds)
                ->where('is_deleted', 0)
                ->selectRaw('COALESCE(SUM(debit_amount), 0) as total_debit, COALESCE(SUM(credit_amount), 0) as total_credit')
                ->first();
            return [$lines->total_debit, $lines->total_credit];
        };

        [$arDebit, $arCredit] = $getJournalBalance(
            $getLedgerIds('debtor')->merge($getLedgerIds('receivable'))
        );
        $arTotal = $arDebit - $arCredit;

        [$apDebit, $apCredit] = $getJournalBalance(
            $getLedgerIds('creditor')->merge($getLedgerIds('payable'))
        );
        $apTotal = $apCredit - $apDebit;

        [$cashDebit, $cashCredit] = $getJournalBalance(
            $getLedgerIds('bank')->merge($getLedgerIds('cash'))
        );
        $cashTotal = $cashDebit - $cashCredit;

        $salesQ = DB::table('mm_invoices')->where('invoice_type', 'sales')->whereNull('deleted_at');
        if ($this->plantId !== null) $salesQ->where('plant_id', $this->plantId);
        $totalSales = (float) $salesQ->sum('subtotal');

        $billsQ = DB::table('mm_invoices')->where('invoice_type', 'bill')->whereNull('deleted_at');
        if ($this->plantId !== null) $billsQ->where('plant_id', $this->plantId);
        $totalBills = (float) $billsQ->sum('subtotal');

        $expQ = DB::table('mm_expenses')->whereNull('deleted_at');
        if ($this->plantId !== null) $expQ->where('plant_id', $this->plantId);
        $totalExpenses = (float) $expQ->sum('amount');

        $currentAssets      = $cashTotal + $arTotal;
        $currentLiabilities = $apTotal;

        $ratios = [
            'plant_scope' => $this->isSaasOwner ? 'All Plants' : "Plant #{$this->plantId}",
            'liquidity_ratios' => [
                'current_assets' => $currentAssets,
                'current_liabilities' => $currentLiabilities,
                'current_ratio' => $currentLiabilities > 0 ? round($currentAssets / $currentLiabilities, 2) : 0.0,
                'cash_to_current_liabilities' => $currentLiabilities > 0 ? round($cashTotal / $currentLiabilities, 2) : 0.0,
            ],
            'profitability_ratios' => [
                'net_sales' => $totalSales,
                'gross_profit_margin_percent' => $totalSales > 0 ? round((($totalSales - $totalBills) / $totalSales) * 100, 2) : 0.0,
                'net_profit_margin_percent' => $totalSales > 0 ? round((($totalSales - ($totalBills + $totalExpenses)) / $totalSales) * 100, 2) : 0.0,
            ],
            'leverage_and_aging' => [
                'outstanding_debtors' => $arTotal,
                'outstanding_creditors' => $apTotal,
                'ar_to_ap_ratio' => $apTotal > 0 ? round($arTotal / $apTotal, 2) : 0.0,
            ]
        ];

        return json_encode($ratios);
    }

    protected function getCostCenters(): string
    {
        $plantExpQuery = DB::table('mm_expenses')
            ->join('mm_plants', 'mm_expenses.plant_id', '=', 'mm_plants.id')
            ->whereNull('mm_expenses.deleted_at')
            ->selectRaw('mm_plants.name as plant_name, SUM(mm_expenses.amount) as total_expenses, COUNT(*) as count');
        if ($this->plantId !== null) $plantExpQuery->where('mm_expenses.plant_id', $this->plantId);

        $plantExpenses = $plantExpQuery->groupBy('plant_name')->get()->map(function($p) {
            return [
                'cost_center_type' => 'Plant Operations',
                'cost_center_name' => $p->plant_name,
                'allocated_expenses' => (float)$p->total_expenses,
                'transactions_count' => $p->count,
            ];
        });

        $machineExpQuery = DB::table('mm_expenses')
            ->join('mm_machines', 'mm_expenses.machine_id', '=', 'mm_machines.id')
            ->whereNull('mm_expenses.deleted_at')
            ->selectRaw('mm_machines.name as machine_name, SUM(mm_expenses.amount) as total_expenses, COUNT(*) as count');
        if ($this->plantId !== null) $machineExpQuery->where('mm_expenses.plant_id', $this->plantId);

        $machineExpenses = $machineExpQuery->groupBy('machine_name')->get()->map(function($m) {
            return [
                'cost_center_type' => 'Vehicle logistics',
                'cost_center_name' => $m->machine_name,
                'allocated_expenses' => (float)$m->total_expenses,
                'transactions_count' => $m->count,
            ];
        });

        return json_encode([
            'plant_scope' => $this->isSaasOwner ? 'All Plants' : "Plant #{$this->plantId}",
            'cost_centers' => array_merge($plantExpenses->toArray(), $machineExpenses->toArray()),
        ]);
    }

    protected function getArAging(): string
    {
        $query = DB::table('mm_invoices')
            ->join('mm_patrons', 'mm_invoices.partner_id', '=', 'mm_patrons.id')
            ->where('mm_invoices.invoice_type', 'sales')
            ->where('mm_invoices.balance_amount', '>', 0)
            ->whereNull('mm_invoices.deleted_at')
            ->select('mm_patrons.legal_name as customer_name', 'mm_invoices.invoice_number', 'mm_invoices.prefix', 'mm_invoices.invoice_date', 'mm_invoices.due_date', 'mm_invoices.balance_amount');

        if ($this->plantId !== null) {
            $query->where('mm_invoices.plant_id', $this->plantId);
        }

        $invoices = $query->get();

        $now   = now();
        $aging = [
            'plant_scope' => $this->isSaasOwner ? 'All Plants' : "Plant #{$this->plantId}",
            'bracket_0_30_days' => 0.0,
            'bracket_31_60_days' => 0.0,
            'bracket_61_90_days' => 0.0,
            'bracket_91_plus_days' => 0.0,
            'total_outstanding' => 0.0,
            'by_customer' => []
        ];

        foreach ($invoices as $inv) {
            $dueDate    = \Carbon\Carbon::parse($inv->due_date);
            $daysPastDue = max(0, (int) $dueDate->diffInDays($now, false));
            $bal        = (float)$inv->balance_amount;
            $aging['total_outstanding'] += $bal;

            $bracket = match(true) {
                $daysPastDue > 90 => 'bracket_91_plus_days',
                $daysPastDue > 60 => 'bracket_61_90_days',
                $daysPastDue > 30 => 'bracket_31_60_days',
                default           => 'bracket_0_30_days',
            };
            $aging[$bracket] += $bal;

            $cust = $inv->customer_name;
            if (!isset($aging['by_customer'][$cust])) {
                $aging['by_customer'][$cust] = [
                    'customer' => $cust,
                    'bracket_0_30_days' => 0.0,
                    'bracket_31_60_days' => 0.0,
                    'bracket_61_90_days' => 0.0,
                    'bracket_91_plus_days' => 0.0,
                    'total' => 0.0,
                ];
            }
            $aging['by_customer'][$cust][$bracket] += $bal;
            $aging['by_customer'][$cust]['total']  += $bal;
        }

        $aging['by_customer'] = array_values($aging['by_customer']);
        return json_encode($aging);
    }

    protected function getAccountingGuide(?string $topic): string
    {
        $guides = [
            'prepaid_accrued' => [
                'title' => 'Prepaid vs. Accrued Expenses Treatment',
                'guidelines' => 'Prepaid expenses represent advance payments for services/goods to be received in the future (e.g., Annual Truck Insurance, Land Lease Advances). Record via journal voucher: Debit Prepaid Expenses (Asset), Credit Cash/Bank. Amortize monthly: Debit Insurance/Lease Expense, Credit Prepaid Expenses. Accrued expenses are expenses incurred but not yet invoiced (e.g. Electricity, Driver Wages). Record at month-end: Debit Expense Account, Credit Accrued Liabilities (Liability). Reverse/pay next month: Debit Accrued Liabilities, Credit Bank.'
            ],
            'deferred_revenue' => [
                'title' => 'Deferred Revenue Recognition',
                'guidelines' => 'In Ready-Mix Concrete operations, customers frequently pay advances before batching. Recognition steps: (1) On receipt of advance: Debit Cash/Bank, Credit Customer Advance (Liability). (2) Upon dispatch delivery: Generate Invoice, Debit Customer Account, Credit Sales Revenue & Taxes. (3) Reconcile/Allocate advance: Debit Customer Advance, Credit Customer Account.'
            ],
            'bank_reconciliation' => [
                'title' => 'Bank Reconciliation Procedure',
                'guidelines' => 'Perform reconciliation weekly: (1) Match bank statement deposits with receipt vouchers. (2) Match bank statement withdrawals with payment vouchers. (3) Identify Uncleared Checks (checks issued but not debited) and Outstanding Deposits (deposits not credited). (4) Post bank fees, interest, and charges via Journal Entry: Debit Bank Charges Expense, Credit Bank Account. (5) Ensure Adjusted Book Balance matches Adjusted Bank Balance.'
            ],
            'depreciation' => [
                'title' => 'Depreciation Policy for Fixed Assets',
                'guidelines' => 'Calculate depreciation monthly for RMC Plant machinery and mixers using Written Down Value (WDV) or Straight Line Method (SLM) based on standard asset lifetimes: (1) Batching Plant (15 years), (2) Transit Mixers (10 years), (3) Loader/DG Sets (8-10 years). Record: Debit Depreciation Expense (P&L), Credit Accumulated Depreciation (Balance Sheet).'
            ],
            'customer_refund' => [
                'title' => 'Customer Refund Journal Entry',
                'guidelines' => 'To process a customer refund for canceled concrete orders or excess advance: (1) Create Credit Note: Debit Sales Returns/Customer Account (reducing receivables), Credit Bank/Cash. (2) If returning an advance payment directly: Debit Customer Advance Account (Liability), Credit Bank/Cash.'
            ],
            'tax_implications' => [
                'title' => 'Revenue Recognition & GST Implications',
                'guidelines' => 'Ready-Mix Concrete (RMC) sales are subject to GST (generally 18% in India). Revenue is recognized upon dispatch from the plant when risk and control transfer to the customer (matching the Weighbridge Tare/Gross slip generation). GST Liability is triggered at the earliest of: (1) Invoice Generation date, or (2) Payment receipt date (for advances, under GST rules, taxes on services are paid on advance, but RMC is classified as Goods where tax is due on invoice/supply).'
            ],
            'inventory_costs' => [
                'title' => 'Project-Related Inventory Costing',
                'guidelines' => 'RMC raw materials (Cement, Crushed Sand, 10mm/20mm Aggregates, Admixtures, Water) must be tracked using the Weighted Average Cost (WAC) method. As raw material inflows (Purchase Inwards) occur at fluctuating prices, update WAC: New Average Rate = (Current Stock Value + New Purchase Value) / (Current Qty + New Qty). Material consumption is computed during daily batch runs and posted: Debit Cost of Goods Sold (COGS), Credit Raw Materials Inventory.'
            ],
            'internal_controls' => [
                'title' => 'Internal Controls for Expense Approval',
                'guidelines' => 'Enforce three-tier maker-checker controls: (1) Purchases and operational expenses must require a Purchase Order or Expense Requisition created by plant coordinators. (2) Invoices must be matched 3-way (Purchase Order, Inward Weigh slip, Vendor Invoice). (3) Authorizations: Plant manager approves up to ₹50,000; Executive Director / Accountant approval required above ₹50,000.'
            ],
            'indirect_costs' => [
                'title' => 'Indirect Costs Allocation to Project Budgets',
                'guidelines' => 'Allocate manufacturing overheads (Plant electricity, quality control lab fees, site administration) to specific concrete grades/projects based on produced volume (per cubic meter - m³): Allocation Rate = Total Monthly Overheads / Total Monthly Produced Volume. Post: Debit Project cost card, Credit overhead allocation control ledger.'
            ],
            'capital_expenditures' => [
                'title' => 'Capital Expenditures (CapEx) Criteria',
                'guidelines' => 'Classify purchases as CapEx if they: (1) Extend the useful life of RMC machinery by >1 year (e.g. major mixer engine overhaul, replacing batching plant pan mixer blades), and (2) Cost exceeds ₹20,000. Capitalize to Fixed Assets and depreciate. Routine repairs (grease, filter changes, tire punch repairs) are operating expenses (OpEx) and charged directly to repairs expense account.'
            ],
            'liabilities' => [
                'title' => 'Reporting Liabilities and Contingent Liabilities',
                'guidelines' => 'Disclose standard current liabilities (bills payable, outstanding GST) on the face of the balance sheet. Contingent liabilities (e.g., customer concrete quality disputes, unresolved commercial tax audits) must be evaluated: (1) If loss is probable and estimable: Provision is made in books. (2) If loss is possible but not certain: Disclose in financial footnotes. (3) If remote: No disclosure required.'
            ],
            'closing_books' => [
                'title' => 'Month-End Closing Checklist',
                'guidelines' => 'Closing books procedure: (1) Post all pending dispatch tickets and verify uninvoiced receipts. (2) Reconcile accounts receivable/payable balances with customer/vendor statement ledgers. (3) Post monthly depreciation and amortize prepaid expenses. (4) Reconcile bank balances and cash drawer registers. (5) Match GST return registers with general ledger sales/purchase tax pools. (6) Review trial balance for mismatched debit/credit codes.'
            ]
        ];

        if ($topic && isset($guides[$topic])) {
            return json_encode($guides[$topic]);
        }

        return json_encode([
            'message' => 'Standard Accounting Reference Library. Specify a valid topic parameter (e.g., prepaid_accrued, deferred_revenue, bank_reconciliation, depreciation, customer_refund, tax_implications, inventory_costs, internal_controls, indirect_costs, capital_expenditures, liabilities, closing_books) to receive detailed guidelines.',
            'available_topics' => array_keys($guides)
        ]);
    }
}
