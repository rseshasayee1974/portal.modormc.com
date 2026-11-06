<?php
/*
Author: ragul-onemodo
Created: 2026-11-06 14:43:29 Asia/Calcutta (UTC+05:30)
*/
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class NotificationWhatsapp extends Model
{
    use SoftDeletes;
    protected $table = 'mm_notification_whatsapps';
    protected $fillable = ['plant_id', 'type', 'role_id', 'user_id', 'mobile', 'status', 'created_by', 'updated_by', 'deleted_by'];
    protected $casts = ['status' => 'boolean', 'role_id' => 'integer', 'user_id' => 'integer'];
    public function user() { return $this->belongsTo(User::class); }
    public function role() { return $this->belongsTo(Role::class); }
}
