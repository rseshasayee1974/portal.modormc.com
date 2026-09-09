<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class ConcreteQualityTestSpecimen extends Model
{
    use HasFactory;

    protected $table = 'mm_concrete_quality_test_specimens';

    protected $fillable = [
        'concrete_quality_test_id',
        'ident_mark',
        'weight_kg',
        'load_kn',
        'compressive_strength',
        'sort_order',
    ];

    protected $casts = [
        'weight_kg' => 'decimal:2',
        'load_kn' => 'decimal:2',
        'compressive_strength' => 'decimal:2',
        'sort_order' => 'integer',
    ];

    public function test()
    {
        return $this->belongsTo(ConcreteQualityTest::class, 'concrete_quality_test_id');
    }
}
