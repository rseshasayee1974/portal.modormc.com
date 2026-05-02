<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\DB;
use App\Traits\AuditFields;

class Product extends Model
{
    /** @use HasFactory<\Database\Factories\ProductFactory> */
    use HasFactory, SoftDeletes, AuditFields;

    protected $table = 'mm_products';

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
    public function scopeForPlant($query, $plantId, int $entityId = null)
    {
        $query = is_array($plantId)
            ? $query->whereIn('plant_id', $plantId)
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
            ? $query->whereIn('category_id', $categoryId)
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
        'status',
        'created_by',
        'updated_by',
        'deleted_by'
    ];

    protected $casts = [
        'is_service' => 'boolean',
        'is_returnable' => 'boolean',
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
}
