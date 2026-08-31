<?php

namespace App\Models;
use App\Traits\TracksModelChanges;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Traits\PlantScoping;

class Payslip extends Model
{
        use HasFactory, SoftDeletes, PlantScoping, TracksModelChanges;

    protected $table = 'mm_payslips';

    protected $fillable = [
        'plant_id',
        'payroll_period_id',
        'personnel_id',
        'payslip_no',
        'working_days',
        'present_days',
        'absent_days',
        'paid_leave_days',
        'gross_salary',
        'total_earnings',
        'total_deductions',
        'net_salary',
        'status',
        'payment_reference',
    ];

    protected $casts = [
        'gross_salary' => 'decimal:2',
        'total_earnings' => 'decimal:2',
        'total_deductions' => 'decimal:2',
        'net_salary' => 'decimal:2',
    ];

    public function plant()
    {
        return $this->belongsTo(Plant::class, 'plant_id');
    }

    public function payrollPeriod()
    {
        return $this->belongsTo(PayrollPeriod::class, 'payroll_period_id');
    }

    public function personnel()
    {
        return $this->belongsTo(Personnel::class, 'personnel_id');
    }

    public function items()
    {
        return $this->hasMany(PayslipItem::class, 'payslip_id');
    }
}
