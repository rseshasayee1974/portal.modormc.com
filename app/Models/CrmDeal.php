<?php
/*
Author: ragul-onemodo
Created: 2026-10-09 12:27:48 Asia/Calcutta (UTC+05:30)
*/
namespace App\Models;

use App\Traits\{PlantScoping, TracksModelChanges};
use Illuminate\Database\Eloquent\{Model, SoftDeletes};

class CrmDeal extends Model
{
    use SoftDeletes, PlantScoping, TracksModelChanges;
    public const STAGES = ['Requirement Received', 'Quotation Sent', 'Negotiation', 'Won', 'Lost'];
    protected $table = 'mm_crm_deals';
    protected $fillable = ['plant_id', 'lead_id', 'customer_id', 'name', 'stage', 'expected_value', 'expected_close_date', 'lost_reason', 'quotation_id', 'sales_order_id'];
    protected $casts = ['expected_close_date' => 'date:Y-m-d', 'expected_value' => 'decimal:2'];
}
