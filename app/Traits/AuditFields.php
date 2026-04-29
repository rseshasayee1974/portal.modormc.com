<?php

namespace App\Traits;

use Illuminate\Support\Facades\Auth;

trait AuditFields
{
    /**
     * Boot the audit fields trait for the model.
     *
     * @return void
     */
    public static function bootAuditFields()
    {
        static::creating(function ($model) {
            if (!$model->isDirty('created_by')) {
                $model->created_by = Auth::id();
            }
            
            // Force the modification timestamp to be null on initial creation
            $updatedColumn = $model->getUpdatedAtColumn();
            if ($updatedColumn) {
                $model->{$updatedColumn} = null;
            }
        });

        static::updating(function ($model) {
            if (!$model->isDirty('updated_by')) {
                $model->updated_by = Auth::id();
            }
            // updated_at will be handled by Laravel as the UPDATED_AT replacement
        });

        if (method_exists(static::class, 'bootSoftDeletes')) {
            static::deleting(function ($model) {
                if (!$model->isForceDeleting()) {
                    $model->deleted_by = Auth::id();
                    $model->save();
                }
            });
        }
    }

    /**
     * Get the name of the "updated at" column.
     *
     * @return string|null
     */
    public function getUpdatedAtColumn()
    {
        return 'updated_at';
    }

    /**
     * Relationship: User who created the record.
     */
    public function creator()
    {
        return $this->belongsTo(\App\Models\User::class, 'created_by');
    }

    /**
     * Relationship: User who last modified the record.
     */
    public function modifier()
    {
        return $this->belongsTo(\App\Models\User::class, 'updated_by');
    }

    /**
     * Relationship: User who deleted the record.
     */
    public function destroyer()
    {
        return $this->belongsTo(\App\Models\User::class, 'deleted_by');
    }
}
