<?php

namespace App\Models;
use App\Traits\TracksModelChanges;

use Illuminate\Database\Eloquent\SoftDeletes;

use Illuminate\Database\Eloquent\Model;

class PersonnelPatronRel extends Model
{
    use SoftDeletes, TracksModelChanges;
    protected $table = 'mm_personnel_patron_rels';

    protected $fillable = [
        'employee_id',
        'patron_id',
        'deleted_by',
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
