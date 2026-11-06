<?php
/*
Author: ragul-onemodo
Created: 2026-11-06 10:11:19 Asia/Calcutta (UTC+05:30)
*/
namespace App\Services;
use App\Models\Tax;
use Illuminate\Validation\ValidationException;

class StockExhaustPricing
{
    public function calculate(array $data, int $plantId): array
    {
        $taxes = Tax::where('plant_id', $plantId)->whereIn('id', collect($data['lines'])->pluck('tax_id')->filter())->get()->keyBy('id');
        $totals = array_fill_keys(['discount_amount', 'untaxed_amount', 'tax_amount', 'total_amount'], 0);
        $inclusive = (bool) ($data['is_tax_inclusive'] ?? false);
        foreach ($data['lines'] as $index => &$line) {
            if (empty($line['product_id'])) continue;
            $taxId = $line['tax_id'] ?? null;
            if ($taxId && !isset($taxes[$taxId])) throw ValidationException::withMessages(["lines.$index.tax_id" => 'Select a tax belonging to this plant.']);
            $rate = (float) ($taxId ? $taxes[$taxId]->rate : 0);
            $line = array_merge($line, self::line($line, $rate, $inclusive));
            foreach ($totals as $key => &$value) $value += $line[$key];
            unset($value);
        }
        unset($line);
        $data['is_tax_inclusive'] = $inclusive;
        foreach ($totals as $key => $value) $data[$key] = $value;
        return $data;
    }

    public static function line(array $line, float $taxRate, bool $inclusive): array
    {
        // Preserve calculation precision without rounding amounts to currency cents.
        $gross = (float) $line['quantity_issued'] * (float) ($line['unit_price'] ?? 0);
        $discount = (float) ($line['discount'] ?? 0);
        $discountAmount = ($line['discount_type'] ?? 'amount') === '%' ? $gross * $discount / 100 : $discount;
        if (($line['discount_type'] ?? 'amount') === '%' && $discount > 100) throw ValidationException::withMessages(['lines' => 'Percentage discount cannot exceed 100.']);
        if ($discountAmount > $gross) throw ValidationException::withMessages(['lines' => 'Discount cannot exceed the line amount.']);
        $discountAmount = min($gross, $discountAmount);
        $net = $gross - $discountAmount;
        $base = $inclusive ? $net * 100 / (100 + $taxRate) : $net;
        $tax = $inclusive ? $net - $base : $base * $taxRate / 100;
        return ['unit_price' => (float) ($line['unit_price'] ?? 0), 'tax_id' => $line['tax_id'] ?? null,
            'tax_rate' => $taxRate, 'discount_type' => $line['discount_type'] ?? 'amount', 'discount' => $discount,
            'discount_amount' => $discountAmount, 'untaxed_amount' => $base, 'tax_amount' => $tax, 'total_amount' => $base + $tax];
    }
}
