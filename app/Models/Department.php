<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
class Department extends Model
{
        use HasFactory, SoftDeletes;

    protected $table = 'mm_departments';

    protected $fillable = [
        'name',
        'code',
    ];

    public function personnels()
    {
        return $this->hasMany(Personnel::class, 'department_id');
    }
}
