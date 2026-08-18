<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use App\Traits\PlantScoping;

class PumpRate extends Model
{
    use HasFactory, SoftDeletes, PlantScoping;

    protected $table = 'mm_pump_rates';

    protected $fillable = [
        'plant_id',
        'customer_id',
        'concrete_pump',
        'rate',
        'rate_type',
        'uom_id',
        'name',
        'site_id',
        'status',
        'created_by',
        'updated_by',
        'deleted_by',
    ];

    protected $casts = [
        'rate' => 'decimal:2',
        'status' => 'boolean',
    ];

    // Relations

    public function plant()
    {
        return $this->belongsTo(Plant::class, 'plant_id');
    }

    public function customer()
    {
        return $this->belongsTo(Patron::class, 'customer_id');
    }

    public function pump()
    {
        return $this->belongsTo(Machine::class, 'concrete_pump');
    }

    public function site()
    {
        return $this->belongsTo(Site::class, 'site_id');
    }

    public function uom()
    {
        return $this->belongsTo(ProductUnit::class, 'uom_id');
    }
}
