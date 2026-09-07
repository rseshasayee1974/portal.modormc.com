<?php

namespace App\Models\QC;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class QcTestResult extends Model
{
    use HasFactory;

    protected $table = 'qc_test_results';

    protected $fillable = [
        'qc_test_id',
        'parameter_id',
        'final_value',
        'final_text',
        'status',
        'criteria_snapshot',
    ];

    protected $casts = [
        'final_value' => 'decimal:4',
        'criteria_snapshot' => 'array',
    ];

    public function test()
    {
        return $this->belongsTo(QcTest::class, 'qc_test_id');
    }

    public function parameter()
    {
        return $this->belongsTo(QcTestParameter::class, 'parameter_id');
    }
}
