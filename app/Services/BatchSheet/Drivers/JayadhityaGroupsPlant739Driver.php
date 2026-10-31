<?php

/*
 * Author: ragul-onemodo
 * Created: 2026-10-31 09:22:38 Asia/Calcutta (UTC+05:30)
 */

namespace App\Services\BatchSheet\Drivers;

use UnexpectedValueException;

class JayadhityaGroupsPlant739Driver extends AbstractPlantDriver
{
    private const COLUMNS = [
        'MSAND 1', 'MSAND 2', '20MM', '12MM', 'Agg 5', 'Agg 6',
        'CEMENT 1', 'CEMENT 2', 'CEMENT 3', 'Cem 4', 'Cem 5',
        'WATER', 'WC', 'Wtr 2', 'Ice', 'ADMIX 1', 'ADMIX 2', 'Adm 3', 'Adm 4', 'Sil',
    ];

    public function getDriverCode(): string
    {
        return 'jayadhitya_groups_plant_739';
    }

    public function getDriverName(): string
    {
        return 'Jayadhitya Groups - Plant Sl.No 739 (Schwing EWIG M1 C)';
    }

    public function getPlantSerial(): ?string
    {
        return '739';
    }

    public function canHandle(string $rawText, array $context = []): bool
    {
        // Match the serial field, never an incidental material weight or batch number.
        if (preg_match('/Plant\s+Sl\.?\s*No\.?\s*:\s*([\w-]+)/i', $rawText, $match)) {
            return $match[1] === $this->getPlantSerial();
        }

        return preg_match('/JAYADHITYA\s+GROUPS/i', $rawText) === 1
            && stripos($rawText, 'BATCH SHEET REPORT') !== false;
    }

    public function parse(string $rawText, array $context = []): array
    {
        $labels = [
            'batch_number' => 'Batch Number', 'batch_date' => 'Batch Date',
            'batch_start_time' => 'Batch Start Time', 'batch_end_time' => 'Batch End Time',
            'customer' => 'Customer', 'site' => 'Site', 'recipe_name' => 'Recipe Name',
            'recipe_code' => 'Recipe Code', 'truck_number' => 'Truck No', 'driver' => 'Truck Driver',
            'mixer_capacity' => 'Mixer Capacity', 'batch_size' => 'Batch Size',
            'production_qty' => 'Production Qty', 'order_number' => 'Order No',
            'manual_qty' => 'Adj / Manual Qty', 'ordered_qty' => 'Ordered Qty',
            'with_this_load' => 'With This Load', 'plant_type' => 'Plant Type',
            'plant_serial' => 'Plant Sl.No', 'recipe_target_weight' => 'Mass of Recipe Targets in kg',
            'total_set_weight' => 'Mass of Total Set Weight in kg',
            'total_actual_weight' => 'Mass of Total Actual Weight in kg',
            'total_difference_percent' => 'Total Mass Difference in Percentage',
        ];

        // Smalot emits three header columns on each line. Split only at known labels.
        $alternatives = implode('|', array_map(fn ($label) => preg_quote($label, '/'), $labels));
        $text = preg_replace('/\r\n?/', "\n", $rawText);
        $text = preg_replace('/\b('.$alternatives.')\s*:/i', "\n$1:", $text);
        $headers = [];
        foreach ($labels as $key => $label) {
            $value = $this->matchRegex('/^'.preg_quote($label, '/').':\h*([^\n]*)/mi', $text);
            if ($value !== null && $value !== '') {
                $headers[$key] = $value;
            }
        }

        if (isset($headers['batch_date'])) {
            $headers['batch_date'] = $this->normalizeDate($headers['batch_date']);
        }
        foreach (['batch_start_time', 'batch_end_time'] as $key) {
            if (isset($headers[$key])) {
                $headers[$key] = $this->normalizeTime($headers[$key]);
            }
        }
        foreach (['mixer_capacity', 'batch_size', 'production_qty', 'manual_qty', 'ordered_qty',
            'with_this_load', 'recipe_target_weight', 'total_set_weight', 'total_actual_weight',
            'total_difference_percent'] as $key) {
            if (isset($headers[$key]) && is_numeric($headers[$key])) {
                $headers[$key] = (float) $headers[$key];
            }
        }

        $set = $this->weightRow($text, 'Total Set Weight in kg');
        $actual = $this->weightRow($text, 'Total Actual Weight in kg');
        $materials = [];
        foreach (self::COLUMNS as $index => $name) {
            if ($set[$index] != 0.0 || $actual[$index] != 0.0) {
                $materials[] = [
                    'material_name' => $name,
                    'target_qty' => $set[$index],
                    'actual_qty' => $actual[$index],
                    'deviation_quantity' => round($actual[$index] - $set[$index], 3),
                ];
            }
        }

        return ['headerFields' => $headers, 'materialRows' => $materials, 'confidence' => 98.5];
    }

    private function weightRow(string $text, string $label): array
    {
        // Bound the capture to numeric lines; mass summaries and cycle rows are separate.
        $row = $this->matchRegex('/^\h*'.preg_quote($label, '/').'\h*\n([\d.\h\n-]+)/mi', $text);
        $values = $row === null ? [] : $this->extractNumbers($row);
        if (count($values) !== count(self::COLUMNS)) {
            throw new UnexpectedValueException("Plant 739: {$label} must contain 20 material columns.");
        }

        return $values;
    }
}
