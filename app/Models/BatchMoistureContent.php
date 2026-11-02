<?php

/*
Author: Cipher
Created: 2026-11-02 11:26:14 Asia/Calcutta (UTC+05:30)
*/

namespace App\Models;

use App\Traits\PlantScoping;
use App\Traits\TracksModelChanges;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class BatchMoistureContent extends Model
{
    use SoftDeletes, PlantScoping, TracksModelChanges;

    protected $table = 'mm_batch_moisture_contents';

    protected $fillable = [
        'plant_id', 'batch_id', 'product_id', 'material_name',
        'design_moisture_percentage', 'consumption_moisture_percentage', 'measured_at', 'measured_date_time', 'notes',
    ];

    protected $casts = [
        'design_moisture_percentage' => 'decimal:3',
        'consumption_moisture_percentage' => 'decimal:3',
        'measured_at' => 'datetime:Y-m-d H:i:s',
        'measured_date_time' => 'datetime:Y-m-d H:i:s',
    ];

    public function batch()
    {
        return $this->belongsTo(Batch::class);
    }

    public function product()
    {
        return $this->belongsTo(Product::class);
    }
}
