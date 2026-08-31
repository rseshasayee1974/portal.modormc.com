<?php

namespace App\Models;
use App\Traits\TracksModelChanges;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class ContactRelation extends Model
{
    use SoftDeletes, TracksModelChanges;
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
