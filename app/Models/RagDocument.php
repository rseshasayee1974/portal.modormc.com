<?php

namespace App\Models;
use App\Traits\TracksModelChanges;

use Illuminate\Database\Eloquent\SoftDeletes;

use Illuminate\Database\Eloquent\Model;

class RagDocument extends Model
{
    use SoftDeletes, TracksModelChanges;
    protected $fillable = [
        'entity_id',
        'source_type',
        'source_id',
        'title',
        'content',
        'embedding',
        'content_hash',
        'token_count',
        'is_active',
        'deleted_by',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    /**
     * Scope: Only active documents.
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope: Filter by entity.
     */
    public function scopeForEntity($query, ?int $entityId)
    {
        if ($entityId) {
            return $query->where(function ($q) use ($entityId) {
                $q->where('entity_id', $entityId)->orWhereNull('entity_id');
            });
        }
        return $query;
    }

    /**
     * Scope: Filter by source type.
     */
    public function scopeOfType($query, string $type)
    {
        return $query->where('source_type', $type);
    }
}
