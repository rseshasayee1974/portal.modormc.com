<?php

/*
Author: Cipher
Created: 2026-10-17 14:44:50 Asia/Calcutta (UTC+05:30)
*/

namespace App\Observers;

use App\Models\PushNotificationAuditLog;
use App\Models\PushNotificationLog;
use App\Services\PushNotificationService;
use Illuminate\Support\Facades\Schema;

class PushNotificationAuditObserver
{
    public function creating(PushNotificationLog $log): void
    {
        $log->created_by ??= auth()->id();
        $log->updated_by ??= auth()->id();
    }

    public function updating(PushNotificationLog $log): void
    {
        if (auth()->check()) {
            $log->updated_by = auth()->id();
        }
    }

    public function deleting(PushNotificationLog $log): void
    {
        if (! $log->isForceDeleting() && auth()->check()) {
            $log->deleted_by = auth()->id();
            $log->saveQuietly();
        }
    }

    public function created(PushNotificationLog $log): void
    {
        $this->record($log, $log->status === 'skipped' ? 'SKIPPED' : 'QUEUED', null, true);
        if ($log->status === 'pending' && config('push_notifications.enabled')) {
            $log->getConnection()->afterCommit(function () use ($log) {
                try {
                    app(PushNotificationService::class)->dispatch($log->id);
                } catch (\Throwable $exception) {
                    $log->fresh()?->update(['error' => 'Notification could not be queued. Retry with push:dispatch-pending.']);
                    report($exception);
                }
            });
        }
    }

    public function updated(PushNotificationLog $log): void
    {
        $changes = array_diff_key($log->getChanges(), array_flip(['created_at', 'created_by', 'updated_at', 'updated_by', 'deleted_at', 'deleted_by']));
        if (! $changes) {
            return;
        }
        $action = $log->wasChanged('status') ? match ($log->status) {
            'pending' => 'QUEUED', 'processing' => 'ATTEMPTED', 'sent' => 'SENT', 'failed' => 'FAILED',
            'delivered' => 'DELIVERED', 'read' => 'READ', 'skipped' => 'SKIPPED', default => 'STATUS_CHANGED',
        } : ($log->wasChanged('attempts') ? 'ATTEMPTED' : 'UPDATED');
        $this->record($log, $action, $changes);
    }

    public function deleted(PushNotificationLog $log): void
    {
        $this->record($log, 'DELETED');
    }

    public function restored(PushNotificationLog $log): void
    {
        $this->record($log, 'RESTORED');
    }

    private function record(PushNotificationLog $log, string $action, ?array $changes = null, bool $created = false): void
    {
        if (! Schema::hasTable('mm_push_notification_audit_logs')) {
            return;
        }
        $before = $created ? null : $log->getRawOriginal();
        $after = $log->getAttributes();
        if ($changes !== null) {
            $before = array_intersect_key($before ?? [], $changes);
            $after = array_intersect_key($after, $changes);
        }
        PushNotificationAuditLog::create([
            'plant_id' => $log->plant_id, 'push_notification_log_id' => $log->id,
            'recipient_user_id' => $log->recipient_user_id,
            'reference_type' => $log->subject_type, 'reference_id' => $log->subject_id,
            'action_type' => $action, 'status_from' => $created ? null : $log->getRawOriginal('status'),
            'status_to' => $log->status, 'attempt_number' => $log->attempts ?? 0,
            'provider' => $log->provider, 'provider_message_id' => $log->provider_message_id,
            'provider_response' => $log->provider_response, 'log_from' => $before, 'log_to' => $after,
            'user_id' => auth()->id(), 'remarks' => 'Push notification '.strtolower($action), 'ip_address' => request()?->ip(),
        ]);
    }
}
