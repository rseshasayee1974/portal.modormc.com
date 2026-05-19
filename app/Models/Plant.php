<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Traits\AuditFields;

class Plant extends Model
{
    use HasFactory, SoftDeletes, AuditFields;
    protected $table = 'mm_plants';
    protected $fillable = [
        'entity_id',
        'code',
        'name',
        'logo_path',
        'email_address',
        'mobile_number',
        'plant_type',
        'gstin',
        'latitude',
        'longitude',
        'is_main',
        'is_active',
        'is_initialized',
        'shift_start_time',
        'shift_end_time',
        'scheduler_api_url',
        'scheduler_api_token',
        'scheduler_oauth_url',
        'scheduler_client_id',
        'scheduler_client_secret',
        'plc_ip',
        'last_heartbeat_at',
        'created_by',
        'updated_by',
        'deleted_by',
    ];

    protected $casts = [
        'is_main' => 'boolean',
        'is_active' => 'int',
        'is_initialized' => 'boolean',
        'last_heartbeat_at' => 'datetime',
    ];

    public function entity()
    {
        return $this->belongsTo(Entity::class, 'entity_id');
    }

    public function addresses()
    {
        return $this->morphToMany(Address::class, 'addressable', 'mm_address_relation');
    }

    public function contacts()
    {
        return $this->morphToMany(Contact::class, 'contactable', 'mm_contact_relation');
    }

    /**
     * Get the shift date and shift name for a given timestamp.
     * Based on shift_start_time (default 12:00:00).
     */
    public function getCurrentShiftInfo(\DateTimeInterface $time = null): array
    {
        $time = $time ? \Carbon\Carbon::parse($time) : now();
        $startTime = $this->shift_start_time ?? '12:00:00';
        
        $shiftStartToday = \Carbon\Carbon::parse($time->format('Y-m-d') . ' ' . $startTime);
        
        if ($time->lessThan($shiftStartToday)) {
            $shiftDate = $time->copy()->subDay()->format('Y-m-d');
        } else {
            $shiftDate = $time->format('Y-m-d');
        }
        
        return [
            'shift_date' => $shiftDate,
            'shift' => 'A', // Default shift name
        ];
    }
}
