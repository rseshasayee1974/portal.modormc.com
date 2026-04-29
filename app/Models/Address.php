<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;

use App\Traits\AuditFields;

class Address extends Model
{
    use HasFactory, SoftDeletes, AuditFields;
    
    protected $table = 'mm_addresses';

    protected $fillable = [
        'plant_id',
        'contact_id',
        'address_type_id',
        'line_1',
        'line_2',
        'city',
        'state_id',
        'state_code',
        'zipcode',
        'landmark',
        'latitude',
        'longitude',
        'is_primary',
        'status',
        'displayed',
        'created_by',
        'updated_by',
        'deleted_by',
    ];

    protected $casts = [
        'is_primary' => 'boolean',
        'status' => 'boolean',
        'displayed' => 'boolean',
        'latitude' => 'decimal:7',
        'longitude' => 'decimal:7',
    ];

    public function contact()
    {
        return $this->belongsTo(Contact::class);
    }

    public function plant()
    {
        return $this->belongsTo(Plant::class, 'plant_id');
    }

    public function addressType()
    {
        return $this->belongsTo(AddressType::class, 'address_type_id');
    }

    public function state()
    {
        return $this->belongsTo(StateCode::class, 'state_id');
    }
}
