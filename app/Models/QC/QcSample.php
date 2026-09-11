<?php

namespace App\Models\QC;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Models\Plant;
use App\Models\Product;
use App\Models\Patron;
use App\Models\PurchaseOrderHistory;
use App\Models\Batch;
use App\Models\Dispatch;
use App\Models\User;

class QcSample extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'mm_qc_samples';

    protected $fillable = [
        'plant_id',
        'sample_no',
        'sample_date',
        'material_id',
        'concrete_grade_id',
        'supplier_id',
        'customer_id',
        'inward_id',
        'batch_id',
        'dispatch_id',
        'truck_no',
        'site_name',
        'source_location',
        'slump_mm',
        'concrete_temp_c',
        'ambient_temp_c',
        'specimen_size',
        'specimen_count',
        'curing_tank_id',
        'sample_quantity',
        'sampled_by',
        'tested_by',
        'status',
        'remarks',
        'created_by',
        'updated_by',
        'deleted_by',
    ];

    protected $casts = [
        'sample_date' => 'datetime',
        'slump_mm' => 'float',
        'concrete_temp_c' => 'float',
        'ambient_temp_c' => 'float',
        'specimen_count' => 'integer',
    ];

    public function plant()
    {
        return $this->belongsTo(Plant::class, 'plant_id');
    }

    public function concreteGrade()
    {
        return $this->belongsTo(\App\Models\ConcreteGrade::class, 'concrete_grade_id');
    }

    public function material()
    {
        return $this->belongsTo(Product::class, 'material_id');
    }

    public function supplier()
    {
        return $this->belongsTo(Patron::class, 'supplier_id');
    }

    public function customer()
    {
        return $this->belongsTo(Patron::class, 'customer_id');
    }

    public function inward()
    {
        return $this->belongsTo(PurchaseOrderHistory::class, 'inward_id');
    }

    public function batch()
    {
        return $this->belongsTo(Batch::class, 'batch_id');
    }

    public function dispatch()
    {
        return $this->belongsTo(Dispatch::class, 'dispatch_id');
    }

    public function sampler()
    {
        return $this->belongsTo(User::class, 'sampled_by');
    }

    public function tester()
    {
        return $this->belongsTo(\App\Models\Personnel::class, 'tested_by');
    }

    public function tests()
    {
        return $this->hasMany(QcTest::class, 'sample_id');
    }
}
