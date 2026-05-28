<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Traits\AuditFields;
use App\Traits\PlantScoping;

class Attendance extends Model
{
    use HasFactory, SoftDeletes, AuditFields, PlantScoping;

    protected $table = 'mm_attendances';

    protected $fillable = [
        'plant_id',
        'personnel_id',
        'shift_id',
        'attendance_date',
        'check_in',
        'check_out',
        'worked_hours',
        'overtime_hours',
        'late_hours',
        'status',
        'is_late',
        'is_early_departure',
        'source',
    ];

    protected $casts = [
        'is_late' => 'boolean',
        'is_early_departure' => 'boolean',
        'check_in' => 'datetime',
        'check_out' => 'datetime',
        'attendance_date' => 'date',
    ];

    public function plant()
    {
        return $this->belongsTo(Plant::class, 'plant_id');
    }

    public function personnel()
    {
        return $this->belongsTo(Personnel::class, 'personnel_id');
    }

    public function shift()
    {
        return $this->belongsTo(Shift::class, 'shift_id');
    }
}
