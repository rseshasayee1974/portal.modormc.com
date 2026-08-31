<?php

namespace App\Models;
use App\Traits\TracksModelChanges;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
class HrmsAuditLog extends Model
{
        use HasFactory, SoftDeletes, TracksModelChanges;

    protected $table = 'mm_hrms_audit_logs';

    protected $fillable = [
        'user_id',
        'personnel_id',
        'module',
        'action',
        'record_id',
        'old_values',
        'new_values',
        'ip_address',
        'user_agent',
    ];

    protected $casts = [
        'old_values' => 'json',
        'new_values' => 'json',
    ];

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function personnel()
    {
        return $this->belongsTo(Personnel::class, 'personnel_id');
    }
}
