<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ApiModule extends Model
{
    use HasFactory;

    protected $table = 'mm_modules';

    protected $fillable = [
        'name',
        'price_per_1000_tokens',
        'price_per_request',
    ];
}
