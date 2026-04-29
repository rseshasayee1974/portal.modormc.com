<?php

namespace App\Models;

use App\Traits\AuditFields;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class MixDesignMaterial extends Model
{
    use HasFactory, SoftDeletes, AuditFields;

    protected $table = 'mm_mix_design_materials';

    protected $fillable = [
        'mix_design_id',
        'material_name',
        'qty_per_m3',
        'uom_id',
        'created_by',
        'updated_by',
        'deleted_by',
    ];

    protected $casts = [
        'qty_per_m3' => 'decimal:3',
    ];

    public function mixDesign()
    {
        return $this->belongsTo(MixDesign::class, 'mix_design_id');
    }

    public function uom()
    {
        return $this->belongsTo(ProductUnit::class, 'uom_id');
    }
}
