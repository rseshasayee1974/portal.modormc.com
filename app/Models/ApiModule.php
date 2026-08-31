<?php

namespace App\Models;
use App\Traits\TracksModelChanges;

use Illuminate\Database\Eloquent\SoftDeletes;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ApiModule extends Model
{
    use SoftDeletes, HasFactory, TracksModelChanges;

    protected $table = 'mm_modules';

    protected $fillable = [
        'name',
        'price_per_1000_tokens',
        'price_per_request',
        'deleted_by',
    ];
}
