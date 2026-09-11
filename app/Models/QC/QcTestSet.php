<?php

namespace App\Models\QC;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class QcTestSet extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'mm_qc_test_sets';

    protected $fillable = [
        'qc_test_id',
        'set_number',
        'age_milestone_id',
        'age_days',
        'age_label',
        'casting_date',
        'place_of_casting',
        'scheduled_testing_date',
        'testing_date',
        'target_strength',
        'min_strength',
        'average_strength',
        'status',
        'error_reason',
        'client_sign_name',
        'client_signed_at',
        'qc_sign_name',
        'qc_signed_at',
        'remarks',
    ];

    protected $casts = [
        'set_number' => 'integer',
        'age_days' => 'integer',
        'casting_date' => 'datetime',
        'scheduled_testing_date' => 'date',
        'testing_date' => 'datetime',
        'target_strength' => 'decimal:4',
        'min_strength' => 'decimal:4',
        'average_strength' => 'decimal:4',
        'client_signed_at' => 'datetime',
        'qc_signed_at' => 'datetime',
    ];

    public function test()
    {
        return $this->belongsTo(QcTest::class, 'qc_test_id');
    }

    public function milestone()
    {
        return $this->belongsTo(QcConfigAgeMilestone::class, 'age_milestone_id');
    }

    public function specimens()
    {
        return $this->hasMany(QcTestSpecimen::class, 'qc_test_set_id')->orderBy('specimen_index');
    }

    public function measurements()
    {
        return $this->hasMany(QcTestMeasurement::class, 'qc_test_set_id');
    }

    /**
     * Calculate and update set average strength from valid specimens.
     */
    public function recalculateAverageStrength(): ?float
    {
        $strengths = $this->specimens()
            ->whereNotNull('strength_mpa')
            ->pluck('strength_mpa')
            ->map(fn($v) => (float)$v)
            ->all();

        if (empty($strengths)) {
            $this->average_strength = null;
            $this->save();
            return null;
        }

        $avg = round(array_sum($strengths) / count($strengths), 2);
        $this->average_strength = $avg;
        $this->save();
        return $avg;
    }
}
