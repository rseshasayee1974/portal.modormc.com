<?php
/*
Author: Cipher
Created: 2026-11-13 10:33:00 Asia/Calcutta (UTC+05:30)
*/

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MobileModulePermission extends Model
{
    protected $table = 'mm_mobile_module_permissions';

    protected $fillable = ['user_id', 'plant_id', 'module', 'actions', 'created_by', 'updated_by'];

    protected $casts = ['actions' => 'array'];
}
