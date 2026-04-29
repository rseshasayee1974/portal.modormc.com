<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;

use App\Traits\AuditFields;

class Contact extends Model
{
    use HasFactory, SoftDeletes, AuditFields;
    protected $table = 'mm_contacts';
    protected $fillable = [
        'plant_id',
        'patron_id',
        'contact_type_id',
        'name',
        'email',
        'mobile',
        'alt_mobile',
        'landline',
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
    ];

    public function patron()
    {
        return $this->belongsTo(Patron::class);
    }

    public function plant()
    {
        return $this->belongsTo(Plant::class, 'plant_id');
    }

    public function contactType()
    {
        return $this->belongsTo(ContactType::class, 'contact_type_id');
    }

    public function addresses()
    {
        return $this->hasMany(Address::class);
    }
}
