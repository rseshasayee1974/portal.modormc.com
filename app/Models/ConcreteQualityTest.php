<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Traits\AuditFields;
use App\Traits\PlantScoping;

class ConcreteQualityTest extends Model
{
    use HasFactory, SoftDeletes, AuditFields, PlantScoping;

    protected $table = 'mm_concrete_quality_tests';

    protected $fillable = [
        'plant_id',
        'batch_id',
        'test_code',
        'test_date',
        'tested_by',
        
        // Fresh Concrete Testing
        'slump_value',
        'fresh_temperature',
        'air_content',
        'fresh_density',
        
        // Hardened Concrete Testing
        'cube_strength_7_days',
        'cube_strength_28_days',
        'core_test_strength',
        'water_permeability',
        'rapid_chloride_permeability',
        
        // Status & Remarks
        'status',
        'remarks',
        'photo_path',
        
        'created_by',
        'updated_by',
        'deleted_by',
    ];

    protected $appends = ['photo_url'];

    public function getPhotoUrlAttribute()
    {
        return $this->photo_path ? \Illuminate\Support\Facades\Storage::url($this->photo_path) : null;
    }

    protected $casts = [
        'test_date' => 'datetime',
        'slump_value' => 'decimal:2',
        'fresh_temperature' => 'decimal:2',
        'air_content' => 'decimal:2',
        'fresh_density' => 'decimal:2',
        'cube_strength_7_days' => 'decimal:2',
        'cube_strength_28_days' => 'decimal:2',
        'core_test_strength' => 'decimal:2',
        'water_permeability' => 'decimal:2',
        'rapid_chloride_permeability' => 'decimal:2',
    ];

    /**
     * Relationship: Scoped to Plant facility.
     */
    public function plant()
    {
        return $this->belongsTo(Plant::class, 'plant_id');
    }

    /**
     * Relationship: Linked to Batch.
     */
    public function batch()
    {
        return $this->belongsTo(Batch::class, 'batch_id');
    }

    /**
     * Relationship: Reference photos stored in mm_images table.
     */
    public function photos()
    {
        return $this->hasMany(Image::class, 'ref_no', 'id')->where('category', 'QCTest');
    }
}
