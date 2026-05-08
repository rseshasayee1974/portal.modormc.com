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
        'email_address',
        'mobile_number',
        'plant_type',
        'gstin',
        'latitude',
        'longitude',
        'is_main',
        'is_active',
        'is_initialized',
        'created_by',
        'updated_by',
        'deleted_by',
    ];

    protected $casts = [
        'is_main' => 'boolean',
        'is_active' => 'boolean',
        'is_initialized' => 'boolean',
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
}
