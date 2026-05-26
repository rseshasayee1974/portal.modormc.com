<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Traits\AuditFields;
use App\Traits\PlantScoping;

class Geofence extends Model
{
    use HasFactory, SoftDeletes, AuditFields, PlantScoping;

    protected $table = 'mm_geofences';

    protected $fillable = [
        'plant_id',
        'name',
        'description',
        'shape',
        'coordinates',
        'is_active',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'coordinates' => 'json',
        'is_active' => 'boolean',
    ];

    public function plant()
    {
        return $this->belongsTo(Plant::class, 'plant_id');
    }

    public function logs()
    {
        return $this->hasMany(GeofenceLog::class, 'geofence_id');
    }
}
