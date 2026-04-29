<?php

namespace App\Helpers;

use App\Models\PurchaseOrder;

class Financialyear
{
    public static function getFinancialYear(): string
    {
        $year  = (int) date('Y');
        $month = (int) date('m');

        if ($month >= 4) {
            $start = substr((string) $year,       -2);
            $end   = substr((string) ($year + 1), -2);
        } else {
            $start = substr((string) ($year - 1), -2);
            $end   = substr((string) $year,        -2);
        }

        return $start . $end; // e.g. "2526"
    }

    /**
     * Generate the next PO ref-no for a given plant.
     * Uses the model's generateNextRefId() which is already plant-scoped.
     */
    public static function generatePurchaseOrderRefNo($plantId, string $date = null): array
    {
        $fy        = self::getFinancialYear();
        $formatted = PurchaseOrder::generateNextRefId($plantId, $date);
// dd($formatted);
        // Parse the numeric sequence from the formatted ref (e.g. "PO-2526-0007" → 7)
        preg_match('/(\d+)$/', $formatted['ref_no'], $m);
        $seq = isset($m[1]) ? (int) $m[1] : 1;

        return [
            'ref_no'         => str_pad($seq, 4, '0', STR_PAD_LEFT),
            'financial_year' => $fy,
            'formatted'      => $formatted['ref_no'],
            'prefix'         =>  $formatted['prefix']
        ];
    }
}