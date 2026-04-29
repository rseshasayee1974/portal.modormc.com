<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\DB;

class ProductCategory extends Model
{
    /** @use HasFactory<\Database\Factories\ProductCategoryFactory> */
    use HasFactory, SoftDeletes;

    protected $table = 'mm_product_categories';

    // ──────────────────────────────────────────────────────────────
    // Auto Code Generation
    // ──────────────────────────────────────────────────────────────

    protected static function boot()
    {
        parent::boot();

        static::creating(function (ProductCategory $category) {
            if (empty($category->code)) {
                $category->code = static::generateCode($category->plant_id);
            }
        });
    }

    /**
     * Generate the next sequential category code for the given plant.
     * The code is a simple integer: 1, 2, 3, …
     * We look at all rows (including soft-deleted) so numbers are never reused.
     */
    public static function generateCode(int $plantId): string
    {
        $max = DB::table('mm_product_categories')
            ->where('plant_id', $plantId)
            ->whereNotNull('code')
            ->whereRaw("code REGEXP '^[0-9]+$'")
            ->max(DB::raw('CAST(code AS UNSIGNED)'));

        return (string) (($max ?? 0) + 1);
    }

    protected $fillable = [
        'plant_id',
        'parent_id',
        'name',
        'code',       // auto-generated on create — do NOT send from frontend
        'description',
        'sort_order',
        'status',
    ];

    protected $casts = [
        'status' => 'boolean',
    ];

    public function plant()
    {
        return $this->belongsTo(Plant::class, 'plant_id');
    }

    public function parent()
    {
        return $this->belongsTo(ProductCategory::class, 'parent_id');
    }

    public function children()
    {
        return $this->hasMany(ProductCategory::class, 'parent_id');
    }

    public function products()
    {
        return $this->hasMany(Product::class, 'category_id');
    }
}
