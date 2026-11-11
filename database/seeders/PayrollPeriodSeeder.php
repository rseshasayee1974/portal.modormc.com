<?php

//
// Author: Cipher
// Created: 2026-11-11 10:03:27 Asia/Calcutta (UTC+05:30)
//

namespace Database\Seeders;

use Carbon\CarbonImmutable;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class PayrollPeriodSeeder extends Seeder
{
    public function run(?int $plantId = null): void
    {
        DB::table('mm_plants')
            ->whereNull('deleted_at')
            ->when($plantId, fn ($query) => $query->where('id', $plantId))
            ->orderBy('id')
            ->each(function ($plant): void {
            $salaryCoverage = DB::table('mm_employee_salary_structures as structures')
                ->join('mm_personnels as personnel', 'personnel.id', '=', 'structures.personnel_id')
                ->where('personnel.plant_id', $plant->id)
                ->whereNull('personnel.deleted_at')
                ->whereNull('structures.deleted_at')
                ->selectRaw('MIN(effective_from) as first_date, MAX(COALESCE(effective_to, ?)) as last_date', [now()->endOfYear()->toDateString()])
                ->first();

            if (!$salaryCoverage?->first_date) {
                return;
            }

            $firstMonth = CarbonImmutable::parse($salaryCoverage->first_date)->startOfMonth();
            $lastMonth = CarbonImmutable::parse($salaryCoverage->last_date)->startOfMonth();

            $existingPeriods = DB::table('mm_payroll_periods')
                ->where('plant_id', $plant->id)
                ->whereNull('deleted_at')
                ->get(['from_date', 'to_date']);

            for ($month = $firstMonth; $month <= $lastMonth; $month = $month->addMonth()) {
                $fromDate = $month->startOfMonth()->toDateString();
                $toDate = $month->endOfMonth()->toDateString();

                $hasApplicableSalary = DB::table('mm_employee_salary_structures as structures')
                    ->join('mm_personnels as personnel', 'personnel.id', '=', 'structures.personnel_id')
                    ->where('personnel.plant_id', $plant->id)
                    ->whereNull('personnel.deleted_at')
                    ->whereNull('structures.deleted_at')
                    ->whereDate('structures.effective_from', '<=', $toDate)
                    ->where(function ($query) use ($fromDate) {
                        $query->whereNull('structures.effective_to')
                            ->orWhereDate('structures.effective_to', '>=', $fromDate);
                    })
                    ->exists();

                if (!$hasApplicableSalary) {
                    continue;
                }

                $alreadyCovered = $existingPeriods->contains(function ($period) use ($fromDate, $toDate): bool {
                    return $period->from_date <= $fromDate && $period->to_date >= $toDate;
                });

                if ($alreadyCovered) {
                    continue;
                }

                DB::table('mm_payroll_periods')->insert([
                    'plant_id' => $plant->id,
                    'name' => $month->format('M-Y'),
                    'from_date' => $fromDate,
                    'to_date' => $toDate,
                    'status' => 'draft',
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        });
    }
}
