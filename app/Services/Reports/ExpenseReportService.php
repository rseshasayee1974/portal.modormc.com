<?php
/*
Author: ragul-onemodo
Created: 2026-11-05 11:17:31 Asia/Calcutta (UTC+05:30)
*/

namespace App\Services\Reports;

use App\Models\Expense;
use App\Services\PlantContextService;
use Carbon\Carbon;

class ExpenseReportService implements ReportServiceInterface
{
    public const COLUMNS = [
        'date' => 'Date', 'reference' => 'Reference', 'category' => 'Category',
        'payee' => 'Payee', 'payment_ledger' => 'Paid Through', 'asset' => 'Asset',
        'made_by' => 'Made By', 'notes' => 'Notes', 'accounting' => 'Accounting', 'amount' => 'Amount (INR)',
    ];

    public function __construct(private readonly PlantContextService $ctx) {}

    public function generate(array $params): array
    {
        \Illuminate\Support\Facades\Validator::make($params, [
            'employee_id' => 'nullable|integer|min:1',
            'expense_type_id' => 'nullable|integer|min:1',
        ])->validate();
        $start = Carbon::parse($params['start'])->toDateString();
        $end = Carbon::parse($params['end'])->toDateString();
        $expenses = Expense::where('plant_id', $this->ctx->requirePlantId())
            ->whereBetween('date', [$start, $end])
            ->when(!empty($params['expense_type_id']), fn ($q) => $q->where('expense_type_id', $params['expense_type_id']))
            ->when(!empty($params['employee_id']), fn ($q) => $q->where('made_by', $params['employee_id']))
            ->when(!empty($params['id']), fn ($q) => $q->where('paid_through', $params['id']))
            ->when(!empty($params['patron_id']), fn ($q) => $q->where(fn ($payee) =>
                $payee->where('vendor_id', $params['patron_id'])->orWhere('customer_id', $params['patron_id'])))
            ->with(['expenseType', 'vendor', 'customer', 'ledger', 'machine', 'madeByEmployee', 'accountingEntry'])
            ->orderBy('date')->orderBy('id')->get();
        $rows = $expenses->map(fn ($expense) => [
            'id' => $expense->id, 'date' => $expense->date?->toDateString(),
            'reference' => $expense->ref_no, 'category' => $expense->expenseType?->name ?? '-',
            'payee' => $expense->vendor?->legal_name ?? $expense->customer?->legal_name ?? '-',
            'payment_ledger' => $expense->ledger?->title ?? '-', 'asset' => $expense->machine?->registration ?? '-',
            'made_by' => trim(($expense->madeByEmployee?->first_name ?? '') . ' ' . ($expense->madeByEmployee?->last_name ?? '')) ?: '-',
            'notes' => $expense->note ?? '',
            'accounting' => $expense->accountingEntry?->is_status === 'POSTED' ? 'Posted' : 'Not posted',
            'amount' => (float) $expense->amount,
        ]);
        $total = fn ($items) => round($items->sum(fn ($row) => (int) round($row['amount'] * 100)) / 100, 2);
        return [
            'transactions' => $rows->all(), 'period' => ['start' => $start, 'end' => $end],
            'summary' => ['count' => $rows->count(), 'total' => $total($rows),
                'posted' => $total($rows->where('accounting', 'Posted')),
                'unposted' => $total($rows->where('accounting', 'Not posted'))],
            'categories' => $rows->groupBy('category')->map(fn ($items, $name) => [
                'name' => $name, 'count' => $items->count(), 'amount' => $total($items),
            ])->sortByDesc('amount')->values()->all(),
            'headers' => array_values(self::COLUMNS), 'fields' => array_keys(self::COLUMNS),
            'alignments' => array_map(fn ($field) => $field === 'amount' ? 'right' : 'left', array_keys(self::COLUMNS)),
            'totals' => ['amount' => $total($rows)],
        ];
    }

    public function targetName(array $params): string { return 'Expense Report'; }
}
