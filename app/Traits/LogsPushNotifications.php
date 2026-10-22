<?php
namespace App\Traits;

use App\Services\PushNotificationService;

trait LogsPushNotifications
{
    public static function bootLogsPushNotifications(): void
    {
        foreach (['created', 'updated', 'deleted', 'restored'] as $operation) {
            static::$operation(function ($model) use ($operation) {
                $businessChanges = array_diff(array_keys($model->getChanges()), [
                    'updated_at', 'updated_by', 'deleted_at', 'deleted_by', 'lead_number',
                ]);
                if ($operation === 'updated' && empty($businessChanges)) {
                    return;
                }
                app(PushNotificationService::class)->record($model, $operation);
            });
        }
    }
}
