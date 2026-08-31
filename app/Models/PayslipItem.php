<?php

namespace App\Models;
use App\Traits\TracksModelChanges;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
class PayslipItem extends Model
{
        use HasFactory, SoftDeletes, TracksModelChanges;

    protected $table = 'mm_payslip_items';

    protected $fillable = [
        'payslip_id',
        'salary_component_id',
        'component_name',
        'type',
        'amount',
        'calculation_source',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
    ];

    public function payslip()
    {
        return $this->belongsTo(Payslip::class, 'payslip_id');
    }

    public function salaryComponent()
    {
        return $this->belongsTo(SalaryComponent::class, 'salary_component_id');
    }
}
