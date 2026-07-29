<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
class Designation extends Model
{
        use HasFactory, SoftDeletes;

    protected $table = 'mm_designations';

    protected $fillable = [
        'name',
        'code',
        'min_salary',
        'max_salary',
    ];

    public function personnels()
    {
        return $this->hasMany(Personnel::class, 'designation_id');
    }
}
