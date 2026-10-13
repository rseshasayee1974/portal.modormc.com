<?php
/*
Author: ragul-onemodo
Created: 2026-10-09 17:59:50 Asia/Calcutta (UTC+05:30)
*/

namespace App\Services\Mobile;

use App\Models\{EntityUser, Plant, User};
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Str;

class MobileAccess
{
    public static function canManageUserAccess(User $user): bool
    {
        if ($user->isSystemAdmin()) return true;
        $entity = (int) session('active_entity_id');
        if (!$entity) return false;
        $role = EntityUser::forContext($user->id, $entity, session('active_plant_id'))?->role;
        return in_array(strtoupper((string) $role?->code), [
            'ADMINISTRATOR',
            'SUPER_ADMIN',
            'PLATFORM_ADMIN',
            'SAAS_OWNER',
        ], true);
    }

    public static function plants(User $user): Builder
    {
        if ($user->isSystemAdmin()) return Plant::query();

        return Plant::query()->where('is_active', 1)
            ->whereHas('entity', function ($q) use ($user) {
                if (!$user->isSystemAdmin()) $q->where('is_suspended', 0);
            })
            ->when(!$user->isSystemAdmin(), function ($q) use ($user) {
                $q->whereExists(function ($membership) use ($user) {
                    $membership->selectRaw('1')->from('mm_entity_users')->where('user_id', $user->id)
                        ->whereNull('mm_entity_users.deleted_at')->whereColumn('mm_entity_users.entity_id', 'mm_plants.entity_id')
                        ->where(fn ($m) => $m->whereNull('mm_entity_users.plant_id')->orWhereColumn('mm_entity_users.plant_id', 'mm_plants.id'));
                });
            });
    }

    public static function permits(string $module, string $action): bool
    {
        if (request()->attributes->get('mobile_system_admin')) return true;
        $names = collect([strtoupper(Str::singular($module)).'.'.strtoupper($action), strtoupper(Str::plural($module)).'.'.strtoupper($action)]);
        $role = request()->attributes->get('mobile_role');
        $permissions = ($role ? $role->permissions->pluck('name') : collect())
            ->merge(request()->user()->getDirectPermissions()->pluck('name'))->map(fn ($name) => strtoupper($name));
        return $names->intersect($permissions)->isNotEmpty();
    }

    public static function authorize(string $module, string $action): void
    {
        abort_unless(self::permits($module, $action), 403, 'Required permission: '.strtoupper(Str::singular($module)).'.'.strtoupper($action));
    }

    public static function plantId(): int { return (int) request()->attributes->get('mobile_plant_id'); }
    public static function personnelId(): ?int { return request()->user()->personnel?->id; }
    public static function all(string $module): bool { return self::permits($module, 'VIEW_ALL'); }

    public static function leads(): Builder
    {
        return \App\Models\CrmLead::where('plant_id', self::plantId())
            ->when(!self::all('CRM_LEAD'), fn ($q) => $q->where('assigned_to', request()->user()->id));
    }

    public static function quotations(): Builder
    {
        return self::commercial(\App\Models\Quotation::query(), 'QUOTATION');
    }

    public static function orders(): Builder
    {
        return self::commercial(\App\Models\SalesOrder::query(), 'SALES_ORDER');
    }

    private static function commercial(Builder $query, string $module): Builder
    {
        return $query->where('plant_id', self::plantId())->when(!self::all($module), function ($q) {
            $q->where(function ($owner) {
                $owner->where('created_by', request()->user()->id);
                if ($id = self::personnelId()) $owner->orWhere('sales_executive_id', $id);
            });
        });
    }

    public static function schedules(): Builder
    {
        return \App\Models\ConcreteBatchingSchedule::where('plant_id', self::plantId())
            ->whereIn('sales_order_id', self::orders()->select('mm_sales_orders.id'));
    }
}
