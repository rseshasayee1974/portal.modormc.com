<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PrintTemplate extends Model
{
    protected $table = 'mm_print_templates';
    
    protected $fillable = [
        'name',
        'key',
        'category',
        'thumbnail',
        'is_system',
        'config'
    ];

    protected $casts = [
        'is_system' => 'boolean',
        'config' => 'array'
    ];
}
