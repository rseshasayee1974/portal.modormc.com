<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
class EinvoiceAuth extends Model
{
        use HasFactory;

    protected $table = 'mm_einvoice_auth';

    const CREATED_AT = 'created_at';
    const UPDATED_AT = 'modified_at';

    protected $fillable = [
        'plant_id',
        'user_id',
        'app_key',
        'user_name',
        'auth_token',
        'sek_key',
        'token_generated_at',
        'token_expiry_at',
        'created_by',
    ];

    protected $casts = [
        'token_generated_at' => 'datetime',
        'token_expiry_at' => 'datetime',
    ];

    public function plant()
    {
        return $this->belongsTo(Plant::class, 'plant_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
