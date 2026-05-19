<?php

namespace App\Traits;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;

trait DashboardFilter
{
    public function applyFilters(Builder $query, array $filters, string $dateColumn = 'created_at')
    {
        $user = auth()->user();
        
        if ($user && !$user->isSystemAdmin()) {
            // Get all plant IDs this user is authorized to view
            $authorizedPlantIds = $user->entityUsers()
                ->whereNotNull('plant_id')
                ->pluck('plant_id')
                ->unique()
                ->toArray();

            if (isset($filters['plant_id'])) {
                $requestedId = (int)$filters['plant_id'];
                if (in_array($requestedId, $authorizedPlantIds)) {
                    $query->where($query->getModel()->getTable() . '.plant_id', $requestedId);
                } else {
                    // Force zero results if requesting unauthorized plant
                    $query->whereRaw('1 = 0');
                }
            } else {
                // Default to all authorized plants
                $query->whereIn($query->getModel()->getTable() . '.plant_id', $authorizedPlantIds);
            }
        } elseif (isset($filters['plant_id'])) {
            $query->where($query->getModel()->getTable() . '.plant_id', $filters['plant_id']);
        }

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
