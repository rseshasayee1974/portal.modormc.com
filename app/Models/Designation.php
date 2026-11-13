<?php

namespace App\Models;
use App\Traits\TracksModelChanges;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
class Designation extends Model
{
        use HasFactory, SoftDeletes, TracksModelChanges;

    protected $table = 'mm_designations';

    protected $fillable = [
        'plant_id',
        'name',
        'code',
        'min_salary',
        'max_salary',
    ];

    public function personnels()
    {
        return $this->hasMany(Personnel::class, 'designation_id');
    }

    public function plant()
    {
        return $this->belongsTo(Plant::class);
    }

    public function scopeForWorkspace($query, User $user, ?int $plantId)
    {
        $plants = app(\App\Services\PlantContextService::class)->accessiblePlants($user);
        return $query->whereIn('plant_id', $plants->select('mm_plants.id'))
            ->when($plantId, fn ($query) => $query->where('plant_id', $plantId));
    }
}
