<?php

namespace App\Models\QC;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Models\Plant;
use App\Models\User;

class QcTestType extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'mm_qc_test_types';

    protected $fillable = [
        'plant_id',
        'code',
        'name',
        'category',
        'material_type',
        'standard_reference',
        'calculation_type',
        'custom_calculator_class',
        'layout_type',
        'grid_config',
        'description',
        'is_active',
        'created_by',
        'updated_by',
        'deleted_by',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'grid_config' => 'array',
    ];

    public function plant()
    {
        return $this->belongsTo(Plant::class, 'plant_id');
    }

    public function parameters()
    {
        return $this->hasMany(QcTestParameter::class, 'test_type_id')->orderBy('display_order');
    }

    public function materialMappings()
    {
        return $this->hasMany(QcMaterialTest::class, 'test_type_id');
    }

    public function schedules()
    {
        return $this->hasMany(QcTestSchedule::class, 'test_type_id');
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
