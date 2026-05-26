<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class GeofenceLog extends Model
{
    use HasFactory;

    protected $table = 'mm_geofence_logs';

    public $timestamps = false;

    protected $fillable = [
        'machine_id',
        'geofence_id',
        'event_type',
        'latitude',
        'longitude',
        'recorded_at',
    ];

    protected $casts = [
        'latitude' => 'decimal:8',
        'longitude' => 'decimal:8',
        'recorded_at' => 'datetime',
    ];

    public function machine()
    {
        return $this->belongsTo(Machine::class, 'machine_id');
    }

    public function geofence()
    {
        return $this->belongsTo(Geofence::class, 'geofence_id');
    }
}
