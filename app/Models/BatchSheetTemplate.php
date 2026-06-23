<?php

namespace App\Models;

use App\Traits\AuditFields;
use App\Traits\PlantScoping;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class BatchSheetTemplate extends Model
{
    use HasFactory, SoftDeletes, AuditFields, PlantScoping;

    protected $table = 'mm_batch_sheet_templates';

    protected $fillable = [
        'plant_id',
        'customer_id',
        'name',
        'source_type',
        'field_mapping',
        'layout_fingerprint',
        'keywords',
        'usage_count',
        'is_active',
        'created_by',
        'updated_by',
        'deleted_by',
    ];

    protected $casts = [
        'field_mapping' => 'array',
        'keywords' => 'array',
        'is_active' => 'boolean',
    ];

    public function plant()
    {
        return $this->belongsTo(Plant::class, 'plant_id');
    }

    public function customer()
    {
        return $this->belongsTo(Patron::class, 'customer_id');
    }
}
