<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\DB;
use App\Traits\ProtectsSystemItems;
use App\Traits\PlantScoping;
use App\Traits\TracksModelChanges;

class Product extends Model
{
    /** @    use HasFactory<\Database\Factories\ProductFactory> */
    use HasFactory, SoftDeletes, ProtectsSystemItems, PlantScoping, TracksModelChanges;

    protected $table = 'mm_products';

    public string $auditTransactionType = 'product';

    // ──────────────────────────────────────────────────────────────
    // Auto Code Generation
    // ──────────────────────────────────────────────────────────────

    protected static function boot()
    {
        parent::boot();

        static::creating(function (Product $product) {
            if (empty($product->code)) {
                $product->code = static::generateCode($product->plant_id, $product->category_id);
            }
        });

        static::updating(function (Product $product) {
            $user = auth()->user();
            $isSystemAdmin = $user && $user->isSystemAdmin();

            if ($product->isRestrictedFromModification() && !$isSystemAdmin) {
                $dirtyFields = array_keys($product->getDirty());
                $exemptedFields = ['category_id', 'hsn_code', 'material_code', 'updated_at', 'updated_by'];
                
                $restrictedChanges = [];
                foreach ($dirtyFields as $field) {
                    if (!in_array($field, $exemptedFields)) {
                        $original = $product->getOriginal($field);
                        $current = $product->getAttribute($field);

                        // Normalize booleans
                        if (in_array($field, ['status', 'tax_mode', 'is_service']) || is_bool($original) || is_bool($current)) {
                            if (filter_var($original, FILTER_VALIDATE_BOOLEAN) === filter_var($current, FILTER_VALIDATE_BOOLEAN)) {
                                continue;
                            }
                        }
                        // Normalize numbers
                        if (is_numeric($original) && is_numeric($current)) {
                            if ((float)$original === (float)$current) {
                                continue;
                            }
                        }
                        // Normalize strings
                        if (is_string($original) && is_string($current)) {
                            if (strtolower(trim($original)) === strtolower(trim($current))) {
                                continue;
                            }
                        }
                        // Normalize empty defaults in sqlite
                        if (($original === null || $original === 0 || $original === false || $original === '0.00' || $original === 0.0) && 
                            ($current === null || $current === 0 || $current === false || $current === 0.0)) {
                            continue;
                        }

                        $restrictedChanges[] = $field;
                    }
                }

                if (!empty($restrictedChanges)) {
                    throw \Illuminate\Validation\ValidationException::withMessages([
                        'product' => ['This product cannot be updated because it is used in an active mix design associated with a batch.'],
                    ]);
                }
            }
        });

        static::deleting(function (Product $product) {
            $user = auth()->user();
            $isSystemAdmin = $user && $user->isSystemAdmin();

            if ($product->isRestrictedFromModification() && !$isSystemAdmin) {
                throw \Illuminate\Validation\ValidationException::withMessages([
                    'product' => ['This product cannot be deleted because it is used in an active mix design associated with a batch.'],
                ]);
            }
        });
    }

    /**
     * Generate the next layout-based product code: [CategoryCode][001...]
     */
    public static function generateCode(int $plantId, ?int $categoryId): string
    {
        $categoryCode = '';
        if ($categoryId) {
            $category = ProductCategory::find($categoryId);
            if ($category && $category->code) {
                $categoryCode = $category->code;
            }
        }

        $codes = DB::table('mm_products')
            ->where('plant_id', $plantId)
            ->where('category_id', $categoryId)
            ->whereNotNull('code')
            ->pluck('code');

        $max = 0;
        foreach ($codes as $code) {
            $seq = 0;
            if ($categoryCode !== '' && str_starts_with($code, $categoryCode)) {
                $seq = (int) substr($code, strlen($categoryCode));
            } else {
                $seq = (int) preg_replace('/[^0-9]/', '', $code);
            }
            if ($seq > $max) {
                $max = $seq;
            }
        }

        $nextSeq = $max + 1;
        return $categoryCode . str_pad((string)$nextSeq, 3, '0', STR_PAD_LEFT);
    }

    // ──────────────────────────────────────────────────────────────
    // Dropdown / Lookup Scopes
    // ──────────────────────────────────────────────────────────────

