<?php
/*
Author: Cipher
Created: 2026-11-04 16:52:40 Asia/Calcutta (UTC+05:30)
*/

namespace App\Models;

use App\Traits\TracksModelChanges;
use Illuminate\Database\Eloquent\{Model, SoftDeletes};

class PersonnelUserRel extends Model
{
    use SoftDeletes, TracksModelChanges;

    protected $table = 'mm_personnel_user_rels';
    protected $fillable = ['personnel_id', 'user_id', 'plant_id'];

    public function personnel()
    {
        return $this->belongsTo(Personnel::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function plant()
    {
        return $this->belongsTo(Plant::class);
    }
}
