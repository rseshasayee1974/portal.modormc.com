<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Traits\PlantScoping;

class InventoryAuditLog extends Model
{
    use HasFactory, PlantScoping;

    protected $table = 'mm_inventory_audit_logs';

    protected static function booted()
    {
        static::creating(function ($log) {
            $log->action_type = strtoupper($log->action_type ?? $log->transaction_type ?? '');
            if ($log->action_type === 'CREATE') {
                return false;
            }
        });
    }

    protected $fillable = [
        'plant_id',
        'transaction_type',
        'action_type',
        'reference_type',
        'reference_id',
        'log_from',
        'log_to',
        'user_id',
        'remarks',
        'ip_address',
    ];

    const UPDATED_AT = null;
    // const UPDATED_AT = null;

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
        $type = $this->reference_type;
        $class = $type && class_exists($type) ? $type : '\\App\\Models\\'.$type;
        if (!is_subclass_of($class, Model::class)) {
            return $this->belongsTo(Product::class, 'reference_id')->whereRaw('1 = 0');
        }
        return $this->belongsTo($class, 'reference_id');
    }
}
