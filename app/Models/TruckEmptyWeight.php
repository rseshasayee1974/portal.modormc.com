<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use App\Traits\PlantScoping;

class TruckEmptyWeight extends Model
{
    use HasFactory, PlantScoping;

    protected $table = 'mm_truck_empty_weights';

    protected $fillable = [
        'truck_id',
        'empty_weight',
        'plant_id',
    ];

    protected $casts = [
        'empty_weight' => 'float',
    ];

    public function truck()
    {
        return $this->belongsTo(Machine::class, 'truck_id');
    }

    public function plant()
    {
        return $this->belongsTo(Plant::class, 'plant_id');
    }
}
