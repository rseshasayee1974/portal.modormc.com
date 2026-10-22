<?php

namespace App\Console\Commands;

use App\Models\PushNotificationLog;
use App\Services\PushNotificationService;
use Illuminate\Console\Command;

class DispatchPendingPushNotifications extends Command
{
    protected $signature = 'push:dispatch-pending {--id=} {--limit=50} {--retry}';

    protected $description = 'Queue pending Laravel database and Reverb notifications';

    public function handle(PushNotificationService $service): int
    {
        if (! config('push_notifications.enabled')) {
            $this->error('Enable PUSH_NOTIFICATIONS_ENABLED to dispatch notifications.');

            return self::FAILURE;
        }
        $limit = filter_var($this->option('limit'), FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => 1000]]);
        $id = $this->option('id');
        if (! $limit || ($id !== null && ! ctype_digit((string) $id))) {
            $this->error('Use a numeric --id and --limit between 1 and 1000.');

            return self::FAILURE;
        }
        $ids = PushNotificationLog::whereIn('status', $this->option('retry') ? ['pending', 'failed'] : ['pending'])
            ->when($id !== null, fn ($q) => $q->whereKey($id))->orderBy('id')->limit($limit)->pluck('id');
        $queued = 0;
        foreach ($ids as $logId) {
            if ($service->dispatch($logId)) {
                $queued++;
            }
        }
        $this->info("Queued {$queued} notifications through Laravel notifications.");

        return self::SUCCESS;
    }
}
