<?php

namespace App\Traits;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder as EloquentBuilder;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Support\Facades\Schema;

trait DashboardFilter
{
    public function applyFilters(EloquentBuilder|QueryBuilder $query, array $filters, string $dateColumn = 'created_at')
    {
        $table = $query instanceof EloquentBuilder ? $query->getModel()->getTable() : 'mm_dispatches';
        $plantColumn = str_contains($table, '.') ? "{$table}.plant_id" : "{$table}.plant_id";

        if (Schema::hasTable('mm_plants')) {
            app(\App\Services\DashboardPlantAccess::class)->scope($query, $plantColumn, $filters);
        } elseif (isset($filters['plant_id']) && $filters['plant_id'] !== '') {
            $query->where($plantColumn, $filters['plant_id']);
        }

        $this->applyDateFiltersToQuery($query, $filters, $dateColumn, $table);

        return $query;
    }

    public function applyDateFiltersToQuery(EloquentBuilder|QueryBuilder $query, array $filters, string $dateColumn = 'created_at', ?string $table = null): EloquentBuilder|QueryBuilder
    {
        $table ??= ($query instanceof EloquentBuilder ? $query->getModel()->getTable() : null);
        $colName = str_contains($dateColumn, '.') ? explode('.', $dateColumn)[1] : $dateColumn;

        if ($table && Schema::hasTable($table) && !Schema::hasColumn($table, $colName)) {
            return $query;
        }

        $qualifiedColumn = str_contains($dateColumn, '.') || !$table ? $dateColumn : "{$table}.{$dateColumn}";

        $fromDate = $filters['from_date'] ?? $filters['start_date'] ?? null;
        $toDate = $filters['to_date'] ?? $filters['end_date'] ?? null;

        if (isset($filters['type'])) {
            switch ($filters['type']) {
                case 'daily':
                    if ($fromDate && $toDate) {
                        $query->whereBetween($qualifiedColumn, [
                            Carbon::parse($fromDate)->startOfDay(),
                            Carbon::parse($toDate)->endOfDay(),
                        ]);
                    } elseif ($fromDate) {
                        $query->where($qualifiedColumn, '>=', Carbon::parse($fromDate)->startOfDay());
                    } elseif ($toDate) {
                        $query->where($qualifiedColumn, '<=', Carbon::parse($toDate)->endOfDay());
                    }
                    break;
                case 'today':
                    $query->whereDate($qualifiedColumn, Carbon::today());
                    break;
                case 'yesterday':
                    $query->whereDate($qualifiedColumn, Carbon::yesterday());
                    break;
                case 'this_week':
                    $query->whereBetween($qualifiedColumn, [Carbon::now()->startOfWeek(), Carbon::now()->endOfWeek()]);
                    break;
                case 'last_week':
                    $query->whereBetween($qualifiedColumn, [Carbon::now()->subWeek()->startOfWeek(), Carbon::now()->subWeek()->endOfWeek()]);
                    break;
                default:
                    if ($fromDate && $toDate) {
                        $query->whereBetween($qualifiedColumn, [
                            Carbon::parse($fromDate)->startOfDay(),
                            Carbon::parse($toDate)->endOfDay(),
                        ]);
                    } elseif ($fromDate) {
                        $query->where($qualifiedColumn, '>=', Carbon::parse($fromDate)->startOfDay());
                    } elseif ($toDate) {
                        $query->where($qualifiedColumn, '<=', Carbon::parse($toDate)->endOfDay());
                    }
                    break;
            }
        } elseif ($fromDate && $toDate) {
            $query->whereBetween($qualifiedColumn, [
                Carbon::parse($fromDate)->startOfDay(),
                Carbon::parse($toDate)->endOfDay(),
            ]);
        } elseif ($fromDate) {
            $query->where($qualifiedColumn, '>=', Carbon::parse($fromDate)->startOfDay());
        } elseif ($toDate) {
            $query->where($qualifiedColumn, '<=', Carbon::parse($toDate)->endOfDay());
        }

        return $query;
    }
}
