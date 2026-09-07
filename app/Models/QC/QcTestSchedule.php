<?php

namespace App\Models\QC;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Models\Plant;
use App\Models\Product;
use App\Models\User;

class QcTestSchedule extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'qc_test_schedules';

    protected $fillable = [
        'plant_id',
        'material_id',
        'test_type_id',
        'frequency_type',
        'frequency_value',
        'is_active',
        'created_by',
        'updated_by',
        'deleted_by',
    ];

    protected $casts = [
        'frequency_value' => 'integer',
        'is_active' => 'boolean',
    ];

    public function plant()
    {
        return $this->belongsTo(Plant::class, 'plant_id');
    }

    public function material()
    {
        return $this->belongsTo(Product::class, 'material_id');
    }

    public function testType()
    {
        return $this->belongsTo(QcTestType::class, 'test_type_id');
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
