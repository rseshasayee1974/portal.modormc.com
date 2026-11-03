<?php
/*
Author: ragul-onemodo
Created: 2026-11-02 16:44:11 Asia/Calcutta (UTC+05:30)
*/

namespace App\Services\BatchSheet\Drivers;

use UnexpectedValueException;

class JayadhityaPlant2787Driver extends AbstractPlantDriver
{
    public const CODE = 'jayadhitya_plant_2_2787';
    public const MATERIALS = ['AGG1', 'MSAND 1', '12MM', '20MM', 'Agg 6', 'CEMENT 1', 'CEMENT 2', 'CEMENT 3', 'WATER', 'Ice', 'ADMIX 1', 'ADMIX 2'];
    public const PRINT_GROUPS = [
        'Aggregate' => ['AGG1' => '20 M', 'MSAND 1' => 'MSAN', '12MM' => '12 MM', '20MM' => '20 MM', 'Agg 6' => '0'],
        'Cement' => ['CEMENT 1' => 'CEM1', 'CEMENT 2' => 'CEM2', 'CEMENT 3' => 'CEM3'],
        'Water' => ['WATER' => 'WATER'],
        'MS / ICE' => ['Ice' => ''],
        'Admixture' => ['ADMIX 1' => 'ADMIX1', 'ADMIX 2' => '-'],
    ];

    public function getDriverCode(): string { return self::CODE; }
    public function getDriverName(): string { return 'Jayadhitya Plant 2 - Serial 2787 (Schwing MCI 370)'; }
    public function getPlantSerial(): ?string { return '2787'; }

    public function canHandle(string $rawText, array $context = []): bool
    {
        return preg_match('/Plant\s+Serial\s+Number\s*:\s*2787\b/i', $rawText) === 1;
    }

    public function parse(string $rawText, array $context = []): array
    {
        $labels = [
            'batch_number' => 'Batch Number / Docket Number', 'batch_date' => 'Batch Date',
            'batch_start_time' => 'Batch Start Time', 'batch_end_time' => 'Batch End Time',
            'plant_serial' => 'Plant Serial Number', 'customer' => 'Customer', 'site' => 'Site',
            'recipe_code' => 'Recipe Code', 'recipe_name' => 'Recipe Name', 'truck_number' => 'Truck Number',
            'driver' => 'Truck Driver', 'order_number' => 'Order Number', 'batcher_name' => 'Batcher Name',
            'ordered_qty' => 'Ordered Quantity', 'production_qty' => 'Production Quantity',
            'manual_qty' => 'Adj/Manual Quantity', 'with_this_load' => 'With This Load',
            'mixer_capacity' => 'Mixer Capacity', 'run_size' => 'Batch Size', 'net_weight' => 'Net Wt From W.Bridge',
        ];
        $text = preg_replace('/\r\n?/', "\n", $rawText);
        $alternatives = implode('|', array_map(fn ($label) => preg_quote($label, '/'), $labels));
        $text = preg_replace('/('.$alternatives.')\h*:*/i', "\n$1:", $text);
        $header = [];
        foreach ($labels as $key => $label) {
            $value = $this->matchRegex('/^'.preg_quote($label, '/').':\h*:?[\h]*([^\n]*)/mi', $text);
            if ($value !== null && $value !== '') $header[$key] = trim($value, " \t:");
        }
        if (($header['plant_serial'] ?? null) !== '2787') {
            throw new UnexpectedValueException('The selected Plant 2 profile requires plant serial 2787.');
        }
        if (isset($header['batch_date'])) $header['batch_date'] = $this->normalizeDate($header['batch_date']);
        foreach (['batch_start_time', 'batch_end_time'] as $key) {
            if (isset($header[$key])) $header[$key] = $this->normalizeTime($header[$key]);
        }
        foreach (['ordered_qty', 'production_qty', 'manual_qty', 'with_this_load', 'mixer_capacity', 'run_size', 'net_weight'] as $key) {
            if (isset($header[$key]) && preg_match('/^(-?\d+(?:\.\d+)?)/', $header[$key], $match)) $header[$key] = (float) $match[1];
        }
        // The stored batch is the full load, not the one-m3 mixer cycle.
        if (isset($header['production_qty'])) $header['batch_size'] = $header['production_qty'];
        if (empty($header['batch_number']) || !is_numeric($header['production_qty'] ?? null) || $header['production_qty'] <= 0) {
            throw new UnexpectedValueException('Plant 2787: a docket number and positive production quantity are required.');
        }

        $set = $this->totals($text, 'Total Set Weight in Kgs.');
        $actual = $this->totals($text, 'Total Actual in Kgs.');
        $runs = $this->runs($rawText, $context['layout_rows'] ?? []);
        $materials = [];
        foreach (self::MATERIALS as $index => $name) {
            if ($set[$index] == 0 && $actual[$index] == 0) continue;
            $material = ['material_name' => $name, 'target_qty' => $set[$index], 'actual_qty' => $actual[$index],
                'deviation_quantity' => round($actual[$index] - $set[$index], 3)];
            if ($runs) {
                $values = array_column($runs, $name);
                if (abs(array_sum($values) - $actual[$index]) > 0.01) {
                    throw new UnexpectedValueException("Plant 2787: {$name} cycle weights do not match the printed total.");
                }
                $material['runs'] = $values;
            }
            $materials[] = $material;
        }
        if (!$materials) throw new UnexpectedValueException('Plant 2787: no material consumption was found.');
        return ['headerFields' => $header, 'materialRows' => $materials, 'confidence' => 98.5];
    }

