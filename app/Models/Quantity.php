<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Traits\AuditFields;
use App\Traits\PlantScoping;

class Quantity extends Model
{
    use HasFactory, SoftDeletes, AuditFields, PlantScoping;

    protected $table = 'mm_quantity';

    protected $fillable = [
        'plant_id',  
        'uom_id', 
        'product_id',
        'opening_quantity', 
        'quantity', 
        'date',
        'is_warehouse', 
        'status',
        'created_by', 
        'updated_by', 
        'deleted_by',
    ];

    protected $casts = [
        'is_warehouse' => 'boolean',
        'quantity'     => 'decimal:2',
        'opening_quantity' => 'decimal:2',
        'date'         => 'date',
    ];

    protected static function boot()
    {
        parent::boot();

        static::saving(function ($model) {
            if ($model->quantity < 0) {
                throw new \InvalidArgumentException('Quantity cannot be negative. Negative values are not allowed.');
            }
            if ($model->opening_quantity < 0) {
                throw new \InvalidArgumentException('Opening quantity cannot be negative. Negative values are not allowed.');
            }
        });

               static::updated(function ($model) {
            try {
                $oldQty = $model->getOriginal('quantity') ?? 0;
                $newQty = $model->quantity;

                if ($oldQty != $newQty) {
                    $type = $newQty > $oldQty ? 'stockin' : 'stockout';
                    $productName = $model->product ? $model->product->title : "Product #{$model->product_id}";
                    
                    \App\Models\InventoryAuditLog::create([
                        'plant_id' => $model->plant_id,
                        'transaction_type' => $type,
                        'reference_type' => 'Update',
                        'reference_id' => $model->product_id,
                        'log_from' => $oldQty,
                        'log_to' => $newQty,
                        'user_id' => \Illuminate\Support\Facades\Auth::id(),
                        'remarks' => "Stock level for '{$productName}' updated from {$oldQty} to {$newQty}",
                        'ip_address' => request()->ip(),
                    ]);
                }
            } catch (\Exception $e) {
                \Illuminate\Support\Facades\Log::error('Failed to log stock update: ' . $e->getMessage());
            }
        });

        static::deleted(function ($model) {
            try {
                $productName = $model->product ? $model->product->title : "Product #{$model->product_id}";
                \App\Models\InventoryAuditLog::create([
                    'plant_id' => $model->plant_id,
                    'transaction_type' => 'stockout',
                    'reference_type' => 'Delete',
                    'reference_id' => $model->product_id,
                    'log_from' => $model->quantity,
                    'log_to' => 0,
                    'user_id' => \Illuminate\Support\Facades\Auth::id(),
                    'remarks' => "Stock record deleted for '{$productName}'",
                    'ip_address' => request()->ip(),
                ]);
            } catch (\Exception $e) {
                \Illuminate\Support\Facades\Log::error('Failed to log stock deletion: ' . $e->getMessage());
            }
        });
    }

    public function plant()
    {
        return $this->belongsTo(Plant::class, 'plant_id');
    }

    public function product()
    {
        return $this->belongsTo(Product::class, 'product_id');
    }

    public function uom()
    {
        return $this->belongsTo(ProductUnit::class, 'uom_id');
    }
}
