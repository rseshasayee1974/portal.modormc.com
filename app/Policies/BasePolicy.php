<?php

namespace App\Policies;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Auth\Access\HandlesAuthorization;
use Illuminate\Support\Str;

abstract class BasePolicy
{
    use HandlesAuthorization;

    protected string $module;
    protected string $model;
    public ?string $modelClass = null;

    public function before(User $user, $ability)
    {
        if ($user->hasRole('super-admin') || 
            $user->hasRole('Super Admin') || 
            $user->hasRole('Platform Admin') || 
            $user->hasRole('Saas Owner')) {
            return true;
        }
    }

    public function viewAny(User $user, ?string $modelClass = null): bool
    {
        $module = $this->getModule($modelClass);
        return $this->checkPermission($user, $module, 'view');
    }

    public function view(User $user, Model $model): bool
    {
        $module = $this->getModule($model);
        return $this->checkPermission($user, $module, 'view') && $this->samePlant($user, $model);
    }

    public function create(User $user, ?string $modelClass = null): bool
    {
        $module = $this->getModule($modelClass);
        return $this->checkPermission($user, $module, 'create');
    }

    public function update(User $user, Model $model): bool
    {
        $module = $this->getModule($model);
        return $this->checkPermission($user, $module, 'update') && $this->samePlant($user, $model);
    }

    public function delete(User $user, Model $model): bool
    {
        $module = $this->getModule($model);
        
        // 1. Standard permission and plant context check
        if (!$this->checkPermission($user, $module, 'delete') || !$this->samePlant($user, $model)) {
            return false;
        }

        // 2. Business rule validation: Cannot delete if the resource is in use
        if (method_exists($model, 'getIsInUseAttribute') && $model->getIsInUseAttribute()) {
            return false;
        }

        if (isset($model->is_in_use) && $model->is_in_use) {
            return false;
        }

        return true;
    }

    protected function checkPermission(User $user, string $module, string $action): bool
    {
        $moduleUpper = strtoupper($module);
        $actionUpper = strtoupper($action);
        $actionLower = strtolower($action);

        return $user->can("{$module}.{$actionLower}") || 
               $user->can("{$moduleUpper}.{$actionUpper}") ||
               $user->can("{$module}.{$actionUpper}");
    }

    protected function getModule(Model|string|null $model = null): string
    {
        if (isset($this->module)) {
            return $this->module;
        }

        $class = null;

        // 1. Check if subclass defined $model (e.g. protected string $model = MixDesign::class)
        if (isset($this->model)) {
            $class = $this->model;
        }

        // 2. Check if instance modelClass is set (e.g. GenericPolicy resolved via guessPolicyNamesUsing)
        if (!$class && isset($this->modelClass)) {
            $class = $this->modelClass;
        }

        // 3. Check parameter passed to the policy method
        if (!$class) {
            $class = is_object($model) ? get_class($model) : $model;
        }

        // 4. Guess from Policy class name itself if it's a specific policy (e.g. ConcreteGradePolicy -> ConcreteGrade)
        if (!$class) {
            $policyName = class_basename(static::class);
            if ($policyName !== 'GenericPolicy' && str_ends_with($policyName, 'Policy')) {
                $guessedClass = 'App\\Models\\' . substr($policyName, 0, -6);
                if (class_exists($guessedClass)) {
                    $class = $guessedClass;
                }
            }
        }

        if (!$class) {
            throw new \Exception("Unable to determine model class for policy: " . static::class);
        }

        // Allow models to explicitly declare their permission module name
        // e.g. public static string $permissionModule = 'customer_po';
        $resolvedClass = is_string($class) ? $class : get_class($class);
        if (class_exists($resolvedClass) && property_exists($resolvedClass, 'permissionModule')) {
            return $resolvedClass::$permissionModule;
        }

        return Str::snake(class_basename($class));
    }

    protected function samePlant(User $user, Model $model): bool
    {
        if (!isset($model->plant_id)) {
            return true;
        }

        $activePlantId = session('active_plant_id');

        return (int) $model->plant_id === (int) $activePlantId || 
               ($user->plant_id ?? null) === $model->plant_id || 
               ($user->default_plant_id ?? null) === $model->plant_id;
    }
}