    /**
     * Scope: active products for a plant (entity_id + plant_id + not deleted).
     * SoftDeletes handles deleted_at IS NULL automatically.
     *
     * @param  \Illuminate\Database\Eloquent\Builder $query
     * @param  int|array  $plantId
     * @param  int|null   $entityId
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeForPlant($query, $plantId, ?int $entityId = null)
    {
        $query = is_array($plantId)
            ? $query->where('plant_id', $plantId)
            : $query->where('plant_id', $plantId);

        if ($entityId !== null) {
            $query->where('entity_id', $entityId);
        }

        return $query;
    }

    /**
     * Scope: Eager load regular relationships like category and unit.
     *
     * @param  \Illuminate\Database\Eloquent\Builder $query
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeWithDetails($query)
    {
        return $query->with(['category', 'unit']);
    }

    /**
     * Scope: filter by one or more product category IDs.
     *
     * @param  \Illuminate\Database\Eloquent\Builder $query
     * @param  int|array  $categoryId
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeOfCategory($query, $categoryId)
    {
        return is_array($categoryId)
            ? $query->where('category_id', $categoryId)
            : $query->where('category_id', $categoryId);
    }

    /**
     * Scope: exclude a specific product by id (edit/update scenarios).
     *
     * @param  \Illuminate\Database\Eloquent\Builder $query
     * @param  int  $id
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeExcludeId($query, int $id)
    {
        return $query->where('mm_products.id', '!=', $id);
    }

    protected $fillable = [
        'plant_id',
        'category_id',
        'unit_id',
        'is_service',
        'purchase_tax_id',
        'sale_tax_id',
        'purchase_price',
        'sales_price',
        'title',
        'material_code',
        'product_type',
        'conversion_quantity',
        'code',
        'alias',
        'description',
        'hsn_code',
        'tax_mode',
        'is_returnable',
        'stock_alert',
        'is_system',
        'status',
        'created_by',
        'updated_by',
        'deleted_by'
    ];

    protected $casts = [
        'is_service' => 'boolean',
        'is_returnable' => 'boolean',
        'is_system' => 'boolean',
        'status' => 'boolean',
        'tax_mode' => 'boolean',
        'purchase_price' => 'decimal:2',
        'sales_price' => 'decimal:2',
        'stock_alert' => 'decimal:2',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime',
    ];

    public function plant()
    {
        return $this->belongsTo(Plant::class, 'plant_id');
    }

    public function category()
    {
        return $this->belongsTo(ProductCategory::class, 'category_id');
    }

    public function unit()
    {
        return $this->belongsTo(ProductUnit::class, 'unit_id');
    }

    public function purchaseTax()
    {
        return $this->belongsTo(Tax::class, 'purchase_tax_id');
    }

    public function saleTax()
    {
        return $this->belongsTo(Tax::class, 'sale_tax_id');
    }

    public function creator()
    {
        return $this->belongsTo(\App\Models\User::class, 'created_by');
    }


    public function getConversionQuantityAttribute()
    {
        if (array_key_exists('convertsion_quantity', $this->attributes)) {
            return $this->attributes['convertsion_quantity'];
        }
        if (array_key_exists('conversion_quantity', $this->attributes)) {
            return $this->attributes['conversion_quantity'];
        }
        return 0;
    }

    public function setConversionQuantityAttribute($value)
    {
        if (array_key_exists('convertsion_quantity', $this->attributes)) {
            $this->attributes['convertsion_quantity'] = $value;
        } elseif (array_key_exists('conversion_quantity', $this->attributes)) {
            $this->attributes['conversion_quantity'] = $value;
        } else {
            static $hasConvertsionCol = null;
            if ($hasConvertsionCol === null) {
                try {
                    $hasConvertsionCol = \Illuminate\Support\Facades\Schema::hasColumn($this->getTable(), 'convertsion_quantity');
                } catch (\Exception $e) {
                    $hasConvertsionCol = false;
                }
            }
            if ($hasConvertsionCol) {
                $this->attributes['convertsion_quantity'] = $value;
            } else {
                $this->attributes['conversion_quantity'] = $value;
            }
        }
    }

    public function getIsInUseAttribute(): bool
    {
        return $this->isRestrictedFromModification();
    }

    public function isRestrictedFromModification(): bool
    {
        return DB::table('mm_mix_design_items')
            ->join('mm_mix_designs', 'mm_mix_design_items.mix_design_id', '=', 'mm_mix_designs.id')
            ->join('mm_sales_orders', 'mm_mix_designs.id', '=', 'mm_sales_orders.mix_design_id')
            ->join('mm_batches', 'mm_sales_orders.id', '=', 'mm_batches.sales_order_id')
            ->where('mm_mix_design_items.product_id', $this->id)
            ->whereNull('mm_mix_designs.deleted_at')
            ->whereNull('mm_mix_design_items.deleted_at')
            ->whereNull('mm_sales_orders.deleted_at')
            ->whereNull('mm_batches.deleted_at')
            ->exists();
    }
}
