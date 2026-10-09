<?php
/*
Author: ragul-onemodo
Created: 2026-10-09 12:27:48 Asia/Calcutta (UTC+05:30)
*/
namespace App\Models;

use App\Traits\{PlantScoping, TracksModelChanges};
use Illuminate\Database\Eloquent\{Model, SoftDeletes};

class CrmLead extends Model
{
    use SoftDeletes, PlantScoping, TracksModelChanges;
    public const STATUSES = ['New', 'Contacted', 'Qualified', 'Converted', 'Unqualified'];
    public const SOURCES = ['Phone', 'Website', 'Referral', 'Walk-in', 'Campaign', 'Other'];
    public const PRIORITIES = ['Low', 'Medium', 'High'];
    protected $table = 'mm_crm_leads';
    protected $fillable = ['plant_id', 'lead_number', 'enquiry_date', 'contact_name', 'company_name', 'phone', 'email', 'address', 'source', 'status', 'priority', 'assigned_to', 'project_name', 'requirement', 'estimated_quantity', 'delivery_location', 'expected_value', 'expected_purchase_date', 'remarks', 'lost_reason', 'customer_id', 'converted_at'];
    protected $casts = ['enquiry_date' => 'date:Y-m-d', 'expected_purchase_date' => 'date:Y-m-d', 'converted_at' => 'datetime', 'expected_value' => 'decimal:2', 'estimated_quantity' => 'decimal:3'];
    public function owner() { return $this->belongsTo(User::class, 'assigned_to'); }
    public function customer() { return $this->belongsTo(Patron::class, 'customer_id'); }
    public function activities() { return $this->hasMany(CrmActivity::class, 'lead_id'); }
    public function attachments() { return $this->hasMany(CrmAttachment::class, 'lead_id'); }
    public function deal() { return $this->hasOne(CrmDeal::class, 'lead_id'); }
}
