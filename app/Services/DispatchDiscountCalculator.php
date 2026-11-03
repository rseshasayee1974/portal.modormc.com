<?php
/*
Author: ragul-onemodo
Created: 2026-11-03 14:41:15 Asia/Calcutta (UTC+05:30)
*/

namespace App\Services;

use App\Models\Tax;
use Illuminate\Validation\ValidationException;

class DispatchDiscountCalculator
{
    public static function calculate(array $data, bool $inclusive, array $rates): array
    {
        $rate = array_sum($rates);
        $gross = (float) ($data['delivered_qty'] ?? 0) * (float) ($data['load_rate'] ?? 0);
        $material = round($inclusive && $rate > 0 ? $gross / (1 + $rate / 100) : $gross, 2);
        $pump = (float) ($data['pump_charges'] ?? 0);
        $pumpTaxable = !empty($data['pump_charge_with_tax']);
        $pump = round($pumpTaxable && $rate > 0 ? $pump / (1 + $rate / 100) : $pump, 2);
        $base = round($material + $pump, 2);
        $discount = round((float) ($data['discount_amount'] ?? 0), 2);
        if ($discount < 0 || $discount > $base) {
            throw ValidationException::withMessages(['financials.discount_amount' => 'Discount must be between zero and the subtotal before tax.']);
        }
        // Allocate the discount proportionally so non-taxable pump charges remain non-taxable.
        $materialDiscount = $base > 0 ? round($discount * $material / $base, 2) : 0;
        $material -= $materialDiscount;
        $pump -= $discount - $materialDiscount;
        $tax = fn (float $amount) => array_sum(array_map(fn ($part) => round($amount * $part / 100, 2), $rates));
        $taxAmount = round($tax($material) + ($pumpTaxable ? $tax($pump) : 0), 2);
        $subtotal = round($base - $discount, 2);
        $total = round($subtotal + $taxAmount + (float) ($data['pass_amount'] ?? 0)
            + (float) ($data['adjustment_amount'] ?? 0) + (float) ($data['transport_expenses'] ?? 0), 2);
        $roundOff = isset($data['round_off']) ? (float) $data['round_off'] : round(round($total) - $total, 2);
        return ['load_untax_amount' => $subtotal, 'load_tax_amount' => $taxAmount,
            'load_total_amount' => round($total + $roundOff, 2), 'round_off' => $roundOff];
    }

    public static function forDispatch(array $data, bool $inclusive): array
    {
        $tax = !empty($data['load_tax_id']) ? Tax::with('children')->find($data['load_tax_id']) : null;
        $rates = $tax?->children->isNotEmpty() ? $tax->children->pluck('tax_rate')->map(fn ($rate) => (float) $rate)->all()
            : [(float) ($tax?->tax_rate ?? 0)];
        return self::calculate($data, $inclusive, $rates);
    }
}
