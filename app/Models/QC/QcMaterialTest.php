<?php

namespace App\Models\QC;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Models\Plant;
use App\Models\Product;
use App\Models\User;

class QcMaterialTest extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'mm_qc_material_tests';

    protected $fillable = [
        'plant_id',
        'material_id',
        'test_type_id',
        'is_required',
        'is_active',
        'created_by',
        'updated_by',
        'deleted_by',
    ];

    protected $casts = [
        'is_required' => 'boolean',
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
