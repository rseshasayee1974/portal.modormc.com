<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
class EmployeeLeaveBalance extends Model
{
        use HasFactory, SoftDeletes;

    protected $table = 'mm_employee_leave_balances';

    protected $fillable = [
        'personnel_id',
        'leave_type_id',
        'year',
        'opening_balance',
        'accrued',
        'used',
        'balance',
    ];

    public function personnel()
    {
        return $this->belongsTo(Personnel::class, 'personnel_id');
    }

    public function leaveType()
    {
        return $this->belongsTo(LeaveType::class, 'leave_type_id');
    }
}
