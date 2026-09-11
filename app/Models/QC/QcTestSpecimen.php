<?php

namespace App\Models\QC;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class QcTestSpecimen extends Model
{
    use HasFactory;

    protected $table = 'mm_qc_test_specimens';

    protected $fillable = [
        'qc_test_set_id',
        'qc_test_id',
        'specimen_index',
        'set_specimen_index',
        'identification_mark',
        'weight_kg',
        'load_kn',
        'cross_sectional_area',
        'density_kg_m3',
        'strength_mpa',
        'failure_type',
        'status',
        'calculation_error',
    ];

    protected $casts = [
        'specimen_index' => 'integer',
        'set_specimen_index' => 'integer',
        'weight_kg' => 'decimal:3',
        'load_kn' => 'decimal:2',
        'cross_sectional_area' => 'decimal:4',
        'density_kg_m3' => 'decimal:2',
        'strength_mpa' => 'decimal:2',
    ];

    public function set()
    {
        return $this->belongsTo(QcTestSet::class, 'qc_test_set_id');
    }

    public function test()
    {
        return $this->belongsTo(QcTest::class, 'qc_test_id');
    }

    public function measurements()
    {
        return $this->hasMany(QcTestMeasurement::class, 'qc_test_specimen_id');
    }
}
