<?php

namespace App\Models;
use App\Traits\TracksModelChanges;

use App\Traits\PlantScoping;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Support\Facades\Auth;

class MachineService extends Model
{
    use HasFactory, SoftDeletes, PlantScoping, TracksModelChanges;

    protected $table = 'mm_machine_service';

    public const CREATED_AT = 'created';
    public const UPDATED_AT = 'modified';
    public const DELETED_AT = 'deleted';

    protected $fillable = [
        'plant_id',
        'truck_id',
        'service_type',
        'last_service_km',
        'next_service_km',
        'current_running_km',
        'service_hr_km',
        'service_date',
        'notes',
        'status',
        'created_by',
        'modified_by',
        'deleted_by'
    ];

    protected $casts = [
        'service_date' => 'date',
        'created' => 'datetime',
        'modified' => 'datetime',
        'deleted' => 'datetime',
        'last_service_km' => 'decimal:2',
        'next_service_km' => 'decimal:2',
        'current_running_km' => 'decimal:2',
    ];

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($model) {
            if (Auth::check()) {
                $model->created_by = Auth::id();
                $model->modified_by = Auth::id();
            }
        });

        static::updating(function ($model) {
            if (Auth::check()) {
                $model->modified_by = Auth::id();
            }
        });

        static::deleting(function ($model) {
            if (Auth::check() && !$model->isForceDeleting()) {
                $model->deleted_by = Auth::id();
                $model->saveQuietly();
            }
        });
    }

    public function machine()
    {
        return $this->belongsTo(Machine::class, 'truck_id');
    }

    public function plant()
    {
        return $this->belongsTo(Plant::class, 'plant_id');
    }
}
