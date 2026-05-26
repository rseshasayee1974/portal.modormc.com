<?php

namespace App\Models;

use App\Traits\PlantScoping;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Support\Facades\Auth;

class MachineTracker extends Model
{
    use HasFactory, PlantScoping;

    protected $table = 'mm_machine_tracker';

    public const CREATED_AT = 'created';
    public const UPDATED_AT = 'modified';

    protected $fillable = [
        'plant_id',
        'machine_id',
        'operation_type',
        'category',
        'operator_id',
        'opening',
        'closing',
        'odometer_start',
        'odometer_end',
        'hourmeter_start',
        'hourmeter_end',
        'eb_start',
        'eb_close',
        'opening_hsd',
        'closing_hsd',
        'notes',
        'fuel',
        'fuel_filled_on',
        'last_fuel_filled_km',
        'fuel_filled_km',
        'pump_name',
        'pump_reading',
        'amount',
        'shift',
        'created_by',
        'modified_by',
        'company_id'
    ];

    protected $casts = [
        'opening' => 'datetime',
        'closing' => 'datetime',
        'fuel_filled_on' => 'datetime',
        'created' => 'datetime',
        'modified' => 'datetime',
        'odometer_start' => 'decimal:2',
        'odometer_end' => 'decimal:2',
        'hourmeter_start' => 'decimal:2',
        'hourmeter_end' => 'decimal:2',
        'eb_start' => 'decimal:2',
        'eb_close' => 'decimal:2',
        'opening_hsd' => 'decimal:2',
        'closing_hsd' => 'decimal:2',
        'fuel' => 'decimal:2',
        'last_fuel_filled_km' => 'decimal:2',
        'fuel_filled_km' => 'decimal:2',
        'amount' => 'decimal:3',
    ];

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($model) {
            if (Auth::check()) {
                $model->created_by = Auth::id();
                $model->modified_by = Auth::id();
            }
            if (!$model->company_id && session('active_entity_id')) {
                $model->company_id = session('active_entity_id');
            }
        });

        static::updating(function ($model) {
            if (Auth::check()) {
                $model->modified_by = Auth::id();
            }
        });
    }

    public function machine()
    {
        return $this->belongsTo(Machine::class, 'machine_id');
    }

    public function plant()
    {
        return $this->belongsTo(Plant::class, 'plant_id');
    }

    public function operator()
    {
        return $this->belongsTo(User::class, 'operator_id');
    }

    public function company()
    {
        return $this->belongsTo(Entity::class, 'company_id');
    }
}
