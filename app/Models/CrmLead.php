<?php

/*
Author: ragul-onemodo
Created: 2026-10-09 12:27:48 Asia/Calcutta (UTC+05:30)
*/

namespace App\Models;

use App\Traits\LogsPushNotifications;
use App\Traits\PlantScoping;
use App\Traits\TracksModelChanges;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class CrmLead extends Model
{
    use LogsPushNotifications, PlantScoping, SoftDeletes, TracksModelChanges;

    public const STATUS_NEW = 'New';

    public const STATUS_CONTACTED = 'Contacted';

    public const STATUS_QUALIFIED = 'Qualified';

    public const STATUS_APPROVED = 'Approved';

    public const STATUS_CONVERTED = 'Converted';

    public const STATUS_UNQUALIFIED = 'Unqualified';

    public const STATUSES = [
        self::STATUS_NEW,
        self::STATUS_CONTACTED,
        self::STATUS_QUALIFIED,
        self::STATUS_APPROVED,
        self::STATUS_CONVERTED,
        self::STATUS_UNQUALIFIED,
    ];

    public const STANDARD_STATUSES = [
        self::STATUS_NEW,
        self::STATUS_CONTACTED,
        self::STATUS_QUALIFIED,
        self::STATUS_UNQUALIFIED,
    ];

    public const QUALIFYING_STATUSES = [
        self::STATUS_QUALIFIED,
        self::STATUS_APPROVED,
    ];

    public const QUOTATION_CONVERTIBLE_STATUSES = [
        self::STATUS_NEW,
        self::STATUS_CONTACTED,
        self::STATUS_QUALIFIED,
        self::STATUS_APPROVED,
    ];

    public const CLOSED_STATUSES = [
        self::STATUS_CONVERTED,
        self::STATUS_UNQUALIFIED,
    ];

    public const SOURCE_PHONE = 'Phone';

    public const SOURCE_WEBSITE = 'Website';

    public const SOURCE_REFERRAL = 'Referral';

    public const SOURCE_WALK_IN = 'Walk-in';

    public const SOURCE_CAMPAIGN = 'Campaign';

    public const SOURCE_OTHER = 'Other';

    public const SOURCES = [
        self::SOURCE_PHONE,
        self::SOURCE_WEBSITE,
        self::SOURCE_REFERRAL,
        self::SOURCE_WALK_IN,
        self::SOURCE_CAMPAIGN,
        self::SOURCE_OTHER,
    ];

    public const PRIORITY_LOW = 'Low';

    public const PRIORITY_MEDIUM = 'Medium';

    public const PRIORITY_HIGH = 'High';

    public const PRIORITIES = [
        self::PRIORITY_LOW,
        self::PRIORITY_MEDIUM,
        self::PRIORITY_HIGH,
    ];

    protected $table = 'mm_crm_leads';

    protected $fillable = ['plant_id', 'lead_number', 'enquiry_date', 'contact_name', 'company_name', 'phone', 'email', 'address', 'source', 'status', 'priority', 'assigned_to', 'project_name', 'mix_design_id', 'requirement', 'estimated_quantity', 'delivery_location', 'expected_value', 'expected_purchase_date', 'remarks', 'lost_reason', 'customer_id', 'converted_at'];

    protected $casts = ['mix_design_id' => 'integer', 'enquiry_date' => 'date:Y-m-d', 'expected_purchase_date' => 'date:Y-m-d', 'converted_at' => 'datetime', 'expected_value' => 'decimal:2', 'estimated_quantity' => 'decimal:3'];

    public function allowedStatuses(): array
    {
        if ($this->converted_at) {
            return [self::STATUS_CONVERTED];
        }

        return array_merge(
            self::STANDARD_STATUSES,
            $this->status === self::STATUS_APPROVED ? [self::STATUS_APPROVED] : []
        );
    }

    public function statusOptions(): array
    {
        return array_map(fn ($status) => [
            'label' => $status,
            'value' => $status,
        ], $this->allowedStatuses());
    }

    public static function defaultStatusOptions(): array
    {
        return array_map(fn ($status) => [
            'label' => $status,
            'value' => $status,
        ], self::STANDARD_STATUSES);
    }

    public function mixDesign()
    {
        return $this->belongsTo(MixDesign::class, 'mix_design_id');
    }

    public function owner()
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    public function customer()
    {
        return $this->belongsTo(Patron::class, 'customer_id');
    }

    public function activities()
    {
        return $this->hasMany(CrmActivity::class, 'lead_id');
    }

    public function attachments()
    {
        return $this->hasMany(CrmAttachment::class, 'lead_id');
    }

    public function deal()
    {
        return $this->hasOne(CrmDeal::class, 'lead_id');
    }
}
