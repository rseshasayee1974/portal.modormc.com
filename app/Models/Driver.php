<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Traits\AuditFields;
use App\Traits\PlantScoping;

class Driver extends Model
{
    use HasFactory, SoftDeletes, AuditFields, PlantScoping;

    protected $table = 'mm_drivers';

    protected $fillable = [
        'entity_id',
        'plant_id',
        'personnel_id',
        'license_number',
        'license_expiry_date',
        'license_type',
        'status',
        'created_by',
        'updated_by',
        'deleted_by',
    ];

    protected $casts = [
        'license_expiry_date' => 'date',
    ];

    /**
     * Scope: active drivers for a plant.
     */
    public function scopeForPlant($query, $plantId, ?int $entityId = null)
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
     * Relations
     */
    public function personnel()
    {
        return $this->belongsTo(Personnel::class, 'personnel_id');
    }

    public function plant()
    {
        return $this->belongsTo(Plant::class, 'plant_id');
    }

    public function entity()
    {
        return $this->belongsTo(Entity::class, 'entity_id');
    }
}
