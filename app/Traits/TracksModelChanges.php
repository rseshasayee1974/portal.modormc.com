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

    public function getAuditChanges(array $ignoredFields = []): array
    {
        return [];
    }

    public function getAuditRemarkString(): string
    {
        return '';
    }

    protected function normalizeAuditValue(mixed $value): string
    {
        return '';
    }
}
