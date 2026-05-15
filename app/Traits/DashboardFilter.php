<?php

namespace App\Traits;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;

trait DashboardFilter
{
    public function applyFilters(Builder $query, array $filters, string $dateColumn = 'created_at')
    {
        if (isset($filters['plant_id'])) {
            $query->where('plant_id', $filters['plant_id']);
        }

        if (isset($filters['type'])) {
            switch ($filters['type']) {
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
            }
        } elseif (isset($filters['from_date']) && isset($filters['to_date'])) {
            $query->whereBetween($dateColumn, [$filters['from_date'], $filters['to_date']]);
        }

        return $query;
    }
}
