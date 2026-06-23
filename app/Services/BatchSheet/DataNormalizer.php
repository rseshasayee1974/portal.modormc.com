<?php

namespace App\Services\BatchSheet;

use App\Models\Driver;
use App\Models\Machine;
use App\Models\Patron;
use App\Models\Personnel;
use App\Models\Product;
use App\Models\WorkOrder;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class DataNormalizer
{
    /**
     * Normalize values and resolve relational foreign keys (customer, truck, driver, operator, work order, product)
     */
    public function normalize(array $extractedHeader, array $extractedMaterials, int $plantId): array
    {
        Log::info("DataNormalizer: Normalizing values for plant {$plantId}");

        $normalizedHeader = [
            'batch_no' => $extractedHeader['batch_number'] ?? null,
            'batch_size' => $this->parseDecimal($extractedHeader['batch_size'] ?? 1.0),
            'start_time' => $this->normalizeTime($extractedHeader['batch_start_time'] ?? null),
            'end_time' => $this->normalizeTime($extractedHeader['batch_end_time'] ?? null),
            'customer_id' => null,
            'truck_id' => null,
            'driver_id' => null,
            'operator_id' => null,
            'work_order_id' => null,
        ];

        // Resolve customer
        if (!empty($extractedHeader['customer'])) {
            $normalizedHeader['customer_id'] = $this->resolveCustomer($extractedHeader['customer'], $plantId);
        }

        // Resolve truck
        if (!empty($extractedHeader['truck_number'])) {
            $normalizedHeader['truck_id'] = $this->resolveTruck($extractedHeader['truck_number'], $plantId);
        }

        // Resolve driver
        if (!empty($extractedHeader['driver'])) {
            $normalizedHeader['driver_id'] = $this->resolveDriver($extractedHeader['driver'], $plantId);
        }

        // Resolve operator
        if (!empty($extractedHeader['operator'])) {
            $normalizedHeader['operator_id'] = $this->resolveOperator($extractedHeader['operator'], $plantId);
        }

        // Resolve work order
        $orderNo = $extractedHeader['order_number'] ?? null;
        if (!empty($orderNo)) {
            $normalizedHeader['work_order_id'] = $this->resolveWorkOrder($orderNo, $plantId);
        }

        // Normalize materials and map to system products
        $normalizedMaterials = [];
        foreach ($extractedMaterials as $m) {
            $name = $m['material_name'] ?? '';
            if (empty($name)) continue;

            $productId = $this->resolveProduct($name, $plantId);

            $normalizedMaterials[] = [
                'material_name' => $name,
                'product_id' => $productId,
                'target_qty' => $this->parseDecimal($m['target_qty'] ?? 0),
                'actual_qty' => $this->parseDecimal($m['actual_qty'] ?? 0),
                'deviation_quantity' => $this->parseDecimal($m['deviation_quantity'] ?? 0),
            ];
        }

        return [
            'header' => $normalizedHeader,
            'materials' => $normalizedMaterials,
        ];
    }

    protected function parseDecimal($val): float
    {
        if (is_numeric($val)) return (float)$val;
        $clean = str_replace(',', '', (string)$val);
        return is_numeric($clean) ? (float)$clean : 0.0;
    }

    protected function normalizeTime(?string $timeStr): ?string
    {
        if (empty($timeStr)) return null;
        try {
            // Attempt to parse with Carbon
            return Carbon::parse($timeStr)->format('H:i:s');
        } catch (\Exception $e) {
            // Fallback: extract HH:MM:SS via regex
            if (preg_match('/(\d{1,2})[\s:-](\d{2})(?:[\s:-](\d{2}))?/', $timeStr, $matches)) {
                $hours = str_pad($matches[1], 2, '0', STR_PAD_LEFT);
                $minutes = str_pad($matches[2], 2, '0', STR_PAD_LEFT);
                $seconds = isset($matches[3]) ? str_pad($matches[3], 2, '0', STR_PAD_LEFT) : '00';
                return "{$hours}:{$minutes}:{$seconds}";
            }
            return null;
        }
    }

    protected function resolveCustomer(string $name, int $plantId): ?int
    {
        $cleanName = trim($name);
        if (empty($cleanName)) return null;

        // Exact match legal_name
        $patron = Patron::where('plant_id', $plantId)->where('legal_name', $cleanName)->first();
        if ($patron) return $patron->id;

        // Fuzzy match contains legal_name
        $patron = Patron::where('plant_id', $plantId)->where('legal_name', 'like', "%{$cleanName}%")->first();
        if ($patron) return $patron->id;

        // Try searching for each word in the name
        $words = explode(' ', $cleanName);
        foreach ($words as $word) {
            if (strlen($word) > 3) {
                $patron = Patron::where('plant_id', $plantId)->where('legal_name', 'like', "%{$word}%")->first();
                if ($patron) return $patron->id;
            }
        }

        return null;
    }

    protected function resolveTruck(string $reg, int $plantId): ?int
    {
        $cleanReg = preg_replace('/[^A-Za-z0-9]/', '', $reg);
        if (empty($cleanReg)) return null;

        $trucks = Machine::where('plant_id', $plantId)->get();
        foreach ($trucks as $truck) {
            $truckRegClean = preg_replace('/[^A-Za-z0-9]/', '', $truck->registration);
            if (str_contains($truckRegClean, $cleanReg) || str_contains($cleanReg, $truckRegClean)) {
                return $truck->id;
            }
        }

        return null;
    }

    protected function resolveDriver(string $name, int $plantId): ?int
    {
        $cleanName = trim($name);
        if (empty($cleanName)) return null;

        $driver = Driver::where('plant_id', $plantId)->where('name', $cleanName)->first();
        if ($driver) return $driver->id;

        $driver = Driver::where('plant_id', $plantId)->where('name', 'like', "%{$cleanName}%")->first();
        if ($driver) return $driver->id;

        return null;
    }

    protected function resolveOperator(string $name, int $plantId): ?int
    {
        $cleanName = trim($name);
        if (empty($cleanName)) return null;

        $op = Personnel::where('plant_id', $plantId)->where('name', $cleanName)->first();
        if ($op) return $op->id;

        $op = Personnel::where('plant_id', $plantId)->where('name', 'like', "%{$cleanName}%")->first();
        if ($op) return $op->id;

        return null;
    }

    protected function resolveWorkOrder(string $orderNo, int $plantId): ?int
    {
        $cleanNo = trim($orderNo);
        if (empty($cleanNo)) return null;

        $wo = WorkOrder::where('plant_id', $plantId)->where('order_no', $cleanNo)->first();
        if ($wo) return $wo->id;

        $wo = WorkOrder::where('plant_id', $plantId)->where('order_no', 'like', "%{$cleanNo}%")->first();
        if ($wo) return $wo->id;

        return null;
    }

    protected function resolveProduct(string $name, int $plantId): ?int
    {
        $cleanName = trim($name);
        if (empty($cleanName)) return null;

        $product = Product::where('plant_id', $plantId)->where('title', $cleanName)->first();
        if ($product) return $product->id;

        $product = Product::where('plant_id', $plantId)->where('title', 'like', "%{$cleanName}%")->first();
        if ($product) return $product->id;

        // Try fuzzy word search
        $words = explode(' ', $cleanName);
        foreach ($words as $word) {
            if (strlen($word) > 2) {
                $product = Product::where('plant_id', $plantId)->where('title', 'like', "%{$word}%")->first();
                if ($product) return $product->id;
            }
        }

        return null;
    }
}
