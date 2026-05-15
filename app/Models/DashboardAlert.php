<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Traits\AuditFields;

class DashboardAlert extends Model
{
    use SoftDeletes, AuditFields;

    protected $table = 'mm_dashboard_alerts';

    protected $fillable = [
        'date_time_off',
        'date_time_on',
        'type',
        'status',
        'message',
        'plant_id',
        'entity_id',
        'created_by',
        'updated_by',
        'deleted_by'
    ];

    protected $casts = [
        'date_time_off' => 'datetime',
        'date_time_on' => 'datetime',
    ];
}
