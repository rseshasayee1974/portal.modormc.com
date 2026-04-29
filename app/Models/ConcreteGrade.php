<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class ConcreteGrade extends Model
{
    use HasFactory, SoftDeletes;
    protected $table = 'mm_concrete_grades';
    protected $fillable = [
        'plant_id',
        'name',
        'concrete_code',
        'concrete_ratio',
        'cement_ratio',
        'sand_ratio',
        'aggregate_ratio',
        'status',
        'created_by',
        'updated_by',
        'deleted_by',
    ];

    protected $casts = [
        'status' => 'boolean',
        'cement_ratio' => 'decimal:2',
        'sand_ratio' => 'decimal:2',
        'aggregate_ratio' => 'decimal:2',
    ];

    public function plant()
    {
        return $this->belongsTo(Plant::class, 'plant_id');
    }

    public function items()
    {
        return $this->hasMany(ConcreteGradeItem::class);
    }
}
