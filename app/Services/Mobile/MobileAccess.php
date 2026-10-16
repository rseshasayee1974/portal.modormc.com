<?php
/*
Author: ragul-onemodo
Created: 2026-10-09 17:59:50 Asia/Calcutta (UTC+05:30)
*/

namespace App\Services\Mobile;

use App\Models\{EntityUser, Plant, User, ConcreteBatchingSchedule, CrmActivity, CrmLead, CustomerPO, Patron, Quotation, SalesOrder, Site};
use Illuminate\Database\Eloquent\{Builder, Model};
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Exists;

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
        if ($user->isSystemAdmin()) return Plant::where('is_active', 1);

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

    public static function plantId(): int
    {
        return (int) request()->attributes->get('mobile_plant_id');
    }

    public static function personnelId(): ?int
    {
        $request = request();
        $user = $request->user();
        $plantId = (int) $request->attributes->get('mobile_plant_id');
        if (!$user || !$plantId) return null;

        // Request-local cache, keyed by user and plant; safe across plant switches
        // and long-lived workers without sharing authorization state.
        $key = 'mobile_personnel:'.$user->id.':'.$plantId;
        if (!$request->attributes->has($key)) {
            $id = $user->personnel()->withoutGlobalScope('plant_id')
                ->where('plant_id', $plantId)->value('id');
            $request->attributes->set($key, $id === null ? null : (int) $id);
        }
        return $request->attributes->get($key);
    }

    public static function isSalesExecutive(): bool
    {
        if (request()->attributes->has('mobile_sales_executive')) {
            return request()->attributes->get('mobile_sales_executive');
        }
        $role = request()->attributes->get('mobile_role');
        $code = strtoupper(trim((string) ($role->code ?? '')));
        return $code === 'SALES_EXECUTIVE'
            || ($code === '' && Str::snake((string) ($role->name ?? '')) === 'sales_executive');
    }

    public static function all(string $module): bool
    {
        return !self::isSalesExecutive() && self::permits($module, 'VIEW_ALL');
    }

    public static function commercialOwnerId(?int $existingOwner = null): ?int
    {
        if ($existingOwner !== null) return $existingOwner;
        $id = self::personnelId();
        abort_if(self::isSalesExecutive() && $id === null, 403, 'A personnel assignment in this plant is required.');
        return $id;
    }

    /** Validate a foreign key with exactly the same visibility as its endpoint. */
    public static function exists(Builder $query): Exists
    {
        $model = $query->getModel();
        return Rule::exists($model->getTable(), $model->getKeyName())
            ->where(fn ($validation) => $validation->whereIn($model->getKeyName(),
                (clone $query)->select($model->getQualifiedKeyName())));
    }

    public static function leads(): Builder
    {
        return self::plantQuery(CrmLead::class)
            ->when(!self::all('CRM_LEAD'), fn (Builder $query) => $query->where('assigned_to', (int) request()->user()?->id));
    }

    public static function activities(): Builder
    {
        return self::plantQuery(CrmActivity::class)
            ->whereIn('lead_id', self::leads()->select('id'));
    }

    public static function quotations(): Builder
    {
        return self::commercial(Quotation::class, 'QUOTATION');
    }

    public static function orders(): Builder
    {
        return self::commercial(SalesOrder::class, 'SALES_ORDER');
    }

    public static function customerPOs(): Builder
    {
        return self::commercial(CustomerPO::class, 'SALES_ORDER');
    }

    public static function customers(): Builder
    {
        return self::plantQuery(Patron::class)->ofType('Customer')
            ->when(!self::all('PATRON'), function (Builder $query) {
                $query->where(function (Builder $related) {
                    // Direct assignments allow the first quotation/order before
                    // the customer has any commercial history.
                    if (($personnelId = self::personnelId()) !== null) {
                        $related->where('sales_executive_id', $personnelId);
                    }
                    $related->orWhereIn('id', self::leads()->select('customer_id'))
                        ->orWhereIn('id', self::orders()->select('customer_id'))
                        ->orWhereIn('id', self::quotations()->select('patron_id'))
                        ->orWhereIn('id', self::customerPOs()->select('patron_id'));
                });
            });
    }

    public static function sites(): Builder
    {
        // Preserve existing plant-wide site access for management roles.
        return self::plantQuery(Site::class)
            ->when(self::isSalesExecutive(), fn (Builder $query) => $query->where(fn (Builder $related) => $related
                ->whereIn('patron_id', self::customers()->select('id'))
                ->orWhereIn('id', self::orders()->select('site_id'))
                ->orWhereIn('id', self::quotations()->select('site_id'))
                ->orWhereIn('id', self::customerPOs()->select('site_id'))));
    }

    public static function schedules(): Builder
    {
        return self::plantQuery(ConcreteBatchingSchedule::class)
            ->whereIn('sales_order_id', self::orders()->select('id'));
    }

    /** @param class-string<Model> $model */
    private static function plantQuery(string $model): Builder
    {
        $query = $model::query();

        // The explicit mobile context is authoritative, independent of browser
        // defaults or a previously resolved global plant context.
        return $query->withoutGlobalScope('plant_id')
            ->where($query->getModel()->qualifyColumn('plant_id'), self::plantId());
    }

    /** @param class-string<Model> $model */
    private static function commercial(string $model, string $module): Builder
    {
        $query = self::plantQuery($model);
        if (self::all($module)) return $query;

        $personnelId = self::personnelId();
        if (self::isSalesExecutive()) {
            // Missing personnel must never match unassigned records (NULL).
            return $personnelId === null
                ? $query->whereRaw('1 = 0')
                : $query->where('sales_executive_id', $personnelId);
        }

        return $query->where(function (Builder $owner) use ($personnelId) {
            $owner->where('created_by', (int) request()->user()?->id);
            if ($personnelId !== null) {
                $owner->orWhere('sales_executive_id', $personnelId);
            }
        });
    }

}
