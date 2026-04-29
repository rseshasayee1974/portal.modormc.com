<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Traits\AuditFields;

class PatronBankAccount extends Model
{
    protected $table = 'mm_patron_bank_accounts';
    use HasFactory, SoftDeletes, AuditFields;

    protected $fillable = [
        'plant_id',
        'bank_account_type',
        'patron_id',
        'account_holder_name',
        'account_number',
        'bank_name',
        'branch_name',
        'ifsc_code',
        'is_primary',
        'status',
        'displayed',
        'created_by',
        'updated_by',
        'deleted_by',
    ];

    protected $casts = [
        'is_primary' => 'boolean',
        'status' => 'boolean',
        'displayed' => 'boolean',
    ];

    public function patron()
    {
        return $this->belongsTo(Patron::class);
    }

    public function plant()
    {
        return $this->belongsTo(Plant::class, 'plant_id');
    }

    public function bankAccountType()
    {
        return $this->belongsTo(BankAccountType::class, 'bank_account_type');
    }
}
