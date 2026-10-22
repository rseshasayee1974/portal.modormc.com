<?php

namespace App\Notifications;

use App\Models\PushNotificationLog;
use App\Services\PushNotificationService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\BroadcastMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\DB;
use Throwable;

class BusinessActivityNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $logId;

    public array $data;

    public function __construct(PushNotificationLog $log)
    {
        $this->id = $log->provider_message_id;
        $this->logId = $log->id;
        $this->data = [
            'push_log_id' => $log->id, 'plant_id' => $log->plant_id, 'title' => $log->title,
            'operation' => $log->operation, 'subject_type' => $log->subject_type,
            'subject_id' => $log->subject_id, 'payload' => $log->payload, 'created_at' => now()->toIso8601String(),
        ];
        $this->afterCommit();
    }

    public function via(object $notifiable): array
    {
        return ['database', 'broadcast'];
    }

    public function backoff(): array
    {
        return [10, 30, 60];
    }

    public function toArray(object $notifiable): array
    {
        return $this->data;
    }

    public function toBroadcast(object $notifiable): BroadcastMessage
    {
        // The notification itself is queued; perform its Reverb request in that worker.
        return (new BroadcastMessage($this->data))->onConnection('sync');
    }

    public function broadcastWith(): array
    {
        return array_merge($this->data, ['id' => $this->logId, 'type' => static::class]);
    }

    public function shouldSend(object $notifiable, string $channel): bool
    {
        $log = PushNotificationLog::find($this->logId);
        if (! $log || ! config('push_notifications.enabled')) {
            return false;
        }
        $eligible = app(PushNotificationService::class)->recipients((int) $log->plant_id, [(int) $notifiable->id]);
        if (! $eligible) {
            $log->update(['status' => 'skipped', 'error' => 'Recipient is no longer eligible to receive notifications in this plant.']);

            return false;
        }

        return $channel === 'database'
            ? ! DB::table('notifications')->where('id', $this->id)->exists()
            : ! in_array($log->status, ['sent', 'delivered', 'read', 'skipped'], true);
    }

    public function failed(Throwable $exception): void
    {
        $log = PushNotificationLog::find($this->logId);
        if ($log && ! in_array($log->status, ['read', 'delivered'], true)) {
            $log->update(['status' => 'failed', 'error' => 'Laravel notification delivery failed after retries.']);
        }
    }
}
