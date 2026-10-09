<?php
/*
Author: ragul-onemodo
Created: 2026-10-09 12:27:48 Asia/Calcutta (UTC+05:30)
*/
namespace App\Models;

use App\Traits\{PlantScoping, TracksModelChanges};
use Illuminate\Database\Eloquent\{Model, SoftDeletes};

class CrmActivity extends Model
{
    use SoftDeletes, PlantScoping, TracksModelChanges;
    public const TYPES = ['Call', 'Meeting', 'Site Visit', 'Task', 'Note'];
    protected $table = 'mm_crm_activities';
    protected $fillable = ['plant_id', 'lead_id', 'type', 'subject', 'description', 'due_at', 'completed_at', 'assigned_to'];
    protected $casts = ['due_at' => 'datetime', 'completed_at' => 'datetime'];
    public function lead() { return $this->belongsTo(CrmLead::class, 'lead_id'); }
    public function owner() { return $this->belongsTo(User::class, 'assigned_to'); }
    public function creator() { return $this->belongsTo(User::class, 'created_by'); }
}
