<?php
/*
Author: ragul-onemodo
Created: 2026-10-09 12:27:48 Asia/Calcutta (UTC+05:30)
*/
namespace App\Models;

use App\Traits\{PlantScoping, TracksModelChanges};
use Illuminate\Database\Eloquent\{Model, SoftDeletes};

class CrmAttachment extends Model
{
    use SoftDeletes, PlantScoping, TracksModelChanges;
    protected $table = 'mm_crm_attachments';
    protected $fillable = ['plant_id', 'lead_id', 'name', 'path', 'size'];
    protected $hidden = ['path'];
}
