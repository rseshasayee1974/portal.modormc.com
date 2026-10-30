<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class OpeningBalanceAuditLog extends Model
{
    protected $table = 'mm_opening_balance_audit_logs';
    public $timestamps = false;
    protected $guarded = ['id'];
    protected $casts = ['before_values' => 'array', 'after_values' => 'array', 'journal' => 'array', 'created_at' => 'datetime'];

}
