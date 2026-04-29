<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class AccountsType extends Model
{
    use HasFactory, SoftDeletes;
    protected $table = 'mm_account_types';
    public $timestamps = false;

    protected $fillable = [
        'plant_id',
        'code',
        'account_id',
        'parent_id',
        'title',
        'status',
        'created_at',
        'created_by',
        'updated_at',
        'updated_by',
        'updated_at',
        'deleted_at',
        'deleted_by',
    ];

    protected $casts = [
        'created_at'  => 'datetime',
        'updated_at'  => 'datetime',
        'deleted_at'  => 'datetime', 
    ];

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($model) {
            if (empty($model->plant_id)) {
                $model->plant_id = session('active_plant_id');
            }
        });
    }

    /**
     * Relationship: AccountType belongs to an Account.
     */
    public function account()
    {
        return $this->belongsTo(Accounts::class, 'account_id');
    }


    /**
     * Relationship: AccountType can have a parent AccountType.
     */
    public function parent()
    {
        return $this->belongsTo(AccountsType::class, 'parent_id');
    }

    /**
     * Relationship: AccountType can have many children AccountTypes.
     */
    public function children()
    {
        return $this->hasMany(AccountsType::class, 'parent_id');
    }
}
