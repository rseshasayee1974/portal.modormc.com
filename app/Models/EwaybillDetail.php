<?php

namespace App\Models;
use App\Traits\TracksModelChanges;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class EwaybillDetail extends Model
{
    use HasFactory, TracksModelChanges;

    protected $table = 'mm_ewaybill_details';

    public $timestamps = false;

    protected $fillable = [
        'plant_id',
        'generation_type',
        'origin_id',
        'ewaybill_no',
        'ewaybill_date',
        'valid_upto',
        'ewaybill_status',
        'ewaybill_cancel_at',
        'ewaybill_cancel_by',
        'ewaybill_reject_at',
        'ewaybill_reject_by',
        'created_at',
        'created_by',
        'modified_at',
        'modified_by',
        'status',
    ];

    protected $casts = [
        'ewaybill_cancel_at' => 'datetime',
        'ewaybill_reject_at' => 'datetime',
        'created_at' => 'datetime',
        'modified_at' => 'datetime',
    ];

    public function plant()
    {
        return $this->belongsTo(Plant::class, 'plant_id');
    }

    public function invoice()
    {
        return $this->belongsTo(Invoice::class, 'origin_id');
    }
}
