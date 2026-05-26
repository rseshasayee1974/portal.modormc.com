<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Traits\AuditFields;
use App\Traits\PlantScoping;

class GpsDevice extends Model
{
    use HasFactory, SoftDeletes, AuditFields, PlantScoping;

    protected $table = 'mm_gps_devices';

    protected $fillable = [
        'plant_id',
        'machine_id',
        'imei',
        'device_model',
        'sim_number',
        'phone_number',
        'is_active',
        'last_activity',
        'notes',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'last_activity' => 'datetime',
    ];

    public function machine()
    {
        return $this->belongsTo(Machine::class, 'machine_id');
    }

    public function latestPosition()
    {
        return $this->hasOne(GpsLatestPosition::class, 'device_id');
    }

    public function positions()
    {
        return $this->hasMany(GpsPosition::class, 'device_id');
    }

    public function plant()
    {
        return $this->belongsTo(Plant::class, 'plant_id');
    }
}
