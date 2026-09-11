<?php

namespace App\Models\QC;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Models\Plant;
use App\Models\Product;
use App\Models\ConcreteGrade;
use App\Models\User;

class QcProductGradeConfig extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'mm_qc_product_grade_configs';

    protected $fillable = [
        'plant_id',
        'material_id',
        'concrete_grade_id',
        'test_type_id',
        'standard_id',
        'specimen_shape',
        'specimen_dimensions',
        'specimens_per_set',
        'total_sets',
        'formula_bindings',
        'version',
        'is_active',
        'created_by',
        'updated_by',
        'deleted_by',
    ];

    protected $casts = [
        'specimens_per_set' => 'integer',
        'total_sets' => 'integer',
        'version' => 'integer',
        'is_active' => 'boolean',
        'formula_bindings' => 'array',
    ];

    public function plant()
    {
        return $this->belongsTo(Plant::class, 'plant_id');
    }

    public function material()
    {
        return $this->belongsTo(Product::class, 'material_id');
    }

    public function concreteGrade()
    {
        return $this->belongsTo(ConcreteGrade::class, 'concrete_grade_id');
    }

    public function testType()
    {
        return $this->belongsTo(QcTestType::class, 'test_type_id');
    }

    public function standard()
    {
        return $this->belongsTo(QcStandard::class, 'standard_id');
    }

    public function milestones()
    {
        return $this->hasMany(QcConfigAgeMilestone::class, 'config_id')->orderBy('display_order')->orderBy('age_days');
    }

    public function tests()
    {
        return $this->hasMany(QcTest::class, 'config_id');
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Generate an immutable configuration snapshot for versioning on a test.
     */
    public function generateConfigurationSnapshot(): array
    {
        $this->loadMissing(['standard', 'testType.parameters.unitRef', 'milestones']);

        $parametersSnapshot = [];
        if ($this->testType && $this->testType->parameters) {
            foreach ($this->testType->parameters as $p) {
                $parametersSnapshot[] = [
                    'id' => $p->id,
                    'code' => $p->code,
                    'name' => $p->name,
                    'data_type' => $p->data_type,
                    'unit' => $p->unit,
                    'qc_unit_id' => $p->qc_unit_id,
                    'unit_symbol' => $p->unitRef?->symbol ?? $p->unit,
                    'scope' => $p->scope,
                    'is_required' => $p->is_required,
                    'is_calculated' => $p->is_calculated,
                    'formula' => $p->formula,
                    'formula_expression' => $p->formula_expression,
                    'rule_type' => $p->rule_type,
                    'min_value' => $p->min_value,
                    'max_value' => $p->max_value,
                    'target_value' => $p->target_value,
                    'tolerance' => $p->tolerance,
                ];
            }
        }

        $milestonesSnapshot = [];
        foreach ($this->milestones as $m) {
            $milestonesSnapshot[] = [
                'id' => $m->id,
                'set_number' => $m->set_number,
                'age_days' => $m->age_days,
                'age_label' => $m->age_label,
                'target_percentage' => (float)$m->target_percentage,
                'target_value' => $m->target_value !== null ? (float)$m->target_value : null,
                'min_value' => $m->min_value !== null ? (float)$m->min_value : null,
                'max_value' => $m->max_value !== null ? (float)$m->max_value : null,
                'rule_type' => $m->rule_type,
                'tolerance' => $m->tolerance !== null ? (float)$m->tolerance : null,
            ];
        }

        return [
            'config_id' => $this->id,
            'version' => $this->version,
            'standard_code' => $this->standard?->code ?? 'IS 516',
            'standard_name' => $this->standard?->name ?? 'Method of Tests for Strength of Concrete',
            'specimen_shape' => $this->specimen_shape,
            'specimen_dimensions' => $this->specimen_dimensions,
            'specimens_per_set' => $this->specimens_per_set,
            'total_sets' => $this->total_sets,
            'formula_bindings' => $this->formula_bindings ?? [
                'LOAD' => 'LOAD_KN',
                'AREA' => 22500,
                'VOLUME' => 0.003375,
                'WEIGHT' => 'SPECIMEN_WT',
            ],
            'parameters' => $parametersSnapshot,
            'milestones' => $milestonesSnapshot,
            'snapshot_created_at' => now()->toIso8601String(),
        ];
    }
}
