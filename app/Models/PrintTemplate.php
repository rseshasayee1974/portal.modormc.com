<?php

namespace App\Models;
use App\Traits\TracksModelChanges;

use Illuminate\Database\Eloquent\SoftDeletes;

use Illuminate\Database\Eloquent\Model;

class PrintTemplate extends Model
{
    use SoftDeletes, TracksModelChanges;
    protected $table = 'mm_print_templates';
    
    protected $fillable = [
        'name',
        'key',
        'category',
        'thumbnail',
        'is_system',
        'mm_config',
        'deleted_by',
    ];

    protected $casts = [
        'is_system' => 'boolean',
        'mm_config' => 'array',
    ];

    /**
     * Expose mm_config as `config` to the frontend / templates.
     */
    public function getConfigAttribute(): ?array
    {
        return $this->mm_config;
    }
}
