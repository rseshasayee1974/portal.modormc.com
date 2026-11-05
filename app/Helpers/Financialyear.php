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
    public static function generatePurchaseOrderRefNo($plantId, ?string $date = null): array
    {
        $formatted = PurchaseOrder::generateNextRefId($plantId, $date);

        return [
            'ref_no'         => $formatted['next_number'],
            'financial_year' => $formatted['financial_year'],
            'formatted'      => $formatted['ref_no'],
            'prefix'         =>  $formatted['prefix']
        ];
    }
}
