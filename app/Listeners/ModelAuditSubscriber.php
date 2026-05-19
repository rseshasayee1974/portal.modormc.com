<?php

namespace App\Listeners;

use App\Services\Audit\AuditLogger;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Database\Eloquent\Model;

class ModelAuditSubscriber
{
    private array $pendingUpdates = [];

    private array $pendingDeletes = [];

    public function __construct(private readonly AuditLogger $auditLogger)
    {
    }

    public function subscribe(Dispatcher $events): void
    {
        $events->listen('eloquent.creating: *', [$this, 'handleCreating']);
        $events->listen('eloquent.created: *', [$this, 'handleCreated']);
        $events->listen('eloquent.updating: *', [$this, 'handleUpdating']);
        $events->listen('eloquent.updated: *', [$this, 'handleUpdated']);
        $events->listen('eloquent.deleting: *', [$this, 'handleDeleting']);
        $events->listen('eloquent.deleted: *', [$this, 'handleDeleted']);
        $events->listen('eloquent.restored: *', [$this, 'handleRestored']);
    }

    public function handleCreating(string $eventName, array $data): void
    {
    }

    public function handleCreated(string $eventName, array $data): void
    {
        $model = $this->extractModel($data);
        if (!$this->auditLogger->shouldAuditModel($model)) {
            return;
        }

        $snapshot = $this->auditLogger->snapshot($model);
        $this->auditLogger->log('CREATE', $model, [
            'new_values' => $snapshot,
            'changed_fields' => array_keys($snapshot),
        ]);
    }

    public function handleUpdating(string $eventName, array $data): void
    {
        $model = $this->extractModel($data);
        if (!$this->auditLogger->shouldAuditModel($model)) {
            return;
        }

        $diff = $this->auditLogger->diff($model);
        if (empty($diff['changed_fields']) && !$diff['password_changed']) {
            return;
        }

        $this->pendingUpdates[spl_object_id($model)] = $diff;
    }

    public function handleUpdated(string $eventName, array $data): void
    {
        $model = $this->extractModel($data);
        if (!$this->auditLogger->shouldAuditModel($model)) {
            return;
        }

        $key = spl_object_id($model);
        $diff = $this->pendingUpdates[$key] ?? null;
        unset($this->pendingUpdates[$key]);

        if (!$diff) {
            return;
        }

        if (!empty($diff['changed_fields'])) {
            $this->auditLogger->log('UPDATE', $model, [
                'old_values' => $diff['old_values'],
                'new_values' => $diff['new_values'],
                'changed_fields' => $diff['changed_fields'],
            ]);
        }

        if (!empty($diff['password_changed'])) {
            $this->auditLogger->log('PASSWORD_CHANGE', $model, [
                'old_values' => ['password' => '[REDACTED]'],
                'new_values' => ['password' => '[REDACTED]'],
                'changed_fields' => ['password'],
                'description' => 'Password changed for '.class_basename($model).' #'.$model->getKey(),
            ]);
        }

        $this->logSpecialAction($model, 'STATUS_CHANGE', config('audit.status_fields', []), $diff);
        $this->logSpecialAction($model, 'ROLE_CHANGE', config('audit.role_fields', []), $diff);
        $this->logSpecialAction($model, 'PERMISSION_CHANGE', config('audit.permission_fields', []), $diff);
    }

    public function handleDeleting(string $eventName, array $data): void
    {
        $model = $this->extractModel($data);
        if (!$this->auditLogger->shouldAuditModel($model)) {
            return;
        }

        $actionType = method_exists($model, 'isForceDeleting') && !$model->isForceDeleting()
            ? 'SOFT_DELETE'
            : 'DELETE';

        $this->pendingDeletes[spl_object_id($model)] = [
            'action_type' => $actionType,
            'old_values' => $this->auditLogger->snapshot($model, $model->getOriginal()),
        ];
    }

    public function handleDeleted(string $eventName, array $data): void
    {
        $model = $this->extractModel($data);
        if (!$this->auditLogger->shouldAuditModel($model)) {
            return;
        }

        $key = spl_object_id($model);
        $payload = $this->pendingDeletes[$key] ?? null;
        unset($this->pendingDeletes[$key]);

        if (!$payload) {
            return;
        }

        $this->auditLogger->log($payload['action_type'], $model, [
            'old_values' => $payload['old_values'],
            'changed_fields' => array_keys($payload['old_values']),
        ]);
    }

    public function handleRestored(string $eventName, array $data): void
    {
        $model = $this->extractModel($data);
        if (!$this->auditLogger->shouldAuditModel($model)) {
            return;
        }

        $snapshot = $this->auditLogger->snapshot($model);
        $this->auditLogger->log('RESTORE', $model, [
            'new_values' => $snapshot,
            'changed_fields' => array_keys($snapshot),
        ]);
    }

    private function logSpecialAction(Model $model, string $actionType, array $targetFields, array $diff): void
    {
        $changedFields = array_values(array_intersect($diff['changed_fields'], $targetFields));
        if ($changedFields === []) {
            return;
        }

        $oldValues = array_intersect_key($diff['old_values'], array_flip($changedFields));
        $newValues = array_intersect_key($diff['new_values'], array_flip($changedFields));

        $this->auditLogger->log($actionType, $model, [
            'old_values' => $oldValues,
            'new_values' => $newValues,
            'changed_fields' => $changedFields,
        ]);
    }

    private function extractModel(array $data): ?Model
    {
        $model = $data[0] ?? null;

        return $model instanceof Model ? $model : null;
    }
}
