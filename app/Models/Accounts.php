<?php

namespace App\Models;

use App\Models\Entity;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Accounts extends Model
{
    use HasFactory, SoftDeletes;
    
    protected $table = 'mm_accounts';
    public $timestamps = false;
    protected $fillable = [
        'plant_id',
        'code',
        'title',
        'status',
        'created_by',
        'created',
        'modified',
        'updated_by',
    ];

    /**
     * PHP 8.3 Typed Class Constant for allowed account types.
     */
    public const ACCOUNT_TYPES = [
        'ASSET',
        'EQUITY',
        'EXPENSE',
        'INCOME',
        'LIABILITY',
        'REVENUE',
    ];


    /**
     * Relationship: Accounts belong to a Plant.
     */
    public function plant()
    {
        return $this->belongsTo(Plant::class, 'plant_id');
    }

    /**
     * Helper to get the constant (for compatibility or easier access).
     */
    public static function accountNameType(): array
    {
        return self::ACCOUNT_TYPES;
    }

    /**
     * Scope: non-deleted records for the active entity session.
     */
    public function scopeActive($query)
    {
        return $query;
    }
}
