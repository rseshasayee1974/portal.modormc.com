<?php

namespace App\Models;
use App\Traits\TracksModelChanges;

use Illuminate\Database\Eloquent\SoftDeletes;

use Illuminate\Database\Eloquent\Model;

class PrintTemplateSetting extends Model
{
    use SoftDeletes, TracksModelChanges;
    protected $table = 'mm_print_template_settings';
    
    protected $fillable = [
        'module_key',
        'print_template_id',
        'plant_id',
        'entity_id',
        'deleted_by',
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
