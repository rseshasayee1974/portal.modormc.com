<?php

namespace App\Traits;

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Schema;

trait TracksModelChanges
{
    /**
     * Cache of table columns to avoid repeated database schema queries during request lifecycle.
     * @var array<string, array<string, int>>
     */
    protected static array $tableColumnsCache = [];

    public static function bootTracksModelChanges(): void
    {
        // Automatically attach audit observer to any model using this trait
        static::observe(\App\Observers\ModelAuditObserver::class);

        static::creating(function ($model) {
            if (Auth::check()) {
                $userId = Auth::id();
                if (static::hasTableColumn($model, 'created_by') && empty($model->created_by)) {
                    $model->created_by = $userId;
                }
                if (static::hasTableColumn($model, 'updated_by') && empty($model->updated_by)) {
                    $model->updated_by = $userId;
                }
                if (static::hasTableColumn($model, 'modified_by') && empty($model->modified_by)) {
                    $model->modified_by = $userId;
                }
            }
        });

        static::updating(function ($model) {
            if (Auth::check()) {
                $userId = Auth::id();
                if (static::hasTableColumn($model, 'updated_by')) {
                    $model->updated_by = $userId;
                }
                if (static::hasTableColumn($model, 'modified_by')) {
                    $model->modified_by = $userId;
                }
            }
        });

        static::deleting(function ($model) {
            if (Auth::check() && method_exists($model, 'isForceDeleting') && !$model->isForceDeleting()) {
                $userId = Auth::id();
                if (static::hasTableColumn($model, 'deleted_by')) {
                    $model->deleted_by = $userId;
                    $model->saveQuietly();
                }
            }
        });
    }

    /**
     * Check whether the model's table contains a specific audit column (cached in memory).
     */
    protected static function hasTableColumn($model, string $column): bool
    {
        $table = $model->getTable();
        if (!isset(static::$tableColumnsCache[$table])) {
            static::$tableColumnsCache[$table] = array_flip(
                Schema::getColumnListing($table)
            );
        }
        return isset(static::$tableColumnsCache[$table][$column]);
    }

    /**
     * Return list of actual dirty/changed fields with old and new values.
     * Returns empty array if no user-facing fields changed.
     *
     * @param array<int, string> $ignoredFields
     * @return array<int, array{field: string, old: mixed, new: mixed}>
     */
    public function getAuditChanges(array $ignoredFields = []): array
    {
        $defaultIgnored = [
            'created_at',
            'updated_at',
            'deleted_at',
            'created_by',
            'updated_by',
            'modified_by',
            'deleted_by',
            'remember_token',
            'password',
        ];

        $ignored = array_merge($defaultIgnored, $ignoredFields);
        if (property_exists($this, 'auditIgnore') && is_array($this->auditIgnore)) {
            $ignored = array_merge($ignored, $this->auditIgnore);
        }

        $dirty = $this->getDirty();
        $changes = [];

        foreach ($dirty as $field => $newValue) {
            if (in_array($field, $ignored, true)) {
                continue;
            }

            $oldValue = $this->getOriginal($field);

            if ($this->isAuditValueChanged($oldValue, $newValue)) {
                $changes[] = [
                    'field' => $field,
                    'old'   => $oldValue,
                    'new'   => $newValue,
                ];
            }
        }

        return $changes;
    }

    /**
     * Determine if an audit field value actually changed.
     */
    protected function isAuditValueChanged(mixed $oldValue, mixed $newValue): bool
    {
        if ($oldValue === $newValue) {
            return false;
        }

        // Ignore null vs empty string difference
        if (($oldValue === null && $newValue === '') || ($oldValue === '' && $newValue === null)) {
            return false;
        }

        // Compare numeric values numerically to prevent float/string false positives (e.g. 10.00 vs 10)
        if (is_numeric($oldValue) && is_numeric($newValue)) {
            return (float) $oldValue !== (float) $newValue;
        }

        // Array / JSON comparison
        if (is_array($oldValue) || is_array($newValue)) {
            return json_encode($oldValue) !== json_encode($newValue);
        }

        return (string) $oldValue !== (string) $newValue;
    }

    /**
     * Format a readable audit summary string for remarks.
     *
     * @param array<int, array{field: string, old: mixed, new: mixed}> $changes
     * @return string
     */
    public function getAuditRemarkString(array $changes = []): string
    {
        if (empty($changes)) {
            $changes = $this->getAuditChanges();
        }

        if (empty($changes)) {
            return '';
        }

        $parts = [];
        foreach ($changes as $change) {
            $oldStr = $this->normalizeAuditValue($change['old']);
            $newStr = $this->normalizeAuditValue($change['new']);
            $parts[] = "{$change['field']}: '{$oldStr}' => '{$newStr}'";
        }

        return implode(', ', $parts);
    }

    /**
     * Normalize audit value into string representation.
     */
    protected function normalizeAuditValue(mixed $value): string
    {
        if ($value === null) {
            return '';
        }
        if (is_bool($value)) {
            return $value ? 'true' : 'false';
        }
        if (is_array($value) || is_object($value)) {
            return json_encode($value, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        }
        return (string) $value;
    }
}
