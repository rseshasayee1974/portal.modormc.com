<?php

namespace App\Models;

use App\Traits\TracksModelChanges;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Traits\PlantScoping;

class ConcreteQualityTest extends Model
{
    use HasFactory, SoftDeletes, PlantScoping, TracksModelChanges;

    protected $table = 'mm_concrete_quality_tests';

    protected $fillable = [
        'plant_id',
        'batch_id',
        'test_code',
        'test_number',
        'test_date',
        'tested_by',

        // Header / Patron / Invoice Details
        'account_name',
        'patron_id',
        'invoice_id',
        'invoice_no',
        'grade',
        'concrete_date',
        'age_of_test_days',
        'date_of_testing',
        'project',
        'billing_address',
        'shipping_address',
        'description',

        // Dimensions & Weights
        'dimension_length',
        'dimension_width',
        'dimension_height',
        'fresh_unit_weight',

        // Fresh Concrete Testing
        'slump_value',
        'fresh_temperature',
        'air_content',
        'fresh_density',

        // Technicians
        'lab_technician',
        'field_technician',

        // Laboratory Summary
        'ident_mark',
        'avg_compressive_strength',

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

    protected $appends = ['photo_url', 'dimension_string'];

    public function getPhotoUrlAttribute()
    {
        return $this->photo_path ? \Illuminate\Support\Facades\Storage::url($this->photo_path) : null;
    }

    public function getDimensionStringAttribute()
    {
        $l = (float)($this->dimension_length ?? 15);
        $w = (float)($this->dimension_width ?? 15);
        $h = (float)($this->dimension_height ?? 15);
        return "{$l} X {$w} X {$h}";
    }

    protected $casts = [
        'test_date' => 'datetime',
        'concrete_date' => 'date',
        'date_of_testing' => 'date',
        'age_of_test_days' => 'integer',
        'dimension_length' => 'decimal:2',
        'dimension_width' => 'decimal:2',
        'dimension_height' => 'decimal:2',
        'fresh_unit_weight' => 'decimal:2',
        'slump_value' => 'decimal:2',
        'fresh_temperature' => 'decimal:2',
        'air_content' => 'decimal:2',
        'fresh_density' => 'decimal:2',
        'cube_strength_7_days' => 'decimal:2',
        'cube_strength_28_days' => 'decimal:2',
        'core_test_strength' => 'decimal:2',
        'water_permeability' => 'decimal:2',
        'rapid_chloride_permeability' => 'decimal:2',
        'avg_compressive_strength' => 'decimal:2',
    ];

    /**
     * Relationship: Scoped to Plant facility.
     */
    public function plant()
    {
        return $this->belongsTo(Plant::class, 'plant_id');
    }

    /**
     * Relationship: Linked to Patron/Customer.
     */
    public function patron()
    {
        return $this->belongsTo(Patron::class, 'patron_id');
    }

    /**
     * Relationship: Linked to Invoice.
     */
    public function invoice()
    {
        return $this->belongsTo(Invoice::class, 'invoice_id');
    }

    /**
     * Relationship: Linked to Batch.
     */
    public function batch()
    {
        return $this->belongsTo(Batch::class, 'batch_id');
    }

    /**
     * Relationship: Laboratory Cube Specimens.
     */
    public function specimens()
    {
        return $this->hasMany(ConcreteQualityTestSpecimen::class, 'concrete_quality_test_id')->orderBy('sort_order')->orderBy('id');
    }

    /**
     * Relationship: Reference photos stored in mm_images table.
     */
    public function photos()
    {
        return $this->hasMany(Image::class, 'ref_no', 'id')->where('category', 'QCTest');
    }
}
