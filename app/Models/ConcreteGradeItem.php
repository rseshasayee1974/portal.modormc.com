<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Traits\TracksModelChanges;

class ConcreteGradeItem extends Model
{
    use HasFactory, SoftDeletes, TracksModelChanges;

    protected $table = 'mm_concrete_grade_items';

    protected $fillable = [
        'plant_id',
        'concrete_grade_id',
        'product_id',
        'quantity',
        'status',
        'created_by',
        'updated_by',
        'deleted_by',
    ];

    protected $appends = [
        'is_in_use',
    ];

    protected $casts = [
        'status'   => 'boolean',
        'quantity' => 'decimal:4',
    ];

    protected static function boot()
    {
        parent::boot();

        static::deleting(function ($item) {
            if ($item->is_in_use) {
                throw new \Exception("Cannot delete concrete grade item '" . ($item->product->title ?? 'Unknown') . "' because it is currently in use by mix designs or batching records.");
            }
        });
    }

    public function grade()
    {
        return $this->belongsTo(ConcreteGrade::class, 'concrete_grade_id');
    }

    public function product()
    {
        return $this->belongsTo(Product::class, 'product_id');
    }

    public function plant()
    {
        return $this->belongsTo(Plant::class, 'plant_id');
    }

    /**
     * All mix design items using the same product and belonging to this grade's mix designs.
     */
    public function mixDesignItems()
    {
        return $this->hasMany(MixDesignItem::class, 'product_id', 'product_id')
            ->whereHas('mixDesign', function ($query) {
                if ($this->exists) {
                    $query->where(function ($q) {
                        $q->where('concrete_grade_id', $this->concrete_grade_id);
                        if ($this->grade) {
                            $q->orWhere('design_type', $this->grade->name);
                        }
                    });
                }
            });
    }

    public function getIsInUseAttribute(): bool
    {
        if (!$this->exists) {
            return false;
        }

        // 1. Check direct reference in Mix Design Items
        if ($this->mixDesignItems()->exists()) {
            return true;
        }

        // // 2. Check indirect references in Batch Materials (production records)
        // $batchMaterialExists = BatchMaterial::where('product_id', $this->product_id)
        //     ->whereHas('batch.workOrder.mixDesign', function ($query) {
        //         $query->where(function ($q) {
        //             $q->where('concrete_grade_id', $this->concrete_grade_id);
        //             if ($this->grade) {
        //                 $q->orWhere('design_type', $this->grade->name);
        //             }
        //         });
        //     })->exists();

        // if ($batchMaterialExists) {
        //     return true;
        // }

        return false;
    }
}