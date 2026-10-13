<?php
/*
Author: ragul-onemodo
Created: 2026-10-09 18:00:44 Asia/Calcutta (UTC+05:30)
*/

namespace App\Services\Mobile;

use App\Models\Tax;

class QuotationCalculator
{
    // Only rates, quantities and the selected tax are inputs. Client totals are never trusted.
    public static function items(array $items, bool $inclusive): array
    {
        return array_map(function ($item, $index) use ($inclusive) {
            $tax = !empty($item['tax_id']) ? Tax::with('children')->findOrFail($item['tax_id']) : null;
            $components = $tax?->children->isNotEmpty() ? $tax->children : collect($tax ? [$tax] : []);
            $rate = $components->sum(fn ($component) => (float) $component->tax_rate);
            $gross = round((float) $item['quantity'] * (float) $item['rate'] + (float) ($item['pump_rate'] ?? 0), 2);
            $discountType = $item['discount_type'] ?? 'amount';
            $discount = (float) ($item['discount'] ?? 0);
            if (!in_array($discountType, ['amount', '%'], true) || $discount < 0 || ($discountType === '%' && $discount > 100)) {
                throw \Illuminate\Validation\ValidationException::withMessages(["items.$index.discount" => 'Discount must be non-negative and percentage cannot exceed 100.']);
            }
            $discountAmount = round($discountType === '%' ? $gross * $discount / 100 : $discount, 2);
            if ($discountAmount > $gross) {
                throw \Illuminate\Validation\ValidationException::withMessages(["items.$index.discount" => 'Discount cannot exceed the item amount.']);
            }
            $net = $gross - $discountAmount;
            $base = round($inclusive && $rate > 0 ? $net * 100 / (100 + $rate) : $net, 2);
            $amountTax = round($components->sum(fn ($component) => round($base * (float) $component->tax_rate / 100, 2)), 2);
            return array_merge($item, ['discount_type' => $discountType, 'discount' => $discount, 'discount_amount' => $discountAmount, 'untaxed_amount' => $base, 'tax_amount' => $amountTax, 'amount_total' => round($base + $amountTax, 2)]);
        }, $items, array_keys($items));
    }
}
