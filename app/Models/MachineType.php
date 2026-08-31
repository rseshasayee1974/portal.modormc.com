<?php

namespace App\Models;
use App\Traits\TracksModelChanges;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Traits\ProtectsSystemItems;

class MachineType extends Model
{
    use HasFactory, SoftDeletes, ProtectsSystemItems, TracksModelChanges;
    protected $table = 'mm_machine_types';
    protected $fillable = [
        'plant_id',
        'name',
        'is_system',
    ];

    protected $casts = [
        'is_system' => 'boolean',
    ];

    public function plant()
    {
        return $this->belongsTo(Plant::class);
    }
}
