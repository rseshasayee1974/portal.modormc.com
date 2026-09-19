<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class OpeningBalanceBatch extends Model
{
    protected $table = 'mm_opening_balance_batches';
    protected $guarded = ['id'];
    protected $casts = ['version' => 'integer', 'lines' => 'array', 'cutover_date' => 'date:Y-m-d', 'posted_at' => 'datetime'];
}
