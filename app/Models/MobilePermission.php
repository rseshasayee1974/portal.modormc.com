<?php
/*
Author: Cipher
Created: 2026-11-13 10:48:11 Asia/Calcutta (UTC+05:30)
*/

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MobilePermission extends Model
{
    protected $table = 'mm_mobile_permissions';

    protected $fillable = ['name', 'module', 'action', 'description', 'is_system'];

    protected $casts = ['is_system' => 'boolean'];
}
