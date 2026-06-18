<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AiMessage extends Model
{
    protected $fillable = [
        'conversation_id',
        'role',
        'content',
        'language',
        'provider',
        'model',
        'input_tokens',
        'output_tokens',
        'has_audio',
        'audio_path',
        'rag_sources',
        'metadata',
    ];

    protected $casts = [
        'has_audio'   => 'boolean',
        'rag_sources' => 'array',
        'metadata'    => 'array',
    ];

    // ── Relationships ──────────────────────────────────────────────────────

    public function conversation(): BelongsTo
    {
        return $this->belongsTo(AiConversation::class, 'conversation_id');
    }

    // ── Scopes ─────────────────────────────────────────────────────────────

    public function scopeUserMessages($query)
    {
        return $query->where('role', 'user');
    }

    public function scopeAssistantMessages($query)
    {
        return $query->where('role', 'assistant');
    }

    // ── Helpers ────────────────────────────────────────────────────────────

    /**
     * Format messages as an array suitable for OpenAI / Gemini chat history.
     */
    public static function formatForContext(iterable $messages): array
    {
        return collect($messages)
            ->filter(fn ($m) => in_array($m->role, ['user', 'assistant']))
            ->map(fn ($m) => ['role' => $m->role, 'content' => $m->content])
            ->values()
            ->toArray();
    }
}
