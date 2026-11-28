<?php
/*
Author: ragul-onemodo
Created: 2026-11-28 15:23:30 Asia/Calcutta (UTC+05:30)
*/

namespace App\Services\Reports;

use App\Models\FuelLog;
use App\Services\PlantContextService;

class FuelLogReportService implements ReportServiceInterface
{
    public const COLUMNS = [
        'date' => 'Refuel date', 'machine' => 'Machine', 'driver' => 'Driver',
        'vendor' => 'Vendor', 'pump' => 'Fuel pump', 'receipt' => 'Pump bill no.',
        'bill' => 'Generated bill', 'payment' => 'Payment method',
        'odometer' => 'Odometer', 'hours' => 'Hourmeter',
        'quantity' => 'Quantity (L)', 'rate' => 'Rate (₹/L)', 'amount' => 'Amount (₹)',
    ];

    public function __construct(private readonly PlantContextService $context) {}

    public function generate(array $params): array
    {
        $query = FuelLog::query()->where('plant_id', $this->context->requirePlantId())
            ->whereBetween('log_date', [$params['start'], $params['end']])
            ->with(['machine:id,registration', 'driver:id,first_name,last_name', 'vendor:id,legal_name', 'invoice:id,prefix,invoice_number'])
            ->orderBy('log_date')->orderBy('id');
        foreach (['truck_id' => 'machine_id', 'driver_id' => 'driver_id'] as $filter => $column) {
            if (!empty($params[$filter])) $query->where($column, $params[$filter]);
        }
        $rows = $query->get()->map(fn (FuelLog $log) => [
            'date' => $log->log_date->format('d-m-Y H:i'),
            'machine' => $log->machine?->registration,
            'driver' => trim($log->driver?->first_name.' '.$log->driver?->last_name),
            'vendor' => $log->vendor?->legal_name, 'pump' => $log->pump_name,
            'receipt' => $log->bill_no, 'bill' => $log->invoice?->full_number,
            'payment' => $log->payment_method, 'odometer' => $log->odometer_reading,
            'hours' => $log->hourmeter_reading, 'quantity' => $log->quantity,
            'rate' => $log->rate_per_liter, 'amount' => $log->total_amount,
        ]);
        return [
            'columns' => self::COLUMNS, 'transactions' => $rows,
            'totals' => ['quantity' => number_format($rows->sum('quantity'), 2, '.', ''),
                         'amount' => number_format($rows->sum('amount'), 2, '.', '')],
        ];
    }

    public function targetName(array $params): string
    {
        return 'Fuel Log Report';
    }

    public static function pdfColumns(array $data): array
    {
        $fields = array_keys(self::COLUMNS);
        return ['headers' => array_values(self::COLUMNS), 'fields' => $fields,
            'alignments' => array_map(fn ($field) => in_array($field, ['odometer', 'hours', 'quantity', 'rate', 'amount']) ? 'right' : 'left', $fields),
            'totals' => $data['totals']];
    }
}
