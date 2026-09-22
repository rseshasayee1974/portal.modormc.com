<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class OpeningBalanceBatch extends Model
{
    use \Illuminate\Database\Eloquent\SoftDeletes;
    protected $table = 'mm_opening_balance_batches';
    protected $guarded = ['id'];
    protected $casts = ['version' => 'integer', 'lines' => 'array', 'cutover_date' => 'date:Y-m-d', 'posted_at' => 'datetime'];
}
