<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Traits\PlantScoping;

class PayrollPeriod extends Model
{
        use HasFactory, SoftDeletes, PlantScoping;

    protected $table = 'mm_payroll_periods';

    protected $fillable = [
        'plant_id',
        'name',
        'from_date',
        'to_date',
        'status',
    ];

    protected $casts = [
        'from_date' => 'date',
        'to_date' => 'date',
    ];

    public function plant()
    {
        return $this->belongsTo(Plant::class, 'plant_id');
    }

    public function payslips()
    {
        return $this->hasMany(Payslip::class, 'payroll_period_id');
    }
}
