<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ContactRelation extends Model
{
    protected $table = 'mm_contact_relation';

    protected $fillable = [
        'contact_id',
        'contactable_id',
        'contactable_type',
    ];

    public function contact()
    {
        return $this->belongsTo(Contact::class);
    }

    public function contactable()
    {
        return $this->morphTo();
    }
}
