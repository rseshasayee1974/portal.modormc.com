<?php

namespace App\Models;
use App\Traits\TracksModelChanges;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class EinvoiceInvoiceRel extends Model
{
    use HasFactory, TracksModelChanges;

    protected $table = 'mm_einvoice_invoice_rel';

    public $timestamps = false;

    protected $fillable = [
        'invoice_id',
        'cr_dr_id',
        'einv_ackno',
        'einv_ack_date',
        'einv_irn',
        'einv_signed_invoice',
        'einv_signed_qrcode',
        'einv_status',
        'einv_cancel_at',
        'plant_id',
        'status',
        'created',
        'created_by',
        'modified',
        'modified_by',
    ];

    protected $casts = [
        'einv_ack_date' => 'datetime',
        'einv_cancel_at' => 'datetime',
        'created' => 'datetime',
        'modified' => 'datetime',
    ];

    public function invoice()
    {
        return $this->belongsTo(Invoice::class, 'invoice_id');
    }

    public function plant()
    {
        return $this->belongsTo(Plant::class, 'plant_id');
    }
}
