<?php

namespace App\Traits;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Session;

/**
 * Automatically applies plant_id scoping to models.
 * 
 * This ensures that users only see and interact with data
 * belonging to their currently active plant session.
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
            $plantId = session('active_plant_id');
            
            // Apply scope only if a plant is selected in the session
            if ($plantId) {
                $builder->where($builder->getModel()->getTable() . '.plant_id', $plantId);
            }
        });

        // 2. Automatic Data Tagging
        static::creating(function ($model) {
            if (!$model->plant_id && session('active_plant_id')) {
                $model->plant_id = session('active_plant_id');
            }
            
            // Also try to sync entity_id if the model has it
            if (isset($model->entity_id) && !$model->entity_id && session('active_entity_id')) {
                $model->entity_id = session('active_entity_id');
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
