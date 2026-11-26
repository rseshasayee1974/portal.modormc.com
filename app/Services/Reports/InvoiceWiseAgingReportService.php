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
        $totals = ['balance_amount' => 0];
        foreach ($buckets as $bucket => $label) {
            $columns[$bucket.'_amount'] = $label;
            $totals[$bucket.'_amount'] = 0;
        }

        $rows = [];
        foreach ($summary['open_invoices'] as $invoice) {
            $field = $invoice['aging_bucket'].'_amount';
            $balance = $invoice['balance_amount'];
            $rows[] = $invoice + [$field => $balance] + array_fill_keys(array_keys($totals), 0);
            $totals[$field] += $balance;
            $totals['balance_amount'] += $balance;
        }

        return [
            'transactions' => $rows,
            'plant_details' => \Illuminate\Support\Arr::only(
                \App\Services\PrintBrandHeader::resolve(['plant' => $summary['plant']]),
                ['name', 'address', 'gstin']
            ),
            'patron_name' => $summary['patron']->legal_name,
            'as_of' => $asOf->format('d/m/Y'),
            'columns' => [
                'fields' => array_keys($columns), 'headers' => array_values($columns),
                'alignments' => ['left', 'center', 'center', ...array_fill(0, count($columns) - 3, 'right')],
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
