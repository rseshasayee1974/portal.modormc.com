<?php

namespace App\Models\QC;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class QcTestMeasurement extends Model
{
    use HasFactory;

    protected $table = 'mm_qc_test_measurements';

    protected $fillable = [
        'qc_test_id',
        'qc_test_set_id',
        'qc_test_specimen_id',
        'parameter_id',
        'value_text',
        'value_numeric',
        'is_calculated',
        'row_index',
    ];

    protected $casts = [
        'qc_test_set_id' => 'integer',
        'qc_test_specimen_id' => 'integer',
        'value_numeric' => 'decimal:4',
        'is_calculated' => 'boolean',
        'row_index' => 'integer',
    ];

    public function test()
    {
        return $this->belongsTo(QcTest::class, 'qc_test_id');
    }

    public function set()
    {
        return $this->belongsTo(QcTestSet::class, 'qc_test_set_id');
    }

    public function specimen()
    {
        return $this->belongsTo(QcTestSpecimen::class, 'qc_test_specimen_id');
    }

    public function parameter()
    {
        return $this->belongsTo(QcTestParameter::class, 'parameter_id');
    }
}
