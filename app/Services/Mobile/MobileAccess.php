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
        return $role?->grantsFullAccess() ?? false;
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
        $user = request()->user();
        $plantId = self::plantId();
        if (!$user || !$plantId) return false;

        $moduleNames = [strtoupper(Str::singular($module)), strtoupper(Str::plural($module))];
        $action = strtoupper($action);
        $action = match ($action) {
            'SHOW', 'LISTING', 'MENU' => 'VIEW',
            'EDIT', 'UPDATE' => 'UPDATE',
            'STORE', 'CREATE' => 'CREATE',
            'DESTROY', 'DELETE' => 'DELETE',
            default => $action,
        };

        return \App\Models\MobilePermission::whereIn('module', $moduleNames)
            ->where('action', $action)->exists()
            && \App\Models\MobileModulePermission::where('user_id', $user->id)
            ->where('plant_id', $plantId)->whereIn('module', $moduleNames)
            ->whereJsonContains('actions', $action)->exists();
    }

    public static function catalogPermits(string $module, string $action): bool
    {
        $moduleNames = [strtoupper(Str::singular($module)), strtoupper(Str::plural($module))];
        return \App\Models\MobilePermission::whereIn('module', $moduleNames)
            ->where('action', strtoupper($action))->exists();
    }

    public static function permissionsForPlant(User $user, int $plantId): array
    {
        $grants = \App\Models\MobileModulePermission::where('user_id', $user->id)
            ->where('plant_id', $plantId)->get()->keyBy('module');
        if ($grants->isEmpty()) return [];

        $permissions = \App\Models\MobilePermission::whereIn('module', $grants->keys())->get(['module', 'action', 'name'])
            ->filter(fn ($permission) => in_array($permission->action, $grants->get($permission->module)?->actions ?? [], true))
            ->pluck('name')->unique()->sort()->values()->all();

        return $permissions;
    }

    /** Flat permission keys grouped with plant IDs for login/bootstrap responses. */
    public static function permissionsByPlant(User $user): array
    {
        return self::plants($user)->get(['id', 'name', 'entity_id'])->map(fn ($plant) => [
            'plant_id' => (int) $plant->id,
            'plant_name' => $plant->name,
            'permissions' => self::permissionsForPlant($user, (int) $plant->id),
        ])->values()->all();
    }

    public static function authorizeRoute(\Illuminate\Http\Request $request): void
    {
        $path = trim($request->path(), '/');
        $path = preg_replace('~^api/mobile/(?:v1/)?~', '', $path);
        $segments = explode('/', $path);
        $resource = $segments[0] ?? '';
        $module = match ($resource) {
            'users', 'user' => 'USER',
            'dashboard' => 'DASHBOARD',
            'sales-summary' => 'SALES_SUMMARY',
            'sales-details' => 'SALES_DETAILS',
            'customer-details' => 'CUSTOMER_DETAILS',
            'top-mix-designs', 'mix-designs' => 'MIX_DESIGN',
            'truck-dispatch-details', 'dispatch-details' => 'DISPATCH',
            'dispatch-batching-summary' => 'DISPATCH',
            'batch-schedules' => 'BATCHING_SCHEDULE',
            'stock-details' => 'STOCK',
            'leads', 'follow-ups' => 'CRM_LEAD',
            'quotations' => 'QUOTATION',
            'customer-pos' => 'CUSTOMER_PO',
            'orders' => 'SALES_ORDER',
            'attendances' => 'ATTENDANCE',
            'payslips' => 'PAYSLIP',
            'customers' => 'PATRON',
            'dropdowns', 'lookups' => null,
            'sites' => 'SITE',
            'taxes' => 'TAX',
            'sales-executives' => 'PERSONNEL',
            'personnel' => 'PERSONNEL',
            'marketing' => 'MARKETING',
            'notifications' => 'NOTIFICATION',
            default => null,
        };
        if ($resource === 'dropdowns' || $resource === 'lookups') {
            abort_unless(self::catalogPermits('PATRON', 'VIEW'), 403, 'Required permission: PATRON.VIEW');
            $grantedLookup = false;
            foreach (['PATRON', 'SITE', 'MIX_DESIGN', 'TAX', 'PERSONNEL'] as $lookupModule) {
                $grantedLookup = self::permits($lookupModule, 'VIEW') || $grantedLookup;
            }
            abort_unless($grantedLookup, 403, 'At least one mobile reference data module permission is required.');
            return;
        }
        if (!$module) return;

        $action = match (strtoupper($request->method())) {
            'GET', 'HEAD' => 'VIEW', 'POST' => 'CREATE', 'PUT', 'PATCH' => 'UPDATE', 'DELETE' => 'DELETE', default => null,
        };
        $tail = implode('/', array_slice($segments, 1));
        if ($module === 'CRM_LEAD' && preg_match('~/(approve)$~', '/'.$tail)) $action = 'APPROVE';
        if ($module === 'CRM_LEAD' && preg_match('~/(assign)$~', '/'.$tail)) $action = 'ASSIGN';
        if ($module === 'CRM_LEAD' && preg_match('~/(convert)$~', '/'.$tail)) $action = 'CONVERT';
        if ($module === 'CRM_LEAD' && $resource === 'follow-ups' && str_contains($tail, 'complete')) $action = 'UPDATE';
        if ($module === 'QUOTATION' && str_contains($tail, 'review')) $action = 'APPROVE';
        if ($module === 'QUOTATION' && str_contains($tail, 'convert-customer-po')) $action = 'CONVERT';
        if ($module === 'QUOTATION' && str_contains($tail, 'submit')) $action = 'UPDATE';
        if ($module === 'CUSTOMER_PO' && (str_contains($tail, 'convert-sales-order') || str_contains($tail, 'convert-salesorder'))) $action = 'CONVERT';
        if ($module === 'ATTENDANCE' && str_ends_with($path, '/pdf')) $action = 'PDF';
        if ($module === 'PAYSLIP' && str_ends_with($path, '/pdf')) $action = 'PDF';
        if ($module === 'NOTIFICATION' && in_array($action, ['CREATE', 'UPDATE'], true)) $action = 'UPDATE';

        if ($action) self::authorize($module, $action);
    }

    public static function authorize(string $module, string $action): void
    {
        $module = strtoupper(Str::singular($module));
        $action = strtoupper($action);
        if (self::permits($module, $action)) return;

        $key = $module.'.'.$action;
        throw new \Illuminate\Http\Exceptions\HttpResponseException(response()->json([
            'success' => false,
            'error_code' => 'MOBILE_PERMISSION_REQUIRED',
            'message' => "Access to this mobile feature requires {$key}.",
            'required_permission' => ['module' => $module, 'action' => $action, 'key' => $key],
            'hint' => 'Ask your administrator to grant this permission for the selected plant.',
        ], 403));
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
        return self::isCommercialAdministrator()
            ? self::plantQuery(CrmLead::class)
            : self::plantQuery(CrmLead::class)->where('created_by', (int) request()->user()?->id);
    }

    public static function activities(): Builder
    {
        if (self::isCommercialAdministrator()) {
            return self::plantQuery(CrmActivity::class);
        }

        return self::plantQuery(CrmActivity::class)
            ->whereIn('lead_id', self::leads()->select('id'))
            ->where(fn (Builder $owner) => $owner->where('assigned_to', (int) request()->user()->id)->orWhereNull('assigned_to'));
    }

    public static function quotations(): Builder
    {
        return self::isCommercialAdministrator()
            ? self::plantQuery(Quotation::class)
            : self::plantQuery(Quotation::class)->where('created_by', (int) request()->user()?->id);
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

    public static function customerPOsForReading(): Builder
    {
        // Main-app PO viewers and administrators have the same plant-wide directory as the website.
        // Keep non-admin sales app and mutation queries restricted to ownership.
        return self::isCommercialAdministrator() || self::canReadPlantCustomerPOs()
            ? self::plantQuery(CustomerPO::class)
            : self::customerPOs();
    }

    public static function customerPOSalesOrdersForReading(): Builder
    {
        return self::isCommercialAdministrator() || self::canReadPlantCustomerPOs()
            ? self::plantQuery(SalesOrder::class)
            : self::orders();
    }

    private static function canReadPlantCustomerPOs(): bool
    {
        return (bool) request()->attributes->get('mobile_app_context')
            && self::permits('CUSTOMER_PO', 'VIEW');
    }

    public static function orderOwnerId(?int $existingOwner = null, bool $updating = false, mixed $requestedOwner = null, bool $hasRequested = false, ?int $inheritedOwner = null): ?int
    {
        if (self::isCommercialAdministrator()) {
            if ($hasRequested) {
                return $requestedOwner ? (int) $requestedOwner : null;
            }

            return $updating ? $existingOwner : $inheritedOwner;
        }

        return self::commercialOwnerId($existingOwner);
    }

    public static function isCommercialAdministrator(): bool
    {
        $user = request()->user();
        if (!$user) {
            return false;
        }

        if (request()->attributes->get('mobile_system_admin') || $user->hasFullAccess() || $user->isSystemAdmin()) {
            return true;
        }

        $role = request()->attributes->get('mobile_role');
        if ($role?->isAdministrator() || $role?->grantsFullAccess()) {
            return true;
        }

        $code = strtoupper(trim((string) $role?->code));
        return in_array($code, ['ADMINISTRATOR', 'ADMIN', 'SUPER_ADMIN', 'PLATFORM_ADMIN', 'SAAS_OWNER'], true);
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
