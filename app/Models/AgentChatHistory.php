<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AgentChatHistory extends Model
{
    protected $table = 'agent_chat_histories';

    protected $fillable = [
        'user_id',
        'plant_id',
        'agent_name',
        'agent_class',
        'session_language',
        'messages',
        'message_count',
        'summary',
    ];

    protected $casts = [
        'messages'      => 'array',
        'message_count' => 'integer',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
