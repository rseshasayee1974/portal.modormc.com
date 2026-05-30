<?php

namespace App\Traits;

use App\Services\PlantContextService;
use Illuminate\Database\Eloquent\Builder;

/**
 * Automatically applies plant_id scoping to models.
 *
 * This ensures that users only see and interact with data
 * belonging to their currently active plant session.
 *
 * Resolution order (via PlantContextService):
 *   1. session('active_plant_id')
 *   2. auth()->user()->default_plant_id
 *   3. null  →  no scope applied (guest / setup routes)
 *
 * @method static void addGlobalScope(string $identifier, \Closure|\Illuminate\Database\Eloquent\Scope $scope)
 * @method static void creating(\Closure|string $callback)
 * @method static \Illuminate\Database\Eloquent\Builder query()
 * @mixin \Illuminate\Database\Eloquent\Model
 */
trait PlantScoping
{
    /**
     * Boot the trait and apply the global scope.
     */
    public static function bootPlantScoping()
    {
        // 1. Automatic Query Filtering
        static::addGlobalScope('plant_id', function (Builder $builder) {
            /** @var PlantContextService $ctx */
            $ctx = app(PlantContextService::class);
            $plantId = $ctx->plantId();

            // Apply scope only if a plant is resolved
            if ($plantId) {
                $builder->where($builder->getModel()->getTable() . '.plant_id', $plantId);
            }
        });

        // 2. Automatic Data Tagging on creation
        static::creating(function ($model) {
            /** @var PlantContextService $ctx */
            $ctx = app(PlantContextService::class);

            if (!$model->plant_id) {
                $plantId = $ctx->plantId();
                if ($plantId) {
                    $model->plant_id = $plantId;
                }
            }

            // Also sync entity_id if the model has it
            if (isset($model->entity_id) && !$model->entity_id) {
                $entityId = $ctx->entityId();
                if ($entityId) {
                    $model->entity_id = $entityId;
                }
            }
        });
    }

    /**
     * Helper to bypass the scope if needed (e.g. for cross-plant reports for admins)
     */
    public static function withoutPlantScope()
    {
        return static::query()->withoutGlobalScope('plant_id');
    }
}
