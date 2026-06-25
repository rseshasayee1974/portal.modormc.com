<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Traits\ProtectsSystemItems;
use App\Traits\PlantScoping;
use App\Traits\TracksModelChanges;

class MixDesign extends Model
{
    use HasFactory, SoftDeletes, ProtectsSystemItems, PlantScoping, TracksModelChanges;

    protected $table = 'mm_mix_designs';

    protected $fillable = [
        'plant_id',
        'partner_id',
        'grade',
        'concrete_grade_id',
        'design_name',
        'design_code',
        'design_type',
        'unit_id',
        'rate_per_qty',
        'is_system',
        'is_active',
        'created_by',
        'updated_by',
        'deleted_by',
    ];

    // protected $appends = [
    //     'is_used_in_quotations',
    //     'is_used_in_batching',
    // ];
    protected $casts = [
        'rate_per_qty' => 'decimal:4',
        'is_active' => 'boolean',
        'is_system' => 'boolean',
    ];

    public function plant()
    {
        return $this->belongsTo(Plant::class, 'plant_id');
    }

    public function partner()
    {
        return $this->belongsTo(Patron::class, 'partner_id');
    }

    public function unit()
    {
        return $this->belongsTo(ProductUnit::class, 'unit_id');
    }

    public function items()
    {
        return $this->hasMany(MixDesignItem::class, 'mix_design_id');
    }

    public function materials()
    {
        return $this->hasMany(MixDesignMaterial::class, 'mix_design_id');
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function concrete_grade()
    {
        return $this->belongsTo(ConcreteGrade::class, 'design_type', 'name','concrete_grade_id');
    }
    public function concrete_grade_items()
    {
        return $this->hasMany(MixDesignItem::class, 'mix_design_id');
    }
    public function quotationItems()
    {
        return $this->hasMany(QuotationItem::class, 'mix_design_id');
    }
    public function batching()
    {
        return $this->hasMany(Batch::class, 'mix_design_id');
    }
    

    // public function getIsUsedInQuotationsAttribute()
    // {
    //     return $this->quotationItems()->exists();
    // }
    // /**
    //  * Determine if this MixDesign is used in any active batching.
    //  */
    // public function getIsUsedInBatchingAttribute()
    // {
    //     // Collect product IDs from associated MixDesign items
    //     $productIds = $this->items()->pluck('product_id');
    //     if ($productIds->isEmpty()) {
    //         return false;
    //     }

    //     // Check if any BatchMaterial references these products in a batch that is not completed
    //     return \App\Models\BatchMaterial::whereIn('product_id', $productIds)
    //         ->whereHas('batch', function ($q) {
    //             $q->whereIn('status', [
    //                 \App\Models\Batch::STATUS_PLANNED,
    //                 \App\Models\Batch::STATUS_LOADING,
    //                 \App\Models\Batch::STATUS_DISPATCHED,
    //                 \App\Models\Batch::STATUS_COMPLETED,
    //             ]);
    //         })
    //         ->exists();
    // }
}
