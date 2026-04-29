<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class ConcreteGradeItem extends Model
{
    use HasFactory, SoftDeletes;
    protected $table = 'mm_concrete_grade_items';
    protected $fillable = [
        'plant_id',
        'concrete_grade_id',
        'product_id',
        'quantity',
        'status',
        'created_by',
        'updated_by',
        'deleted_by',
    ];

    protected $casts = [
        'status' => 'boolean',
        'quantity' => 'decimal:4',
    ];

    public function grade()
    {
        return $this->belongsTo(ConcreteGrade::class, 'concrete_grade_id');
    }

    public function product()
    {
        return $this->belongsTo(Product::class, 'product_id');
    }

    public function plant()
    {
        return $this->belongsTo(Plant::class, 'plant_id');
    }
}
