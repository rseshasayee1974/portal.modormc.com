<?php

namespace App\Observers;

use Illuminate\Database\Eloquent\Model;
use App\Models\InventoryAuditLog;
use Illuminate\Support\Facades\Auth;

/**
 * Class ModelAuditObserver
 * 
 * Automatically captures and persists audit logs for Eloquent models
 * implementing the TracksModelChanges trait.
 */
class ModelAuditObserver
{
    /**
     * Keep track of changes during the 'updating' event, since the model's
     * dirty attributes will be cleared by the time the 'updated' event runs.
     *
     * @var array<int, array{remarks: string, log_from: string, log_to: string}>
     */
    protected static array $pendingUpdates = [];

    /**
     * Handle the Model "created" event.
     *
     * @param Model $model
     * @return void
     */
    public function created(Model $model): void
    {
        if (!method_exists($model, 'getAuditChanges')) {
            return;
        }

        try {
            InventoryAuditLog::create([
                'plant_id'         => $model->plant_id ?? (session('active_plant_id') ?: 1),
                'transaction_type' => 'CREATE',
                'reference_type'   => class_basename($model),
                'reference_id'     => $model->getKey(),
                'log_from'         => json_encode([]),
                'log_to'           => json_encode($model->getAttributes(), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
                'remarks'          => 'Created: ' . class_basename($model) . ' #' . $model->getKey(),
                'user_id'          => Auth::id(),
                'ip_address'       => app()->runningInConsole() ? '127.0.0.1' : request()->ip(),
            ]);
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error('Failed to log model creation audit: ' . $e->getMessage());
        }
    }

    /**
     * Handle the Model "updating" event.
     * Captures dirty state in-memory before it gets saved/flushed.
     *
     * @param Model $model
     * @return void
     */
    public function updating(Model $model): void
    {
        if (!method_exists($model, 'getAuditChanges')) {
            return;
        }

        try {
            // Prevent duplicate/empty UPDATE logs when no actual change exists
            $changes = $model->getAuditChanges();
            if (empty($changes)) {
                return;
            }

            $logFrom = [];
            $logTo = [];
            foreach ($changes as $change) {
                $logFrom[$change['field']] = $change['old'];
                $logTo[$change['field']] = $change['new'];
            }

            $remarks = 'Updated: ' . $model->getAuditRemarkString();
            $cacheKey = class_basename($model) . ':' . $model->getKey();

            self::$pendingUpdates[$cacheKey] = [
                'remarks'  => $remarks,
                'log_from' => json_encode($logFrom, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
                'log_to'   => json_encode($logTo, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
            ];
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error('Failed to capture model updating audit: ' . $e->getMessage());
        }
    }

    /**
     * Handle the Model "updated" event.
     * Writes to database only after successful update.
     *
     * @param Model $model
     * @return void
     */
    public function updated(Model $model): void
    {
        $cacheKey = class_basename($model) . ':' . $model->getKey();

        if (!isset(self::$pendingUpdates[$cacheKey])) {
            return;
        }

        $pending = self::$pendingUpdates[$cacheKey];
        unset(self::$pendingUpdates[$cacheKey]);

        try {
            InventoryAuditLog::create([
                'plant_id'         => $model->plant_id ?? (session('active_plant_id') ?: 1),
                'transaction_type' => 'UPDATE',
                'reference_type'   => class_basename($model),
                'reference_id'     => $model->getKey(),
                'log_from'         => $pending['log_from'],
                'log_to'           => $pending['log_to'],
                'remarks'          => $pending['remarks'],
                'user_id'          => Auth::id(),
                'ip_address'       => app()->runningInConsole() ? '127.0.0.1' : request()->ip(),
            ]);
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error('Failed to log model update audit: ' . $e->getMessage());
        }
    }

    /**
     * Handle the Model "deleted" event.
     *
     * @param Model $model
     * @return void
     */
    public function deleted(Model $model): void
    {
        if (!method_exists($model, 'getAuditChanges')) {
            return;
        }

        try {
            InventoryAuditLog::create([
                'plant_id'         => $model->plant_id ?? (session('active_plant_id') ?: 1),
                'transaction_type' => 'DELETE',
                'reference_type'   => class_basename($model),
                'reference_id'     => $model->getKey(),
                'log_from'         => json_encode($model->getOriginal(), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
                'log_to'           => json_encode([]),
                'remarks'          => 'Deleted: ' . class_basename($model) . ': ' . ($model->title ?? $model->name ?? $model->getKey()),
                'user_id'          => Auth::id(),
                'ip_address'       => app()->runningInConsole() ? '127.0.0.1' : request()->ip(),
            ]);
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error('Failed to log model deletion audit: ' . $e->getMessage());
        }
    }
}
