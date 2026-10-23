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
                        ->where(function ($scope) use ($user) {
                            $scope->whereColumn('mm_entity_users.plant_id', 'mm_plants.id')
                                ->orWhere(function ($entityWide) use ($user) {
                                    $entityWide->whereNull('mm_entity_users.plant_id')
                                        ->where(function ($access) use ($user) {
                                            $access->whereExists(function ($role) {
                                                $role->selectRaw('1')->from('mm_roles')
                                                    ->whereColumn('mm_roles.id', 'mm_entity_users.role_id')
                                                    ->whereNull('mm_roles.deleted_at')
                                                    ->whereIn('mm_roles.code', ['ADMINISTRATOR', 'SUPER_ADMIN', 'PLATFORM_ADMIN', 'SAAS_OWNER']);
                                            })->orWhereExists(function ($personnel) use ($user) {
                                                $personnel->selectRaw('1')->from('mm_personnels')
                                                    ->where('mm_personnels.user_id', $user->id)
                                                    ->whereNull('mm_personnels.deleted_at')
                                                    ->whereColumn('mm_personnels.plant_id', 'mm_plants.id');
                                            });
                                        });
                                });
                        });
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
        // Mobile records are personal for every role, including administrators.
        return false;
                // return !self::isSalesExecutive() && self::permits($module, 'VIEW_ALL');

    }

    public static function commercialOwnerId(?int $existingOwner = null): ?int
    {
        $id = self::personnelId();
        abort_if($id === null, 403, 'A personnel assignment in this plant is required.');
        abort_if($existingOwner !== null && $existingOwner !== $id, 403, 'Mobile documents must belong to the logged-in user.');
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
            ->where('created_by', (int) request()->user()?->id);
    }

    public static function activities(): Builder
    {
        return self::plantQuery(CrmActivity::class)
            ->whereIn('lead_id', self::leads()->select('id'))
            ->where(fn (Builder $owner) => $owner->where('assigned_to', (int) request()->user()->id)->orWhereNull('assigned_to'));
    }

    public static function quotations(): Builder
    {
        return self::plantQuery(Quotation::class)->where('created_by', (int) request()->user()?->id);
    }

    public static function leadQuotationIds(): Builder
    {
        return self::plantQuery(\App\Models\CrmDeal::class)
            ->whereIn('lead_id', self::leads()->select('id'))->select('quotation_id');
    }

    public static function orders(): Builder
    {
        return self::isCommercialAdministrator()
            ? self::plantQuery(SalesOrder::class)
            : self::commercial(SalesOrder::class, 'SALES_ORDER');
    }

    public static function customerPOs(): Builder
    {
        return self::isCommercialAdministrator()
            ? self::plantQuery(CustomerPO::class)
            : self::commercial(CustomerPO::class, 'SALES_ORDER');
    }

    public static function orderOwnerId(?int $existingOwner = null, bool $updating = false): ?int
    {
        return self::isCommercialAdministrator()
            ? ($updating ? $existingOwner : self::personnelId())
            : self::commercialOwnerId($existingOwner);
    }

    public static function isCommercialAdministrator(): bool
    {
        $role = request()->attributes->get('mobile_role');
        return request()->attributes->get('mobile_app_context') && (
            request()->attributes->get('mobile_system_admin') || in_array(strtoupper((string) $role?->code), [
                'ADMINISTRATOR', 'SUPER_ADMIN', 'PLATFORM_ADMIN', 'SAAS_OWNER',
            ], true)
        );
    }

    public static function customerPOQuotations(): Builder
    {
        return self::isCommercialAdministrator() ? self::plantQuery(Quotation::class) : self::quotations();
    }

    /** Shared customer reference data; this query does not grant master-data writes. */
    public static function customers(): Builder
    {
        return self::plantQuery(Patron::class)->ofType('Customer');
    }

    /** Sites are shared reference data within the selected plant. */
    public static function sites(): Builder
    {
        return self::plantQuery(Site::class);
    }

    public static function plantSites(): Builder
    {
        return self::sites();
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
        $personnelId = self::personnelId();
        // Creator and VIEW_ALL permissions never broaden mobile ownership.
        return $personnelId === null
            ? $query->whereRaw('1 = 0')
            : $query->where('sales_executive_id', $personnelId);
    }

}
