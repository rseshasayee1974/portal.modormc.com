<?php

namespace App\Observers;

use Illuminate\Database\Eloquent\Model;
use App\Models\InventoryAuditLog;
use Illuminate\Support\Facades\Auth;

class ModelAuditObserver
{
    /**
     * Handle the Model "updated" event.
     */
    public function updated(Model $model): void
    {
        if ($model instanceof InventoryAuditLog) {
            return;
        }

        $changes = method_exists($model, 'getAuditChanges')
            ? $model->getAuditChanges()
            : [];

        // If no fields were updated, don't log it
        if (empty($changes)) {
            return;
        }

        $remark = method_exists($model, 'getAuditRemarkString')
            ? $model->getAuditRemarkString($changes)
            : '';

        $oldValues = [];
        $newValues = [];
        foreach ($changes as $change) {
            $oldValues[$change['field']] = $change['old'];
            $newValues[$change['field']] = $change['new'];
        }

        $plantId = $model->plant_id ?? session('active_plant_id') ?? null;

        try {
            InventoryAuditLog::create([
                'plant_id'         => $plantId,
                'transaction_type' => 'UPDATE',
                'action_type'      => 'UPDATE',
                'reference_type'   => class_basename($model),
                'reference_id'     => $model->getKey(),
                'log_from'         => !empty($oldValues) ? json_encode($oldValues, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) : null,
                'log_to'           => !empty($newValues) ? json_encode($newValues, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) : null,
                'user_id'          => Auth::id(),
                'remarks'          => $remark ?: "Updated " . class_basename($model) . " (#{$model->getKey()})",
                'ip_address'        => request()?->ip(),
            ]);
        } catch (\Throwable $e) {
            // Safe fallback to prevent breaking transaction if audit logging encounters schema mismatch
            \Illuminate\Support\Facades\Log::error("ModelAuditObserver update failed: " . $e->getMessage());
        }
    }

    /**
     * Handle the Model "deleted" event.
     */
    public function deleted(Model $model): void
    {
        if ($model instanceof InventoryAuditLog) {
            return;
        }

        $plantId = $model->plant_id ?? session('active_plant_id') ?? null;

        try {
            InventoryAuditLog::create([
                'plant_id'         => $plantId,
                'transaction_type' => 'DELETE',
                'action_type'      => 'DELETE',
                'reference_type'   => class_basename($model),
                'reference_id'     => $model->getKey(),
                'log_from'         => json_encode($model->getOriginal(), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
                'log_to'           => null,
                'user_id'          => Auth::id(),
                'remarks'          => "Deleted " . class_basename($model) . " (#{$model->getKey()})",
                'ip_address'        => request()?->ip(),
            ]);
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::error("ModelAuditObserver delete failed: " . $e->getMessage());
        }
    }
}
