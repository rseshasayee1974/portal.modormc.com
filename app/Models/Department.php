<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Traits\AuditFields;
use App\Traits\PlantScoping;

class Department extends Model
{
    use HasFactory, SoftDeletes, AuditFields, PlantScoping;

    protected $table = 'mm_departments';

    protected $fillable = [
        'plant_id',
        'name',
        'code',
    ];

    public function plant()
    {
        return $this->belongsTo(Plant::class, 'plant_id');
    }


    public function personnels()
    {
        return $this->hasMany(Personnel::class, 'department_id');
    }
}
