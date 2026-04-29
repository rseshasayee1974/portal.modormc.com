<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PrintTemplateSetting extends Model
{
    protected $table = 'mm_print_template_settings';
    
    protected $fillable = [
        'module_key',
        'print_template_id',
        'plant_id',
        'entity_id'
    ];

    public function template()
    {
        return $this->belongsTo(PrintTemplate::class, 'print_template_id');
    }

    public function plant()
    {
        return $this->belongsTo(Plant::class);
    }
}
