<?php

namespace App\Http\Middleware;

use Illuminate\Http\Request;
use Inertia\Middleware;
use App\Models\EntityUser;
use App\Models\Entity;
use App\Models\Plant;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that's loaded on the first page visit.
     *
     * @see https://inertiajs.com/server-side-setup#root-template
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determines the current asset version.
     *
     * @see https://inertiajs.com/asset-versioning
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @see https://inertiajs.com/shared-data
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        $user = $request->user();
        $activeEntityId = session('active_entity_id');
        $systemAdmin = $user?->isSystemAdmin() ?? false;
        $assignment = null;
        $assignmentPlantId = session('active_plant_id');
        $activeEntity = null;
        if ($user && $activeEntityId) {
            $entity = Entity::find($activeEntityId);
            if (!$systemAdmin && !($entity?->is_suspended)) {
                $assignment = EntityUser::forContext($user->id, (int) $activeEntityId, $assignmentPlantId);
            }
            if ($entity && $entity->is_suspended && !$systemAdmin) {
                session()->forget(['active_entity_id', 'active_plant_id']);
                $activeEntityId = null;
            } elseif ($entity) {
                if ($systemAdmin || $assignment) {
                    $activeEntity = [
                        'entity_id' => $entity->id,
                        'entity_name' => $entity->legal_name,
                        'entity_logo' => $entity->logo_file,
                        'role_id' => $systemAdmin ? 1 : $assignment->role_id,
                        'role_name' => $systemAdmin ? 'Super Administrator' : ($assignment->role?->name ?? 'Unknown'),
                        'is_active' => true,
                        'is_suspended' => (int) $entity->is_suspended,
                    ];
                }
            }
        }

        $activePlantId = session('active_plant_id');
        $activePlant   = null;
        if ($activePlantId) {
            $plant = Plant::find($activePlantId);
            if ($plant && $plant->is_active === 1) {
                $mixerCapacity = (float)($plant->mixer_capacity ?: 1.25);
                $hasClientId = static function ($value): bool {
                    $value = trim((string) $value);
                    return $value !== '' && (!is_numeric($value) || (float) $value > 0);
                };
                $activePlant = [
                    'plant_id'       => $plant->id,
                    'plant_name'     => $plant->name,
                    'plant_code'     => $plant->code,
                    'plant_logo'     => $plant->logo_path ? "/storage/{$plant->logo_path}" : null,
                    'mixer_capacity' => $mixerCapacity,
                    'gstin'          => $plant->gstin,
                    'einvoice_enabled' => $hasClientId($plant->einvoice_client_id),
                    'ewaybill_enabled' => $hasClientId($plant->ewaybill_client_id),
                ];
                session([
                    'mixer_capacity' => $mixerCapacity,
                    'gstin'          => $plant->gstin,
                ]);
            } else {
                session()->forget(['active_plant_id', 'mixer_capacity', 'gstin']);
                $activePlantId = null;
            }
        }

        $plantsCount = fn () => $user && $activeEntityId
            ? app(\App\Services\DashboardPlantAccess::class)->query($user)->where('entity_id', $activeEntityId)->count()
            : 0;

        // Determine Tenant-Specific Role and Permissions or user - specific roles of the logged in user
        if ($user && !$systemAdmin && $activeEntityId && $assignmentPlantId !== $activePlantId) {
            $assignment = EntityUser::forContext($user->id, (int) $activeEntityId, $activePlantId);
        }
        $activeRole = $assignment?->role;
        $contextAdministrator = $activeEntityId
            ? ($systemAdmin || ($activeRole?->isAdministrator() ?? false))
            : ($user?->isContextAdministrator() ?? false);

        $contextFullAccess = $user && ($user->hasFullAccess() || ($activeRole?->grantsFullAccess() ?? false));

        $tenantRoleName = $activeRole ? $activeRole->name : $user?->getRoleNames()->first();
        $tenantRoleCode = $activeRole ? $activeRole->code : $user?->getRoleCode()->first();
        
        $tenantPermissions = collect();
        if ($activeRole) {
            $tenantPermissions = $activeRole->permissions->pluck('name');
            if ($user) {
                $tenantPermissions = $tenantPermissions->merge($user->getDirectPermissions()->pluck('name'))->unique()->values();
            }
        } elseif (!$activeEntityId || $user?->isSystemAdmin()) {
            $tenantPermissions = $user ? $user->getAllPermissions()->pluck('name') : collect();
        }

        $menus = function () use ($user, $tenantPermissions, $contextFullAccess) {
            if (!$user) return [];
            $isSuper = $user->isSystemAdmin();
            $reportPermissions = app(\App\Services\Reports\ReportPermissions::class)->matrix();
            $reportMenuAllowed = function ($item) use ($reportPermissions) {
                $permission = strtoupper($item->permission_name ?? '');
                if (!str_starts_with($permission, 'REPORT_')) return null;
                $id = strtolower(substr(explode('.', $permission)[0], 7));
                return $reportPermissions[$id]['view'] ?? false;
            };
            $permissionIndex = array_fill_keys($tenantPermissions->map(fn ($permission) => strtolower($permission))->all(), true);
            $navFields = ['id', 'parent_id', 'title', 'alias', 'link', 'icon', 'ordering', 'permission_name'];
            $parentsWithChildren = $contextFullAccess || $isSuper ? [] : array_fill_keys(\App\Models\Menu::where('menutype', 2)->distinct()->pluck('parent_id')->all(), true);
            $masterMenuIds = $contextFullAccess || $isSuper ? [] : array_fill_keys(\App\Models\Menu::where('menutype', 1)
                ->whereRaw('LOWER(permission_name) = ?', ['master.all'])->pluck('id')->all(), true);
            $sideNav = \App\Models\Menu::where('menutype', 2)->where('published', true)
                ->orderBy('ordering')->get()->each->setVisible($navFields)
                ->filter(function ($item) use ($isSuper, $permissionIndex, $reportMenuAllowed, $contextFullAccess, $masterMenuIds) {
                   
                    if ($isSuper) return true;
                    if (!$contextFullAccess && isset($masterMenuIds[$item->parent_id]) && !isset($permissionIndex['master.all'])) return false;
                    $reportAllowed = $reportMenuAllowed($item);
                    if ($reportAllowed !== null) return $reportAllowed;

                    if ($contextFullAccess) return true;
                   
                    if (!$item->permission_name) return true;
                    return isset($permissionIndex[strtolower($item->permission_name)]);
                })
                ->values()
                ->groupBy('parent_id');

            $topNav = \App\Models\Menu::where('menutype', 1)->where('published', true)
                ->orderBy('ordering')->get()->each->setVisible($navFields)
                ->filter(function ($item) use ($isSuper, $permissionIndex, $parentsWithChildren, $sideNav, $reportPermissions, $contextFullAccess) {
                    if ($isSuper) return true;
                    if (strtolower(trim((string) $item->alias)) === 'report') {
                        return (bool) array_filter($reportPermissions, fn ($actions) => $actions['view']);
                    }

                    if ($contextFullAccess) return true;


                    $hasDirectPerm = $item->permission_name
                        ? isset($permissionIndex[strtolower($item->permission_name)])
                        : false;

                    if (strtolower((string) $item->permission_name) === 'master.all' && !$hasDirectPerm) return false;

                    $hasChildrenInDb = isset($parentsWithChildren[$item->id]);
                    $hasChildSubNav = isset($sideNav[$item->id]) && $sideNav[$item->id]->isNotEmpty();

                    if ($hasChildrenInDb) {
                        if (!$hasChildSubNav) {
                            return false;
                        }

                        $isLinkPermitted = $sideNav[$item->id]->contains(fn($child) => $child->link === $item->link);
                        if (!$isLinkPermitted) {
                            $item->link = $sideNav[$item->id]->first()->link;
                        }
                        return true;
                    }

                    if ($item->permission_name) {
                        return $hasDirectPerm;
                    }

                    return true;
                })
                ->values();

            return [
                'top_nav' => $topNav,
                'sidebar_nav' => $sideNav,
            ];
        };

        return [
            ...parent::share($request),
            'presence' => \Inertia\Inertia::always(fn () => $request->user() ? [
                'user_id' => $request->user()->id,
                'csrf_token' => $request->session()->token(),
                'heartbeat_url' => route('session.presence'),
                'close_url' => route('session.presence.close'),
                'login_url' => route('login'),
            ] : null),
            'entity_timezone' => \Inertia\Inertia::always(fn () => $request->attributes->get('entity_timezone', 'Asia/Kolkata')),
            'auth' => [
                'user' => $user ? [
                    'id' => $user->id,
                    'default_plant_id' => $user->default_plant_id,
                    'email' => $user->email,
                    'email_verified_at' => $user->email_verified_at,
                    'is_active' => $user->is_active,
                    'mobile' => $user->mobile,
                    'username' => $user->username,
                    'profile_photo_url' => $user->profile_photo_url,
                    'roles' => $user->roles->map(fn($role) => [
                        'id' => $role->id,
                        'code' => $role->code,
                        'name' => $role->name,
                    ])->toArray(),
                ] : null,
            ],
            'active_entity'    => $activeEntity,
            'active_plant'     => $activePlant,
            'active_plant_id'  => $activePlantId,
            'gstin'            => fn () => session('gstin', $activePlant['gstin'] ?? null),
            'mixer_capacity'   => fn () => session('mixer_capacity', $activePlant['mixer_capacity'] ?? 1.25),
            'has_full_access'  => (bool) $contextFullAccess,
            'is_context_administrator' => $contextAdministrator,
            'can_choose_invoice_copy' => $contextAdministrator,
            'user_role'        => $tenantRoleName,
            'user_code'        => $tenantRoleCode,
            'user_permissions' => $tenantPermissions,
            ...$this->documentFormProps($request, $activePlantId),
            'menus'            => $menus,
            'plants_count'     => $plantsCount,
            'flash' => [
                'success' => fn () => $request->session()->get('success'),
                'error'   => fn () => $request->session()->get('error'),
                'deletion_request_submitted' => fn () => $request->session()->get('deletion_request_submitted'),
                'support_request_submitted' => fn () => $request->session()->get('support_request_submitted'),
                'support_request_notification_error' => fn () => $request->session()->get('support_request_notification_error'),
                'new_batch_id' => fn () => $request->session()->get('new_batch_id'),
                'dispatched_batch_id' => fn () => $request->session()->get('dispatched_batch_id'),
            ],
        ];
    }

    private function documentFormProps(Request $request, $plantId): array
    {
        $module = match ($request->route()?->getControllerClass()) {
            \App\Http\Controllers\BatchController::class => 'BATCH',
            \App\Http\Controllers\PurchaseOrderInwardController::class => 'INWARD',
            \App\Http\Controllers\PurchaseOrderController::class => 'PURCHASE_ORDER',
            \App\Http\Controllers\SalesOrderController::class => 'SALES_ORDER',
            \App\Http\Controllers\QuotationController::class => 'QUOTATION',
            \App\Http\Controllers\CustomerPOController::class => 'CUSTOMER_PO',
            \App\Http\Controllers\InvoiceController::class => 'INVOICE',
            \App\Http\Controllers\BillingController::class => 'BILLING',
            \App\Http\Controllers\PaymentController::class => 'PAYMENT',
            default => null,
        };
        if (!$module || !$request->user()) return [];

        $props = ['document_number_permissions' => fn () => [
            $module => \App\Support\DocumentNumberAccess::allows($request->user(), $module),
        ]];
        $weightFields = ['new_weight', 'newweight', 'camera', 'camera_url', 'camera_url_1', 'camera_url_2', 'manual_weight'];
        $fields = match ($module) {
            'INWARD' => $weightFields,
            'PURCHASE_ORDER' => ['new_weight', 'newweight'],
            'BATCH' => [...$weightFields, 'weight_mandatory', 'default_transport', 'hide_batch_form',
                'actual_consumption_variation', 'actual_variation_limit', 'sheet_upload', 'target_to_actual'],
            'QUOTATION' => ['quote_validity'],
            default => [],
        };
        if ($plantId && $fields) {
            $props['custom_settings'] = fn () => ['batching' => \Illuminate\Support\Arr::only(
                \App\Models\CustomSetting::getForModule($plantId, 'batching'), $fields
            )];
        }
        return $props;
    }
}
