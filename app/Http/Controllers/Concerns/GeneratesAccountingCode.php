<?php

namespace App\Http\Controllers\Concerns;

use Illuminate\Support\Facades\DB;

trait GeneratesAccountingCode
{
    /**
     * Account category ranges.
     */
    protected array $ranges = [
        'EQUITY'    => [3000, 3999],
        'LIABILITY' => [2000, 2999],
        'ASSET'     => [1000, 1999],
        'INCOME'    => [4000, 4999],
        'EXPENSE'   => [5000, 5999],
    ];

    /**
     * Generate the next available code within a specific category range, scoped by entity.
     * 
     * @param string $category 
     * @param string $table 
     * @param int|null $plantId
     * @return string
     */
    public function generateNextCode(string $category, string $table, $plantId = null): string
    {
        $plantId = $plantId ?? session('active_plant_id');
        $category = strtoupper($category);
        
        if (!isset($this->ranges[$category])) {
            return '0000';
        }

        [$start, $end] = $this->ranges[$category];

        // Find max in range, specifically for this entity and non-deleted records
        $maxAccount = DB::table('mm_accounts')
            // ->where('plant_id', $plantId)
            ->whereNull('deleted_at')
            ->whereBetween('code', [$start, $end])
            ->max('code');
            
        $maxType = DB::table('mm_account_types')
            ->where('plant_id', $plantId)
            ->whereNull('deleted_at')
            ->whereBetween('code', [$start, $end])
            ->max('code');
            
        $maxLedger = DB::table('mm_ledgers')
            ->where('plant_id', $plantId)
            ->whereNull('deleted_at')
            ->whereBetween('code', [$start, $end])
            ->max('code');

        $currentMax = max((int)$maxAccount, (int)$maxType, (int)$maxLedger);

        if ($currentMax === 0 || $currentMax < $start) {
            return (string)$start;
        }

        if ($currentMax >= $end) {
            return (string)$currentMax; 
        }

        return (string)($currentMax + 1);
    }

    /**
     * Validate that a code is within the allowed range for a category.
     */
    public function validateCodeRange(string $code, string $category): bool
    {
        $category = strtoupper($category);
        if (!isset($this->ranges[$category])) return false;
        
        [$start, $end] = $this->ranges[$category];
        $intCode = (int)$code;
        
        return $intCode >= $start && $intCode <= $end;
    }
}
