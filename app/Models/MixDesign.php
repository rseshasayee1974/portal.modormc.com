<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class MixDesign extends Model
{
    use HasFactory, SoftDeletes;

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
        'is_active',
        'created_by',
        'updated_by',
        'deleted_by',
    ];

    protected $casts = [
        'rate_per_qty' => 'decimal:4',
        'is_active' => 'boolean',
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
        return $this->belongsTo(ConcreteGrade::class, 'design_type', 'name');
    }
}
