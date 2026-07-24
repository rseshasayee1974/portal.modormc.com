<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Traits\PlantScoping;

class StatutoryConfig extends Model
{
        use HasFactory, SoftDeletes, PlantScoping;

    protected $table = 'mm_statutory_configs';

    protected $fillable = [
        'plant_id',
        'statute_name',
        'rules',
        'effective_from',
    ];

    protected $casts = [
        'rules' => 'json',
        'effective_from' => 'date',
    ];

    public function plant()
    {
        return $this->belongsTo(Plant::class, 'plant_id');
    }
}
