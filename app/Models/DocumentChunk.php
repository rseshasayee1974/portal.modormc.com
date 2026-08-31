<?php

namespace App\Models;
use App\Traits\TracksModelChanges;

use Illuminate\Database\Eloquent\SoftDeletes;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DocumentChunk extends Model
{
    use SoftDeletes, TracksModelChanges;
    protected $fillable = [
        'rag_document_id',
        'entity_id',
        'chunk_index',
        'content',
        'embedding',
        'content_hash',
        'token_count',
        'is_active',
        'deleted_by',
    ];

    protected $casts = [
        'is_active'   => 'boolean',
        'chunk_index' => 'integer',
        'token_count' => 'integer',
    ];

    // ── Relationships ──────────────────────────────────────────────────────

    public function ragDocument(): BelongsTo
    {
        return $this->belongsTo(RagDocument::class);
    }

    // ── Scopes ─────────────────────────────────────────────────────────────

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeForEntity($query, ?int $entityId)
    {
        return $query->where('entity_id', $entityId);
    }

    // ── Helpers ────────────────────────────────────────────────────────────

    /**
     * Get the decoded embedding as a float array.
     */
    public function getEmbeddingArray(): array
    {
        return $this->embedding ? json_decode($this->embedding, true) : [];
    }
}
