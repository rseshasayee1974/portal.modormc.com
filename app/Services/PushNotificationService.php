<?php

namespace App\Services;

use App\Models\PushNotificationLog;
use App\Models\User;
use App\Notifications\BusinessActivityNotification;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class PushNotificationService
{
    public const RECEIVE_PERMISSION = 'PUSH_NOTIFICATION.RECEIVE';

    public function dispatch(int $logId): bool
    {
        if (! config('push_notifications.enabled')) {
            return false;
        }

        return DB::transaction(function () use ($logId) {
            $log = PushNotificationLog::lockForUpdate()->find($logId);
            if (! $log || ! $log->recipient_user_id || ! in_array($log->status, ['pending', 'failed'], true)) {
                return false;
            }
            if ($log->status === 'pending' && $log->provider_message_id && $log->provider === 'reverb' && ! $log->error) {
                return false;
            }
            $recipient = User::find($log->recipient_user_id);
            if (! $recipient || ! $this->recipients((int) $log->plant_id, [(int) $recipient->id])) {
                $log->update(['status' => 'skipped', 'error' => 'Recipient is no longer eligible to receive notifications in this plant.']);

                return false;
            }
            $log->update(['status' => 'pending', 'provider' => 'reverb', 'provider_message_id' => $log->provider_message_id ?: (string) Str::uuid(), 'error' => null]);
            Notification::send($recipient, new BusinessActivityNotification($log));

            return true;
        });
    }

    public function recipients(int $plantId, ?array $userIds = null): array
    {
        return User::where('is_active', 1)->when($userIds !== null, fn ($q) => $q->whereIn('id', $userIds))->whereExists(fn ($q) => $q->selectRaw('1')->from('mm_entity_users')
            ->join('mm_plants', 'mm_plants.entity_id', '=', 'mm_entity_users.entity_id')
            ->whereColumn('mm_entity_users.user_id', 'mm_users.id')->where('mm_plants.id', $plantId)
            ->whereNull('mm_plants.deleted_at')->whereNull('mm_entity_users.deleted_at')
            ->where('mm_entity_users.id', function ($context) use ($plantId) {
                $context->select('context.id')->from('mm_entity_users as context')
                    ->whereColumn('context.user_id', 'mm_users.id')->whereColumn('context.entity_id', 'mm_plants.entity_id')
                    ->whereNull('context.deleted_at')
                    ->where(fn ($scope) => $scope->whereNull('context.plant_id')->orWhere('context.plant_id', $plantId))
                    ->orderByRaw('context.plant_id IS NULL')->orderBy('context.id')->limit(1);
            })
            ->where(function ($allowed) {
                $allowed->whereExists(fn ($roles) => $roles->selectRaw('1')->from('mm_role_has_permissions')
                    ->join('mm_permissions', 'mm_permissions.id', '=', 'mm_role_has_permissions.permission_id')
                    ->join('mm_roles', 'mm_roles.id', '=', 'mm_role_has_permissions.role_id')
                    ->whereColumn('mm_role_has_permissions.role_id', 'mm_entity_users.role_id')->whereNull('mm_roles.deleted_at')
                    ->where('mm_permissions.name', self::RECEIVE_PERMISSION)->where('mm_permissions.guard_name', 'web'))
                    ->orWhereExists(fn ($direct) => $direct->selectRaw('1')->from('mm_model_has_permissions')
                        ->join('mm_permissions', 'mm_permissions.id', '=', 'mm_model_has_permissions.permission_id')
                        ->whereColumn('mm_model_has_permissions.model_id', 'mm_users.id')
                        ->where('mm_model_has_permissions.model_type', (new User)->getMorphClass())
                        ->where('mm_permissions.name', self::RECEIVE_PERMISSION)->where('mm_permissions.guard_name', 'web'));
            })
            ->where(fn ($m) => $m->whereNull('mm_entity_users.plant_id')->orWhere('mm_entity_users.plant_id', $plantId)))
            ->pluck('id')->map(fn ($id) => (int) $id)->all();
    }

    public function record(Model $model, string $operation): void
    {
        if (! Schema::hasTable('mm_push_notification_logs')) {
            return;
        }
        $plantId = (int) $model->plant_id;
        $recipients = $this->recipients($plantId);
        $changes = $operation === 'updated' ? array_intersect_key($model->getChanges(), array_flip([
            'status', 'assigned_to', 'customer_id', 'patron_id', 'site_id', 'sales_executive_id', 'amount_total', 'total_qty',
        ])) : [];
        foreach ($recipients ?: [null] as $recipient) {
            PushNotificationLog::create([
                'plant_id' => $plantId, 'actor_id' => auth()->id(), 'recipient_user_id' => $recipient,
                'subject_type' => $model->getMorphClass(), 'subject_id' => $model->getKey(), 'operation' => $operation,
                'title' => class_basename($model).' '.$operation,
                'payload' => ['reference' => $model->getAttribute('reference') ?: ($model->lead_number ?: ($model->order_no ?: (string) $model->getKey())), 'changes' => $changes],
                'status' => $recipient ? 'pending' : 'skipped',
                'error' => $recipient ? null : 'No active plant users have PUSH_NOTIFICATION.RECEIVE permission.',
            ]);
        }
    }
}
