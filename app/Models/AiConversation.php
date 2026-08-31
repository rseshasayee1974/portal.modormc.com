<?php

namespace App\Models;
use App\Traits\TracksModelChanges;

use Illuminate\Database\Eloquent\SoftDeletes;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class AiConversation extends Model
{
    use SoftDeletes, TracksModelChanges;
    protected $fillable = [
        'entity_id',
        'plant_id',
        'user_id',
        'session_token',
        'channel',
        'language',
        'customer_name',
        'customer_email',
        'customer_phone',
        'is_escalated',
        'escalated_at',
        'status',
        'message_count',
        'summary',
        'deleted_by',
    ];

    protected $casts = [
        'is_escalated' => 'boolean',
        'escalated_at' => 'datetime',
        'message_count' => 'integer',
    ];

    // ── Relationships ──────────────────────────────────────────────────────

    public function messages(): HasMany
    {
        return $this->hasMany(AiMessage::class, 'conversation_id');
    }

    public function voiceLogs(): HasMany
    {
        return $this->hasMany(VoiceLog::class, 'conversation_id');
    }

    // ── Scopes ─────────────────────────────────────────────────────────────

    public function scopeForEntity($query, ?int $entityId)
    {
        return $query->where('entity_id', $entityId);
    }

    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }

    public function scopeEscalated($query)
    {
        return $query->where('is_escalated', true);
    }

    // ── Helpers ────────────────────────────────────────────────────────────

    /**
     * Generate a unique, secure session token.
     */
    public static function generateToken(): string
    {
        return Str::random(64);
    }

    public function close(): void
    {
        $this->update(['status' => 'closed']);
    }

    public function escalate(): void
    {
        $this->update([
            'is_escalated' => true,
            'escalated_at' => now(),
            'status'       => 'escalated',
        ]);
    }

    public function incrementMessageCount(): void
    {
        $this->increment('message_count');
    }
}
