<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
class EmployeeSalaryStructure extends Model
{
        use HasFactory, SoftDeletes;

    protected $table = 'mm_employee_salary_structures';

    protected $fillable = [
        'personnel_id',
        'salary_component_id',
        'amount',
        'effective_from',
        'effective_to',
    ];

    protected $casts = [
        'effective_from' => 'date',
        'effective_to' => 'date',
        'amount' => 'decimal:2',
    ];

    public function personnel()
    {
        return $this->belongsTo(Personnel::class, 'personnel_id');
    }

    public function salaryComponent()
    {
        return $this->belongsTo(SalaryComponent::class, 'salary_component_id');
    }
}
