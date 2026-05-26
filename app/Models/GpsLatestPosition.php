<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class GpsLatestPosition extends Model
{
    use HasFactory;

    protected $table = 'mm_gps_latest_positions';

    protected $fillable = [
        'device_id',
        'machine_id',
        'latitude',
        'longitude',
        'speed',
        'heading',
        'altitude',
        'ignition',
        'odometer',
        'recorded_at',
    ];

    protected $casts = [
        'latitude' => 'decimal:8',
        'longitude' => 'decimal:8',
        'speed' => 'decimal:2',
        'heading' => 'decimal:2',
        'altitude' => 'decimal:2',
        'ignition' => 'boolean',
        'odometer' => 'decimal:2',
        'recorded_at' => 'datetime',
    ];

    public function device()
    {
        return $this->belongsTo(GpsDevice::class, 'device_id');
    }

    public function machine()
    {
        return $this->belongsTo(Machine::class, 'machine_id');
    }
}
