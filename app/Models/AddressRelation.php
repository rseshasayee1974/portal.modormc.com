<?php

namespace App\Models;
use App\Traits\TracksModelChanges;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class AddressRelation extends Model
{
    use SoftDeletes, TracksModelChanges;
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
