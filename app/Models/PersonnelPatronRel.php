<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PersonnelPatronRel extends Model
{
    protected $table = 'mm_personnel_patron_rels';

    protected $fillable = [
        'employee_id',
        'patron_id'
    ];

    public function employee()
    {
        return $this->belongsTo(Personnel::class, 'employee_id');
    }

    public function patron()
    {
        return $this->belongsTo(Patron::class, 'patron_id');
    }
}
