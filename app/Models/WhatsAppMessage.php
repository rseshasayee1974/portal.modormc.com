<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class WhatsAppMessage extends Model
{
    protected $table = 'mm_whatsapp_messages';

    protected $fillable = [
        'entity_id', 'plant_id', 'category', 'origin', 'origin_id', 'provider',
        'contact', 'template', 'parameters', 'status', 'response_status',
        'provider_response', 'error_message', 'submitted_at', 'created_by', 'updated_by',
    ];

    protected $casts = [
        'parameters' => 'array',
        'origin_id' => 'integer',
        'submitted_at' => 'datetime',
        'response_status' => 'integer',
    ];
}
