<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class OpeningBalanceAuditLog extends Model
{
    protected $table = 'mm_opening_balance_audit_logs';
    public $timestamps = false;
    protected $guarded = ['id'];
    protected $casts = ['before_values' => 'array', 'after_values' => 'array', 'journal' => 'array', 'created_at' => 'datetime'];

    protected static function booted(): void
    {
        static::updating(fn () => throw new \LogicException('Opening balance audit records cannot be changed.'));
        static::deleting(fn () => throw new \LogicException('Opening balance audit records cannot be deleted.'));
    }
}
