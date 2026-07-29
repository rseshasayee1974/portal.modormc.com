<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
class LeaveType extends Model
{
        use HasFactory, SoftDeletes;

    protected $table = 'mm_leave_types';

    protected $fillable = [
        'name',
        'is_paid',
        'max_days_per_year',
        'carry_forward',
    ];

    protected $casts = [
        'is_paid' => 'boolean',
        'carry_forward' => 'boolean',
    ];

    public function balances()
    {
        return $this->hasMany(EmployeeLeaveBalance::class, 'leave_type_id');
    }

    public function applications()
    {
        return $this->hasMany(LeaveApplication::class, 'leave_type_id');
    }
}