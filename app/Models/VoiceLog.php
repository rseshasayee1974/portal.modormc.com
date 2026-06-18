<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class VoiceLog extends Model
{
    protected $fillable = [
        'conversation_id',
        'user_id',
        'type',
        'provider',
        'language',
        'input_audio_path',
        'transcript',
        'input_text',
        'output_audio_path',
        'duration_ms',
        'status',
        'error',
        'metadata',
    ];

    protected $casts = [
        'metadata'    => 'array',
        'duration_ms' => 'integer',
    ];

    // ── Relationships ──────────────────────────────────────────────────────

    public function conversation(): BelongsTo
    {
        return $this->belongsTo(AiConversation::class, 'conversation_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    // ── Scopes ─────────────────────────────────────────────────────────────

    public function scopeStt($query)
    {
        return $query->where('type', 'stt');
    }

    public function scopeTts($query)
    {
        return $query->where('type', 'tts');
    }

    public function scopeFailed($query)
    {
        return $query->where('status', 'failed');
    }
}
