<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Traits\PlantScoping;

class InventoryAuditLog extends Model
{
    use HasFactory, PlantScoping;

    protected $table = 'mm_inventory_audit_logs';

    protected $fillable = [
        'plant_id',
        'transaction_type',
        'reference_type',
        'reference_id',
        'log_from',
        'log_to',
        'user_id',
        'remarks',
        'ip_address',
    ];

    const UPDATED_AT = null;

    protected $casts = [
        'created_at' => 'datetime',
    ];

    /**
     * Relationship: Plant.
     */
    public function plant()
    {
        return $this->belongsTo(Plant::class, 'plant_id');
    }

    /**
     * Relationship: User.
     */
    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /**
     * Relationship: Polymorphic reference (e.g. Product, etc.).
     */
    public function reference()
    {
        return $this->morphTo(null, 'reference_type', 'reference_id');
    }
}