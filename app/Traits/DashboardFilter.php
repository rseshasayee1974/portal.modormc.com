<?php

namespace App\Traits;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;

trait DashboardFilter
{
    public function applyFilters(Builder $query, array $filters, string $dateColumn = 'created_at')
    {
        app(\App\Services\DashboardPlantAccess::class)->scope($query, $query->getModel()->getTable() . '.plant_id', $filters);

        if (isset($filters['type'])) {
            switch ($filters['type']) {
                case 'daily':
                    if (isset($filters['from_date']) && isset($filters['to_date'])) {
                        $query->whereBetween($dateColumn, [
                            Carbon::parse($filters['from_date'])->startOfDay(),
                            Carbon::parse($filters['to_date'])->endOfDay(),
                        ]);
                    }
                    break;
                case 'today':
                    $query->whereDate($dateColumn, Carbon::today());
                    break;
                case 'yesterday':
                    $query->whereDate($dateColumn, Carbon::yesterday());
                    break;
                case 'this_week':
                    $query->whereBetween($dateColumn, [Carbon::now()->startOfWeek(), Carbon::now()->endOfWeek()]);
                    break;
                case 'last_week':
                    $query->whereBetween($dateColumn, [Carbon::now()->subWeek()->startOfWeek(), Carbon::now()->subWeek()->endOfWeek()]);
                    break;
                default:
                    if (isset($filters['from_date']) && isset($filters['to_date'])) {
                        $query->whereBetween($dateColumn, [
                            Carbon::parse($filters['from_date'])->startOfDay(),
                            Carbon::parse($filters['to_date'])->endOfDay(),
                        ]);
                    }
                    break;
            }
        } elseif (isset($filters['from_date']) && isset($filters['to_date'])) {
            $query->whereBetween($dateColumn, [
                Carbon::parse($filters['from_date'])->startOfDay(),
                Carbon::parse($filters['to_date'])->endOfDay(),
            ]);
        }

        return $query;
    }
}
