<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AddressRelation extends Model
{
    protected $table = 'mm_address_relation';

    protected $fillable = [
        'address_id',
        'addressable_id',
        'addressable_type',
    ];

    public function address()
    {
        return $this->belongsTo(Address::class);
    }

    public function addressable()
    {
        return $this->morphTo();
    }
}