    private function totals(string $text, string $label): array
    {
        $line = $this->matchRegex('/^\h*'.preg_quote($label, '/').'\h*\n([^\n]+)/mi', $text);
        $values = $line === null ? [] : $this->extractNumbers($line);
        // The original report has a duplicated, overlapping zero at the unused aggregate slot.
        if (count($values) === 13 && $values[4] === 0.0 && $values[5] === 0.0) array_splice($values, 5, 1);
        if (count($values) !== 12) throw new UnexpectedValueException("Plant 2787: {$label} must contain 12 material values.");
        return $values;
    }

    private function runs(string $text, array $layoutRows): array
    {
        $reading = false;
        $runs = [];
        $mainColumns = ['AGG1', 'MSAND 1', 'moisture', '12MM', '20MM', 'CEMENT 1', 'CEMENT 2', 'CEMENT 3', 'WATER', 'ADMIX 1', 'ADMIX 2'];
        $fullColumns = ['AGG1', 'MSAND 1', 'moisture', '12MM', '20MM', 'Agg 6', 'CEMENT 1', 'CEMENT 2', 'CEMENT 3', 'WATER', 'correction', 'MS', 'Ice', 'ADMIX 1', 'ADMIX 2'];
        $rows = $layoutRows ?: array_map(fn ($line) => ['text' => $line, 'cells' => []], explode("\n", $text));
        foreach ($rows as $row) {
            if (preg_match('/^Actual in Kgs\./i', trim($row['text']))) { $reading = true; continue; }
            if (stripos($row['text'], 'Total Set Weight') !== false) break;
            if (!$reading || !preg_match('/^\h*[\d.-]/', $row['text'])) continue;
            $values = $this->extractNumbers($row['text']);
            $chunks = array_map(fn ($cell) => $this->extractNumbers($cell['text']), $row['cells']);
            if (count($values) === 15 && count($chunks[0] ?? []) === 11) {
                $run = array_combine($mainColumns, $chunks[0]);
                $remaining = array_merge(...array_slice($chunks, 1));
                foreach (['Agg 6', 'correction', 'MS', 'Ice'] as $index => $column) $run[$column] = $remaining[$index];
            } elseif (count($values) === 15) {
                $run = array_combine($fullColumns, $values);
            } else {
                throw new UnexpectedValueException('Plant 2787: a mixer cycle has an incomplete material matrix.');
            }
            $runs[] = $run;
        }
        return $runs;
    }
}
