<?php

namespace App\Services\BatchSheet\Drivers;

class PalaniyappaMci70Plant782Driver extends AbstractPlantDriver
{
    public function getDriverCode(): string
    {
        return 'palaniyappa_mci70_plant_782';
    }

    public function getDriverName(): string
    {
        return 'Palaniyappa Concrete - Plant Serial 782 (MCI 70 Ver 3.1)';
    }

    public function getPlantSerial(): ?string
    {
        return '782';
    }

    public function canHandle(string $rawText, array $context = []): bool
    {
        return (stripos($rawText, '782') !== false && stripos($rawText, 'Plant Serial Number') !== false)
            || (stripos($rawText, 'MCI 70 Control System') !== false)
            || (stripos($rawText, 'PALANIYAPPA CONCRETE') !== false && stripos($rawText, 'MCI 70') !== false);
    }

    public function parse(string $rawText, array $context = []): array
    {
        $headers = [
            'batch_number' => $this->matchRegex('/Batch Number\s*\/?\s*Docket Number\s*:\s*([0-9a-zA-Z\-]+)/i', $rawText)
                ?: $this->matchRegex('/Docket Number\s*:\s*([0-9a-zA-Z\-]+)/i', $rawText),
            'batch_date' => $this->normalizeDate($this->matchRegex('/Batch Date\s*:\s*([0-9]{1,2}-[0-9]{1,2}-[0-9]{4}|[0-9]{1,2}\/[0-9]{1,2}\/[0-9]{4})/i', $rawText)),
            'batch_start_time' => $this->normalizeTime($this->matchRegex('/Batch Start Time\s*:\s*([0-9]{1,2}:[0-9]{2}(?::[0-9]{2})?)/i', $rawText)),
            'batch_end_time' => $this->normalizeTime($this->matchRegex('/Batch End Time\s*:\s*([0-9]{1,2}:[0-9]{2}(?::[0-9]{2})?)/i', $rawText)),
            'customer' => $this->matchRegex('/Customer\s*:\s*([^\n\r]+)/i', $rawText),
            'recipe_code' => $this->matchRegex('/Recipe Code\s*:\s*([^\n\r]+)/i', $rawText),
            'recipe_name' => $this->matchRegex('/Recipe Name\s*:\s*([^\n\r]+)/i', $rawText),
            'truck_number' => $this->matchRegex('/Truck Number\s*:\s*([0-9a-zA-Z\-]+)/i', $rawText),
            'driver' => $this->matchRegex('/Truck Driver\s*:\s*([^\n\r]+)/i', $rawText),
            'batcher_name' => $this->matchRegex('/Batcher Name\s*:\s*([^\n\r]+)/i', $rawText),
            'mixer_capacity' => 0.5,
            'batch_size' => (float)$this->matchRegex('/Production Quantity\s*:\s*([0-9\.]+)/i', $rawText) ?: 7.5,
            'production_qty' => (float)$this->matchRegex('/Production Quantity\s*:\s*([0-9\.]+)/i', $rawText) ?: 7.5,
            'ordered_qty' => (float)$this->matchRegex('/Order Quantity\s*:\s*([0-9\.]+)/i', $rawText) ?: 7.5,
            'with_this_load' => (float)$this->matchRegex('/With This Load\s*:\s*([0-9\.]+)/i', $rawText) ?: 7.5,
        ];

        // Plant 782 Column Names
        $colNames = ['SAND', 'MOI in %', '12MM', '20MM', 'AGG_0', 'CEMENT 1', 'CEMENT 2', 'CEMENT 3', 'WATER', 'MS/ICE', 'ADMIX1'];

        $setWeights = [];
        $actualWeights = [];

        if (preg_match('/Total Set Weight in Kgs\s*\n([0-9\.\s\-]+)/i', $rawText, $m)) {
            $setWeights = $this->extractNumbers($m[1]);
        }
        if (preg_match('/Total Actual Weight in Kgs\s*\n([0-9\.\s\-]+)/i', $rawText, $m)) {
            $actualWeights = $this->extractNumbers($m[1]);
        }

        $materials = [];
        $max = max(count($setWeights), count($actualWeights));
        for ($i = 0; $i < $max; $i++) {
            $t = $setWeights[$i] ?? 0.0;
            $a = $actualWeights[$i] ?? 0.0;
            if ($t > 0 || $a > 0) {
                $materials[] = [
                    'material_name' => $colNames[$i] ?? ("Material " . ($i + 1)),
                    'target_qty' => $t,
                    'actual_qty' => $a,
                    'deviation_quantity' => round($a - $t, 3),
                ];
            }
        }

        return [
            'headerFields' => array_filter($headers, fn($v) => !is_null($v)),
            'materialRows' => $materials,
            'confidence' => 98.0,
        ];
    }
}
