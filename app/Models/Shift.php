<?php

namespace App\Models;
use App\Traits\TracksModelChanges;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
class Shift extends Model
{
        use HasFactory, SoftDeletes, TracksModelChanges;

    protected $table = 'mm_shifts';

    protected $fillable = [
        'shift_name',
        'start_time',
        'end_time',
        'grace_time',
        'working_hours',
        'is_night_shift',
    ];

    protected $casts = [
        'is_night_shift' => 'boolean',
    ];

    public function personnels()
    {
        return $this->belongsToMany(Personnel::class, 'mm_employee_shifts', 'shift_id', 'personnel_id')
            ->withPivot('effective_from', 'effective_to')
            ->withTimestamps();
    }
}
