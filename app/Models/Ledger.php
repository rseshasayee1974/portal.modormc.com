<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Models\AccountsType;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Traits\AuditFields;
use Illuminate\Support\Str;
use App\Http\Controllers\Concerns\GeneratesAccountingCode;

class Ledger extends Model
{
    use HasFactory, SoftDeletes, AuditFields, GeneratesAccountingCode;

    protected $table = 'mm_ledgers';

    protected $fillable = [
         
        'plant_id',
        'account_type_id',
        'code',
        'is_pnl',
        'title',
        'slug',
        'notes',
        'description',
        'status',
        'created_by',
        'updated_by',
        'deleted_by',
    ];

    protected $casts = [
        'status'      => 'integer',
        'is_pnl'      => 'boolean',
    ];

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($ledger) {
            if (empty($ledger->slug)) {
                $ledger->slug = Str::slug($ledger->title);
            }
            if (is_null($ledger->status)) {
                $ledger->status = 1;
            }
            if (empty($ledger->plant_id)) {
                $ledger->plant_id = session('active_plant_id');
            }
        });
    }

    /**
     * Relationship: Ledger belongs to an Account Type.
     */
    public function accountType()
    {
        return $this->belongsTo(AccountsType::class, 'account_type_id', 'id');
    }

    /**
     * Relationship: Ledger belongs to a Plant.
     */
    public function plant()
    {
        return $this->belongsTo(Plant::class, 'plant_id', 'id');
    }

    /**
     * Business Logic: Validate the code against the account type category.
     */
    public function isValidCodeForCategory($code, $category): bool
    {
        return $this->validateCodeRange($code, $category);
    }

    /**
     * Business Logic: Generate next code.
     */
    public static function generateNextCodeForCategory($category, $plantId)
    {
        $instance = new static();
        return $instance->generateNextCode($category, 'ledgers', $plantId);
    }
}
