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

    protected $table = 'qc_tests';

    protected $fillable = [
        'plant_id',
        'sample_id',
        'test_type_id',
        'test_no',
        'test_date',
        'tested_by',
        'overall_status',
        'evaluated_at',
        'reviewed_by',
        'reviewed_at',
        'approval_status',
        'retest_reason',
        'remarks',
        'created_by',
        'updated_by',
        'deleted_by',
    ];

    protected $casts = [
        'test_date' => 'datetime',
        'evaluated_at' => 'datetime',
        'reviewed_at' => 'datetime',
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
}
