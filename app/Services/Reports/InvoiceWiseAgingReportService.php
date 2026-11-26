<?php
/*
 * Author: ragul-onemodo
 * Created: 2026-11-26 15:22:36 Asia/Calcutta (UTC+05:30)
 */

namespace App\Services\Reports;

use App\Models\Patron;
use Carbon\Carbon;
use Illuminate\Validation\Rule;

class InvoiceWiseAgingReportService implements ReportServiceInterface
{
    public function __construct(private readonly CustomerOutstandingReportService $outstanding) {}

    public static function patronRules(?int $plantId): array
    {
        return ['required', 'integer', Rule::exists('mm_patrons', 'id')->where('plant_id', $plantId)->whereNull('deleted_at')];
    }

    public function generate(array $params): array
    {
        $plantId = $params['plant_id'] ?? session('active_plant_id');
        validator($params, ['patron_id' => self::patronRules($plantId)])->validate();
        abort_unless($plantId, 422, 'Select a plant.');
        $asOf = Carbon::parse($params['end'] ?? now())->startOfDay();
        $summary = $this->outstanding->agingSummary($plantId, $params['patron_id'], null, $asOf->toDateString(), $asOf);
        return self::formatSummary($summary, $asOf);
    }

    public static function formatSummary(array $summary, Carbon $asOf, ?string $group = null): array
    {
        $buckets = [
            'aging_0_15' => '0–15 days', 'aging_16_30' => '16–30 days',
            'aging_31_60' => '31–60 days', 'aging_61_90' => '61–90 days',
            'aging_90_plus' => '91+ days',
        ];
        $columns = [
            'full_number' => 'Invoice No.', 'invoice_date' => 'Invoice Date', 'due_date' => 'Due Date',
            'days_overdue' => 'Days Overdue', 'total_amount' => 'Invoice Amount',
            'paid_amount' => 'Allocated Amount', 'balance_amount' => 'Outstanding',
        ];
        if ($group === 'vendor') {
            $columns = ['customer_name' => 'Vendor', 'total_amount' => 'Billed Amount', 'balance_amount' => 'Outstanding'];
        } elseif ($group !== null) {
            $bill = $group === 'bill';
            $columns = ['customer_name' => $bill ? 'Vendor' : 'Customer'] + $columns;
            if ($bill) {
                $columns['full_number'] = 'Bill No.';
                $columns['invoice_date'] = 'Bill Date';
                $columns['total_amount'] = 'Bill Amount';
            }
        }
        $totals = ['balance_amount' => 0];
        foreach ($buckets as $bucket => $label) {
            $columns[$bucket.'_amount'] = $label;
            $totals[$bucket.'_amount'] = 0;
        }

        $rows = [];
        if ($group === 'vendor') {
            foreach ($summary['customer_summary'] as $party) {
                $row = ['id' => $party['customer_id'], 'customer_name' => $party['customer_name'],
                    'total_amount' => $party['total_purchased'], 'balance_amount' => $party['total_outstanding']];
                foreach ($buckets as $bucket => $label) {
                    $row[$bucket.'_amount'] = $party[$bucket];
                }
                $rows[] = $row;
            }
        }
        $documents = $group === 'vendor' ? [] : $summary['open_invoices'];
        if ($group === 'bill' || $group === 'invoice') {
            $documents = collect($documents)->sortBy([['customer_name', 'asc'], ['customer_id', 'asc'], ['raw_date', 'asc'], ['id', 'asc']]);
        }
        foreach ($documents as $invoice) {
            $field = $invoice['aging_bucket'].'_amount';
            $balance = $invoice['balance_amount'];
            $rows[] = $invoice + [$field => $balance] + array_fill_keys(array_keys($totals), 0);
        }
        foreach (array_keys($totals) as $field) {
            $totals[$field] = array_sum(array_column($rows, $field));
        }

        return [
            'transactions' => $rows,
            'plant_details' => \Illuminate\Support\Arr::only(
                \App\Services\PrintBrandHeader::resolve(['plant' => $summary['plant']]),
                ['name', 'address', 'gstin']
            ),
            'patron_name' => $summary['patron']?->legal_name ?? (in_array($group, ['bill', 'vendor'], true) ? 'All vendors' : 'All customers'),
            'outstanding_group' => $group,
            'as_of' => $asOf->format('d/m/Y'),
            'columns' => [
                'fields' => array_keys($columns), 'headers' => array_values($columns),
                'alignments' => array_map(fn ($key) => str_ends_with($key, '_amount') || $key === 'days_overdue' ? 'right' : 'left', array_keys($columns)),
                'totals' => array_map(fn ($amount) => round($amount, 2), $totals),
            ],
        ];
    }

    public function targetName(array $params): string
    {
        return Patron::where('plant_id', $params['plant_id'] ?? session('active_plant_id'))
            ->whereKey($params['patron_id'])->value('legal_name') ?? 'Invoice-wise Aging';
    }
}
