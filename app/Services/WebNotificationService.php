<?php
/*
Author: ragul-onemodo
Created: 2026-11-25 11:23:00 Asia/Calcutta (UTC+05:30)
*/

namespace App\Services;

use App\Helpers\DateTimeHelper;
use App\Models\EntityUser;
use App\Models\Machine;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class WebNotificationService
{
    /**
     * Module definitions mapped to permission, category, label, and target route.
     */
    private const MODULE_CONFIG = [
        'sales_order' => [
            'permission' => 'SALES_ORDER.VIEW',
            'category' => 'sales',
            'type' => 'Sales Order',
            'url' => '/salesorders',
        ],
        'quotation' => [
            'permission' => 'QUOTATION.VIEW',
            'category' => 'sales',
            'type' => 'Quotation',
            'url' => '/quotations',
        ],
        'customer_po' => [
            'permission' => 'CUSTOMER_PO.VIEW',
            'category' => 'sales',
            'type' => 'Customer PO',
            'url' => '/customerpos',
        ],
        'crm_lead' => [
            'permission' => 'CRM_LEAD.VIEW',
            'category' => 'sales',
            'type' => 'CRM Lead',
            'url' => '/crm/leads',
        ],
        'batch' => [
            'permission' => 'BATCH.VIEW',
            'category' => 'operations',
            'type' => 'Batching',
            'url' => '/batches',
        ],
        'dispatch' => [
            'permission' => 'DISPATCH.VIEW',
            'category' => 'operations',
            'type' => 'Dispatch',
            'url' => '/dispatches',
        ],
        'purchase_order' => [
            'permission' => 'PURCHASE_ORDER.VIEW',
            'category' => 'operations',
            'type' => 'Purchase Order',
            'url' => '/purchaseorder',
        ],
        'invoice' => [
            'permission' => 'INVOICE.VIEW',
            'category' => 'finance',
            'type' => 'Invoice',
            'url' => '/invoices',
        ],
        'payment' => [
            'permission' => 'PAYMENT.VIEW',
            'category' => 'finance',
            'type' => 'Payment',
            'url' => '/payments',
        ],
        'expense' => [
            'permission' => 'EXPENSE.VIEW',
            'category' => 'finance',
            'type' => 'Expense',
            'url' => '/expenses',
        ],
        'machine_expiry' => [
            'permission' => 'MACHINE.VIEW',
            'category' => 'fleet',
            'type' => 'Machine Expiry',
            'url' => '/machines',
        ],
        'leave_application' => [
            'permission' => 'LEAVE_APPLICATION.VIEW',
            'category' => 'hr',
            'type' => 'Leave Request',
            'url' => '/leave-applications',
        ],
    ];

    public function feed(User $user, ?int $entityId, ?int $plantId): array
    {
        $context = $this->resolveRoleContext($user, $entityId, $plantId);
        $readIds = $this->getReadCache($user->id, $plantId);
        $items = collect();

        // 1. Laravel database notifications (filtered by active plant & role permissions)
        $items = $items->merge($this->databaseNotifications($user, $plantId, $context));

        // 2. Role-wise live operational alerts & recent plant activities
        if ($plantId) {
            if ($this->canAccess($context, 'MACHINE.VIEW')) {
                $items = $items->merge($this->machineExpiryAlerts($plantId, $readIds));
            }
            if ($this->canAccess($context, 'LEAVE_APPLICATION.VIEW')) {
                $items = $items->merge($this->pendingLeaveAlerts($plantId, $readIds));
            }
            $items = $items->merge($this->recentModuleActivities($plantId, $context, $readIds));
        }

        $sorted = $items
            ->sortByDesc(fn (array $item) => $item['sort_at'] ?? '')
            ->values()
            ->take(50)
            ->map(function (array $item) {
                unset($item['sort_at']);
                return $item;
            })
            ->values();

        $categories = $sorted
            ->pluck('category')
            ->unique()
            ->values()
            ->all();

        return [
            'role_name' => $context['role_name'],
            'unread_count' => $sorted->where('is_read', false)->count(),
            'categories' => $categories,
            'notifications' => $sorted->all(),
        ];
    }

    public function markAsRead(User $user, ?int $plantId, string $notificationId): void
    {
        if (str_starts_with($notificationId, 'db:')) {
            $uuid = substr($notificationId, 3);
            $entry = $user->notifications()->where('id', $uuid)->first();
            if ($entry && $entry->read_at === null) {
                $entry->markAsRead();
            }
            return;
        }

        $readIds = $this->getReadCache($user->id, $plantId);
        if (! in_array($notificationId, $readIds, true)) {
            $readIds[] = $notificationId;
            $this->putReadCache($user->id, $plantId, array_slice($readIds, -500));
        }
    }

    public function markAllAsRead(User $user, ?int $entityId, ?int $plantId): void
    {
        $user->notifications()->whereNull('read_at')->update(['read_at' => now()]);

        $feed = $this->feed($user, $entityId, $plantId);
        $ids = collect($feed['notifications'])
            ->pluck('id')
            ->reject(fn ($id) => str_starts_with((string) $id, 'db:'))
            ->values()
            ->all();

        $merged = array_values(array_unique(array_merge($this->getReadCache($user->id, $plantId), $ids)));
        $this->putReadCache($user->id, $plantId, array_slice($merged, -500));
    }

    private function resolveRoleContext(User $user, ?int $entityId, ?int $plantId): array
    {
        if ($user->isSystemAdmin()) {
            return [
                'role_name' => 'Super Administrator',
                'full_access' => true,
                'permissions' => collect(),
            ];
        }

        $activeRole = $entityId
            ? EntityUser::forContext($user->id, $entityId, $plantId)?->role
            : null;

        $fullAccess = $user->hasFullAccess() || ($activeRole?->grantsFullAccess() ?? false);
        $roleName = $activeRole?->name ?: ($user->getRoleNames()->first() ?: 'User');

        $permissions = $activeRole
            ? $activeRole->permissions->pluck('name')->merge($user->getDirectPermissions()->pluck('name'))
            : $user->getAllPermissions()->pluck('name');

        return [
            'role_name' => $roleName,
            'full_access' => $fullAccess,
            'permissions' => $permissions->map(fn ($p) => strtoupper((string) $p))->unique()->flip(),
        ];
    }

    private function canAccess(array $context, string $permission): bool
    {
        return $context['full_access'] || $context['permissions']->has(strtoupper($permission));
    }

    private function databaseNotifications(User $user, ?int $plantId, array $context): Collection
    {
        if (! Schema::hasTable('notifications')) {
            return collect();
        }

        $entries = $user->notifications()
            ->when($plantId, fn ($q) => $q->where(fn ($w) => $w->whereNull('data->plant_id')->orWhere('data->plant_id', $plantId)))
            ->orderByDesc('created_at')
            ->limit(25)
            ->get();

        return $entries->map(function (DatabaseNotification $entry) use ($context) {
            $data = is_array($entry->data) ? $entry->data : [];
            $subjectKey = $this->mapSubjectToModuleKey((string) ($data['subject_type'] ?? ''));
            $config = self::MODULE_CONFIG[$subjectKey] ?? null;

            if ($config && ! $this->canAccess($context, $config['permission'])) {
                return null;
            }

            $ref = $data['payload']['reference'] ?? null;
            $title = $data['title'] ?? ($config['type'] ?? 'Notification');
            $body = $ref ? "Ref: {$ref}" : ($data['message'] ?? 'Activity notification');

            return [
                'id' => 'db:' . $entry->id,
                'type' => $config['type'] ?? 'System',
                'category' => $config['category'] ?? 'general',
                'severity' => 'info',
                'title' => $title,
                'message' => $body,
                'url' => $config['url'] ?? '/dashboard',
                'is_read' => $entry->read_at !== null,
                'time_label' => $entry->created_at?->diffForHumans() ?? 'Just now',
                'sort_at' => $entry->created_at?->toIso8601String() ?? now()->toIso8601String(),
            ];
        })->filter()->values();
    }

    private function machineExpiryAlerts(int $plantId, array $readIds): Collection
    {
        if (! Schema::hasTable('mm_machines') || ! Schema::hasTable('mm_machine_documents')) {
            return collect();
        }

        $today = CarbonImmutable::now(DateTimeHelper::timezoneForPlant($plantId))->startOfDay();
        $machines = Machine::withoutGlobalScope('plant_id')
            ->where('plant_id', $plantId)
            ->where('is_active', true)
            ->with(['documents' => fn ($q) => $q->whereNotNull('expiry_date')])
            ->get();

        $alerts = collect();
        foreach ($machines as $machine) {
            foreach ($machine->documents as $doc) {
                $dateStr = substr((string) $doc->expiry_date, 0, 10);
                try {
                    $expiry = CarbonImmutable::createFromFormat('!Y-m-d', $dateStr, $today->timezone);
                } catch (\Throwable) {
                    continue;
                }
                $days = (int) $today->diffInDays($expiry, false);
                if ($days > 30) {
                    continue;
                }

                $id = "machine_doc:{$doc->id}:{$dateStr}";
                $statusText = $days < 0
                    ? 'Expired ' . abs($days) . ' day(s) ago'
                    : ($days === 0 ? 'Expires today' : "Expires in {$days} day(s)");

                $alerts->push([
                    'id' => $id,
                    'type' => 'Machine Expiry',
                    'category' => 'fleet',
                    'severity' => $days <= 0 ? 'danger' : ($days <= 7 ? 'warning' : 'info'),
                    'title' => "{$machine->registration} — " . strtoupper(str_replace('_', ' ', (string) $doc->type)),
                    'message' => "{$statusText} ({$expiry->format('d-M-Y')})",
                    'url' => '/machines',
                    'is_read' => in_array($id, $readIds, true),
                    'time_label' => $statusText,
                    'sort_at' => $today->subDays(max(0, $days))->toIso8601String(),
                ]);
            }
        }

        return $alerts->take(10);
    }

    private function pendingLeaveAlerts(int $plantId, array $readIds): Collection
    {
        if (! Schema::hasTable('mm_leave_applications') || ! Schema::hasTable('mm_personnels')) {
            return collect();
        }

        $rows = DB::table('mm_leave_applications as la')
            ->join('mm_personnels as p', 'p.id', '=', 'la.personnel_id')
            ->where('p.plant_id', $plantId)
            ->whereNull('la.deleted_at')
            ->whereNull('p.deleted_at')
            ->where('la.status', 'pending')
            ->orderByDesc('la.created_at')
            ->limit(5)
            ->get(['la.id', 'p.first_name', 'p.last_name', 'la.created_at']);

        return $rows->map(function ($row) use ($readIds) {
            $id = "leave:{$row->id}";
            $name = trim(($row->first_name ?? '') . ' ' . ($row->last_name ?? ''));
            $createdAt = $row->created_at ? CarbonImmutable::parse($row->created_at) : now();

            return [
                'id' => $id,
                'type' => 'Leave Request',
                'category' => 'hr',
                'severity' => 'warning',
                'title' => "Pending Leave: {$name}",
                'message' => 'Awaiting approval',
                'url' => '/leave-applications',
                'is_read' => in_array($id, $readIds, true),
                'time_label' => $createdAt->diffForHumans(),
                'sort_at' => $createdAt->toIso8601String(),
            ];
        });
    }

    private function recentModuleActivities(int $plantId, array $context, array $readIds): Collection
    {
        $since = now()->subDays(7);
        $sources = [
            ['key' => 'sales_order', 'table' => 'mm_sales_orders', 'ref' => 'order_no', 'prefix' => 'prefix', 'status' => 'status'],
            ['key' => 'quotation', 'table' => 'mm_quotations', 'ref' => 'reference', 'prefix' => 'prefix', 'status' => 'status'],
            ['key' => 'customer_po', 'table' => 'mm_customer_pos', 'ref' => 'reference', 'prefix' => 'prefix', 'status' => 'status'],
            ['key' => 'crm_lead', 'table' => 'mm_crm_leads', 'ref' => 'lead_number', 'prefix' => null, 'status' => 'status'],
            ['key' => 'batch', 'table' => 'mm_batches', 'ref' => 'batch_no', 'prefix' => null, 'status' => 'status'],
            ['key' => 'dispatch', 'table' => 'mm_dispatches', 'ref' => 'dispatch_no', 'prefix' => 'prefix', 'status' => 'status'],
            ['key' => 'purchase_order', 'table' => 'mm_purchase_orders', 'ref' => 'po_number', 'prefix' => null, 'status' => null],
            ['key' => 'invoice', 'table' => 'mm_invoices', 'ref' => 'invoice_number', 'prefix' => 'prefix', 'status' => 'status'],
            ['key' => 'payment', 'table' => 'mm_payments', 'ref' => 'reference', 'prefix' => null, 'status' => 'status'],
            ['key' => 'expense', 'table' => 'mm_expenses', 'ref' => 'ref_no', 'prefix' => null, 'status' => 'status'],
        ];

        $items = collect();
        foreach ($sources as $src) {
            $config = self::MODULE_CONFIG[$src['key']];
            if (! $this->canAccess($context, $config['permission'])) {
                continue;
            }

            $columns = Schema::getColumnListing($src['table']);
            if (array_diff(['id', 'plant_id', 'created_at', 'updated_at', 'deleted_at'], $columns)) {
                continue;
            }

            $cols = ['id', 'created_at', 'updated_at'];
            $status = $src['key'] === 'dispatch' && in_array('dispatch_status', $columns, true)
                ? 'dispatch_status' : $src['status'];
            foreach (['ref_val' => $src['ref'], 'prefix_val' => $src['prefix'], 'status_val' => $status] as $alias => $column) {
                if (in_array($column, $columns, true)) {
                    $cols[] = $column . ' as ' . $alias;
                }
            }

            $rows = DB::table($src['table'])
                ->where('plant_id', $plantId)
                ->whereNull('deleted_at')
                ->where('updated_at', '>=', $since)
                ->orderByDesc('updated_at')
                ->limit(4)
                ->get($cols);

            foreach ($rows as $row) {
                $ref = trim((string) (($row->prefix_val ?? '') . ($row->ref_val ?? $row->id)));
                $ts = $row->updated_at ?: $row->created_at;
                $time = $ts ? CarbonImmutable::parse($ts) : now();
                $id = "{$src['key']}:{$row->id}:" . $time->timestamp;
                $statusLabel = isset($row->status_val) && $row->status_val !== '' ? ' • Status: ' . ucfirst((string) $row->status_val) : '';

                $items->push([
                    'id' => $id,
                    'type' => $config['type'],
                    'category' => $config['category'],
                    'severity' => 'info',
                    'title' => "{$config['type']} #{$ref}",
                    'message' => ($row->created_at === $row->updated_at ? 'New record created' : 'Record updated') . $statusLabel,
                    'url' => $config['url'],
                    'is_read' => in_array($id, $readIds, true),
                    'time_label' => $time->diffForHumans(),
                    'sort_at' => $time->toIso8601String(),
                ]);
            }
        }

        return $items;
    }

    private function mapSubjectToModuleKey(string $subjectType): string
    {
        $base = strtolower(class_basename($subjectType));
        return match ($base) {
            'salesorder' => 'sales_order',
            'quotation' => 'quotation',
            'customerpo' => 'customer_po',
            'crmlead' => 'crm_lead',
            'batch' => 'batch',
            'dispatch' => 'dispatch',
            'purchaseorder' => 'purchase_order',
            'invoice' => 'invoice',
            'payment' => 'payment',
            'expense' => 'expense',
            default => '',
        };
    }

    private function cacheKey(int $userId, ?int $plantId): string
    {
        return "web_notifications_read:{$userId}:" . ($plantId ?? 0);
    }

    private function getReadCache(int $userId, ?int $plantId): array
    {
        return Cache::get($this->cacheKey($userId, $plantId), []);
    }

    private function putReadCache(int $userId, ?int $plantId, array $ids): void
    {
        Cache::put($this->cacheKey($userId, $plantId), $ids, now()->addDays(30));
    }
}
