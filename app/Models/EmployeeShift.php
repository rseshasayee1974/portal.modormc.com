<?php

namespace App\Models;
use App\Traits\TracksModelChanges;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
class EmployeeShift extends Model
{
        use HasFactory, SoftDeletes, TracksModelChanges;

    protected $table = 'mm_employee_shifts';

    protected $fillable = [
        'personnel_id',
        'shift_id',
        'effective_from',
        'effective_to',
    ];

    public function personnel()
    {
        return $this->belongsTo(Personnel::class, 'personnel_id');
    }

    public function shift()
    {
        return $this->belongsTo(Shift::class, 'shift_id');
    }
}
