<?php

namespace App\Models;
use App\Traits\TracksModelChanges;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
class ReportSchedule extends Model
{
        use HasFactory, SoftDeletes, TracksModelChanges;

    protected $table = 'mm_report_schedules';

    protected $fillable = [
        'plant_id',
        'report_type',
        'report_params',
        'email_recipients',
        'frequency',
        'schedule_time',
        'last_run_at',
        'is_active',
        'created_by',
        'updated_by',
        'deleted_by',
    ];

    protected $casts = [
        'report_params' => 'array',
        'last_run_at' => 'datetime',
        'is_active' => 'boolean',
    ];

    public function plant()
    {
        return $this->belongsTo(Plant::class, 'plant_id');
    }
}
