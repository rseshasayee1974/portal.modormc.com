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

    protected $table = 'qc_samples';

    protected $fillable = [
        'plant_id',
        'sample_no',
        'sample_date',
        'material_id',
        'supplier_id',
        'customer_id',
        'inward_id',
        'batch_id',
        'dispatch_id',
        'source_location',
        'sample_quantity',
        'sampled_by',
        'status',
        'remarks',
        'created_by',
        'updated_by',
        'deleted_by',
    ];

    protected $casts = [
        'sample_date' => 'datetime',
    ];

    public function plant()
    {
        return $this->belongsTo(Plant::class, 'plant_id');
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

    public function tests()
    {
        return $this->hasMany(QcTest::class, 'sample_id');
    }
}
