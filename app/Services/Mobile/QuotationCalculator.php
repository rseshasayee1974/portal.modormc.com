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
        return array_map(function ($item) use ($inclusive) {
            $tax = !empty($item['tax_id']) ? Tax::with('children')->findOrFail($item['tax_id']) : null;
            $components = $tax?->children->isNotEmpty() ? $tax->children : collect($tax ? [$tax] : []);
            $rate = $components->sum(fn ($component) => (float) $component->tax_rate);
            $calculation = \App\Services\PrintDataFormatter::calculateLineItemTotals((float) $item['quantity'], (float) $item['rate'], (float) ($item['pump_rate'] ?? 0), $rate, $inclusive);
            $base = round($calculation['untaxedAmount'], 2);
            $amountTax = round($components->sum(fn ($component) => round($base * (float) $component->tax_rate / 100, 2)), 2);
            return array_merge($item, ['untaxed_amount' => $base, 'tax_amount' => $amountTax, 'amount_total' => round($base + $amountTax, 2)]);
        }, $items);
    }
}
