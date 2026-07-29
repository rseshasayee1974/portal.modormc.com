<?php

namespace App\Models;

use Illuminate\Database\Eloquent\SoftDeletes;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class PersonnelContact extends Model
{
    use SoftDeletes, HasFactory;

    protected $table = 'mm_personnel_contacts';
    protected $primaryKey = 'contact_id';
    public $incrementing = false;
    protected $keyType = 'string';
    public $timestamps = false;

    protected $fillable = [
        'contact_id',
        'employee_id',
        'contact_type',
        'contact_value',
        'is_primary',
        'deleted_by',
    ];

    protected $casts = [
        'is_primary' => 'boolean',
    ];

    public function employee()
    {
        return $this->belongsTo(Personnel::class, 'employee_id');
    }
}
