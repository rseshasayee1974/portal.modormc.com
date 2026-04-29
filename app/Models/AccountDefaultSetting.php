<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Traits\AuditFields;

class AccountDefaultSetting extends Model
{
    use HasFactory, SoftDeletes, AuditFields;

    protected $table = 'mm_account_default_settings';

    protected $fillable = [
        'plant_id',
        'module_id',
        'module_name',
        'setting_key',
        'ledger_id',
        'is_active',
        'created_by',
        'updated_by',
        'deleted_by',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function ledger()
    {
        return $this->belongsTo(Ledger::class, 'ledger_id');
    }

    public function plant()
    {
        return $this->belongsTo(Plant::class, 'plant_id');
    }

    public function module()
    {
        return $this->belongsTo(Module::class, 'module_id');
    }
}
