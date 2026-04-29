<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;

class MachineType extends Model
{
    use HasFactory, SoftDeletes;
    protected $table = 'mm_machine_types';
    protected $fillable = [
        'plant_id',
        'name',
    ];

    protected $casts = [];

    public function plant()
    {
        return $this->belongsTo(Plant::class);
    }
}
