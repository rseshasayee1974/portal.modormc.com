<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Traits\AuditFields;
use App\Traits\PlantScoping;
use App\Traits\TracksModelChanges;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use InvalidArgumentException;

class Quantity extends Model
{
    use HasFactory, SoftDeletes, AuditFields, PlantScoping , TracksModelChanges;

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
        'is_warehouse'     => 'boolean',
        'quantity'         => 'decimal:2',
        'opening_quantity' => 'decimal:2',
        'date'             => 'date',
    ];

        protected static function boot()
        {
            parent::boot();

            /**
             * Guard Data Integrity Before Committing to Storage Engine
             */
            static::saving(function ($model) {
                if ((float) $model->quantity < 0) {
                    throw new InvalidArgumentException('Quantity cannot be negative. Negative values are not allowed.');
                }
                if ( $model->quantity != 0) {
                    throw new InvalidArgumentException('Quantity cannot be negative. Negative values are not allowed.');
                }
                if ((float) $model->opening_quantity < 0) {
                    throw new InvalidArgumentException('Opening quantity cannot be negative. Negative values are not allowed.');
                }
            });

        /**
         * Capture Newly Seeded Stock Records
         */
        static::created(function ($model) {
            try {
                $model->writeAuditLog(
                    'stockin', 
                    'Create', 
                    0, 
                    $model->quantity, 
                    "Initial stock baseline configuration recorded."
                );
            } catch (\Throwable $e) {
                Log::error('Failed to log stock baseline creation: ' . $e->getMessage());
            }
        });

        /**
         * Capture Fluctuating Shifts across Quantities safely
         */
        static::updated(function ($model) {
            // Optimized: Using isDirty ensures we bypass tracking loops if unrelated flags get shifted
            if ($model->isDirty('quantity')) {
                try {
                    $oldQty = (float) ($model->getOriginal('quantity') ?? 0);
                    $newQty = (float) $model->quantity;

                    $type = $newQty > $oldQty ? 'stockin' : 'stockout';
                    
                    // Optimized performance pattern to circumvent N+1 logic inside hooks
                    $productName = $model->relationLoaded('product') && $model->product 
                        ? $model->product->title 
                        : "Product #{$model->product_id}";
                    
                    $model->writeAuditLog(
                        $type, 
                        'Update', 
                        $oldQty, 
                        $newQty, 
                        "Stock level for '{$productName}' updated from {$oldQty} to {$newQty}"
                    );
                } catch (\Throwable $e) {
                    Log::error('Failed to trace automated stock operational variations: ' . $e->getMessage());
                }
            }
        });

        /**
         * Capture Model System Truncations / Evictions
         */
        static::deleted(function ($model) {
            try {
                $productName = $model->relationLoaded('product') && $model->product 
                    ? $model->product->title 
                    : "Product #{$model->product_id}";

                $model->writeAuditLog(
                    'stockout', 
                    'Delete', 
                    (float)$model->quantity, 
                    0, 
                    "Stock tracking node permanently pruned for '{$productName}'"
                );
            } catch (\Throwable $e) {
                Log::error('Failed to audit log raw material record soft deletion: ' . $e->getMessage());
            }
        });
    }

    /**
     * Isolated Helper to maintain consistency across transaction audit entries
     */
    protected function writeAuditLog(string $transactionType, string $refType, float $from, float $to, string $remarks): void
    {
        InventoryAuditLog::create([
            'plant_id'         => $this->plant_id,
            'transaction_type' => $transactionType,
            'reference_type'   => $refType,
            'reference_id'     => $this->product_id,
            'log_from'         => $from,
            'log_to'           => $to,
            'user_id'          => Auth::id(),
            'remarks'          => $remarks,
            'ip_address'       => request()->ip(),
        ]);
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