<?php

namespace App\Models\QC;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class QcConfigAgeMilestone extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'mm_qc_config_age_milestones';

    protected $fillable = [
        'config_id',
        'set_number',
        'age_days',
        'age_label',
        'target_percentage',
        'target_value',
        'min_value',
        'max_value',
        'rule_type',
        'tolerance',
        'display_order',
        'created_by',
        'updated_by',
        'deleted_by',
    ];

    protected $casts = [
        'set_number' => 'integer',
        'age_days' => 'integer',
        'target_percentage' => 'decimal:2',
        'target_value' => 'decimal:4',
        'min_value' => 'decimal:4',
        'max_value' => 'decimal:4',
        'tolerance' => 'decimal:4',
        'display_order' => 'integer',
    ];

    public function config()
    {
        return $this->belongsTo(QcProductGradeConfig::class, 'config_id');
    }

    public function testSets()
    {
        return $this->hasMany(QcTestSet::class, 'age_milestone_id');
    }
}
