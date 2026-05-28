<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Traits\AuditFields;
use App\Traits\PlantScoping;

class SalaryComponent extends Model
{
    use HasFactory, SoftDeletes, AuditFields, PlantScoping;

    protected $table = 'mm_salary_components';

    protected $fillable = [
        'plant_id',
        'name',
        'type',
        'calculation_type',
        'default_value',
        'is_taxable',
        'is_statutory',
        'config',
    ];

    protected $casts = [
        'is_taxable' => 'boolean',
        'is_statutory' => 'boolean',
        'config' => 'json',
    ];

    public function plant()
    {
        return $this->belongsTo(Plant::class, 'plant_id');
    }

    public function structures()
    {
        return $this->hasMany(EmployeeSalaryStructure::class, 'salary_component_id');
    }
}
