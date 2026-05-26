<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Traits\AuditFields;
use App\Traits\PlantScoping;

class FuelLog extends Model
{
    use HasFactory, SoftDeletes, AuditFields, PlantScoping;

    protected $table = 'mm_fuel_logs';

    protected $fillable = [
        'entity_id',
        'plant_id',
        'machine_id',
        'driver_id',
        'log_date',
        'quantity',
        'rate_per_liter',
        'total_amount',
        'odometer_reading',
        'hourmeter_reading',
        'pump_name',
        'bill_no',
        'payment_method',
        'attachment_path',
        'notes',
        'created_by',
        'updated_by',
        'deleted_by',
    ];

    protected $casts = [
        'log_date' => 'datetime',
        'quantity' => 'decimal:2',
        'rate_per_liter' => 'decimal:2',
        'total_amount' => 'decimal:2',
        'odometer_reading' => 'decimal:2',
        'hourmeter_reading' => 'decimal:2',
    ];

    /**
     * Scope: active fuel logs for a plant.
     */
    public function scopeForPlant($query, $plantId, int $entityId = null)
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
    public function machine()
    {
        return $this->belongsTo(Machine::class, 'machine_id');
    }

    public function driver()
    {
        return $this->belongsTo(Personnel::class, 'driver_id');
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
