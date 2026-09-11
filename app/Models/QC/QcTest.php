<?php

namespace App\Models\QC;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Models\Plant;
use App\Models\User;
use App\Models\Image;

class QcTest extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'mm_qc_tests';

    protected $fillable = [
        'plant_id',
        'sample_id',
        'test_type_id',
        'config_id',
        'test_no',
        'scheduled_date',
        'age_days',
        'target_strength',
        'min_strength',
        'unit',
        'test_date',
        'tested_by',
        'overall_status',
        'evaluated_at',
        'reviewed_by',
        'reviewed_at',
        'approval_status',
        'retest_reason',
        'remarks',
        'configuration_snapshot',
        'created_by',
        'updated_by',
        'deleted_by',
    ];

    protected $casts = [
        'config_id' => 'integer',
        'scheduled_date' => 'date',
        'age_days' => 'integer',
        'target_strength' => 'float',
        'min_strength' => 'float',
        'test_date' => 'datetime',
        'evaluated_at' => 'datetime',
        'reviewed_at' => 'datetime',
        'configuration_snapshot' => 'array',
    ];

    public function plant()
    {
        return $this->belongsTo(Plant::class, 'plant_id');
    }

    public function sample()
    {
        return $this->belongsTo(QcSample::class, 'sample_id');
    }

    public function testType()
    {
        return $this->belongsTo(QcTestType::class, 'test_type_id');
    }

    public function config()
    {
        return $this->belongsTo(QcProductGradeConfig::class, 'config_id');
    }

    public function sets()
    {
        return $this->hasMany(QcTestSet::class, 'qc_test_id')->orderBy('set_number');
    }

    public function specimens()
    {
        return $this->hasMany(QcTestSpecimen::class, 'qc_test_id')->orderBy('specimen_index');
    }

    public function tester()
    {
        return $this->belongsTo(User::class, 'tested_by');
    }

    public function reviewer()
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function measurements()
    {
        return $this->hasMany(QcTestMeasurement::class, 'qc_test_id');
    }

    public function results()
    {
        return $this->hasMany(QcTestResult::class, 'qc_test_id');
    }

    public function photos()
    {
        return $this->hasMany(Image::class, 'ref_no', 'id')->where('category', 'QC_TEST');
    }

    /**
     * Ensure the test has its 3 sets and 9 specimens initialized.
     */
    public function ensureSetsAndSpecimensInitialized(): void
    {
        if ($this->sets()->count() > 0) {
            return;
        }

        $castingDate = $this->sample?->sample_date ?? $this->test_date ?? now();
        $placeOfCasting = $this->sample?->source_location ?? 'SITE / PLANT';

        // Default sets: Set 1 (7 Days), Set 2 (28 Days), Set 3 (28 Days)
        $setConfigs = [
            1 => ['age_days' => 7, 'label' => '7 Days'],
            2 => ['age_days' => 28, 'label' => '28 Days'],
            3 => ['age_days' => 28, 'label' => '28 Days'],
        ];

        // Check if a Product Grade Config exists with configured milestones
        if ($this->config && $this->config->milestones()->count() > 0) {
            $setConfigs = [];
            foreach ($this->config->milestones as $m) {
                $setConfigs[$m->set_number] = [
                    'milestone_id' => $m->id,
                    'age_days' => $m->age_days,
                    'label' => $m->age_label,
                    'target_strength' => $m->target_value,
                    'min_strength' => $m->min_value,
                ];
            }
        }

        $cubeIndex = 1;
        foreach ($setConfigs as $setNum => $sInfo) {
            $ageDays = $sInfo['age_days'];
            $testingDate = (clone $castingDate)->addDays($ageDays);

            $testSet = $this->sets()->create([
                'set_number' => $setNum,
                'age_milestone_id' => $sInfo['milestone_id'] ?? null,
                'age_days' => $ageDays,
                'age_label' => $sInfo['label'] ?? "{$ageDays} Days",
                'casting_date' => $castingDate,
                'place_of_casting' => $placeOfCasting,
                'scheduled_testing_date' => $testingDate->toDateString(),
                'testing_date' => $testingDate,
                'target_strength' => $sInfo['target_strength'] ?? $this->target_strength,
                'min_strength' => $sInfo['min_strength'] ?? $this->min_strength,
                'status' => 'pending',
            ]);

            // Create 3 specimens for each set (continuous 1 to 9)
            for ($i = 1; $i <= 3; $i++) {
                $testSet->specimens()->create([
                    'qc_test_id' => $this->id,
                    'specimen_index' => $cubeIndex,
                    'set_specimen_index' => $i,
                    'identification_mark' => (string)$cubeIndex,
                    'cross_sectional_area' => 22500,
                    'status' => 'pending',
                ]);
                $cubeIndex++;
            }
        }
    }
}
