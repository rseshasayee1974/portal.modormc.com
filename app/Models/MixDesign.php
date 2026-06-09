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

    protected $appends = [
        'is_in_use'
    ];
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
    

    public function getIsInUseAttribute()
    {
        return $this->quotationItems()->exists() ;
    }
}
