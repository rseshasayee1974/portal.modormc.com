<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class EwaybillAuth extends Model
{
    use HasFactory;

    protected $table = 'mm_ewaybill_auth';

    const CREATED_AT = 'created_at';
    const UPDATED_AT = 'modified_at';

    protected $fillable = [
        'plant_id',
        'user_id',
        'username',
        'password',
        'gstin',
        'authtoken',
        'transaction_no',
        'token_generated_at',
        'token_expiry_at',
        'created_by',
        'modified_by',
    ];

    protected $casts = [
        'token_generated_at' => 'datetime',
        'token_expiry_at'    => 'datetime',
    ];

    public function plant()
    {
        return $this->belongsTo(Plant::class, 'plant_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /**
     * SEK key accessor - maps to transaction_no column.
     */
    public function getSekKeyAttribute(): ?string
    {
        return $this->transaction_no;
    }

    public function setSekKeyAttribute(?string $value): void
    {
        $this->transaction_no = $value;
    }
}
