<?php



namespace App\Listeners;

use App\Models\PushNotificationLog;
use App\Notifications\BusinessActivityNotification;
use Illuminate\Notifications\Events\NotificationSending;
use Illuminate\Notifications\Events\NotificationSent;
use Illuminate\Support\Facades\DB;

class TrackPushNotificationDelivery
{
    public function sending(NotificationSending $event): void
    {
        if (! $event->notification instanceof BusinessActivityNotification || $event->channel !== 'broadcast') {
            return;
        }
        DB::transaction(function () use ($event) {
            $log = PushNotificationLog::lockForUpdate()->find($event->notification->logId);
            if ($log) {
                $log->update(['status' => 'processing', 'attempts' => $log->attempts + 1,
                    'last_attempt_at' => now(), 'provider' => 'reverb', 'error' => null]);
            }
        });
    }

    public function sent(NotificationSent $event): void
    {
        if (! $event->notification instanceof BusinessActivityNotification) {
            return;
        }
        DB::transaction(function () use ($event) {
            $log = PushNotificationLog::lockForUpdate()->find($event->notification->logId);
            if (! $log) {
                return;
            }
            $response = array_merge($log->provider_response ?? [], [$event->channel => 'sent']);
            $data = ['provider_response' => $response];
            if ($event->channel === 'broadcast' && ! in_array($log->status, ['read', 'delivered'], true)) {
                $data += ['status' => 'sent', 'sent_at' => now(), 'provider' => 'reverb', 'error' => null];
            }
            $log->update($data);
        });
    }
}
