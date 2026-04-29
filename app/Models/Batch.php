<?php

namespace App\Models;

use App\Traits\AuditFields;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Batch extends Model
{
    use HasFactory, SoftDeletes, AuditFields;

    protected $table = 'mm_batches';

    protected $fillable = [
        'work_order_id',
        'batch_no',
        'batch_size',
        'start_time',
        'end_time',
        'truck_id',
        'transport_id',
        'driver_id',
        'empty_weight_truck',
        'loaded_weight_truck',
        'net_weight',
        'uom_id',
        // 'load_rate',
        // 'load_tax_id',
        'site_id',
        'status',
        'created_by',
        'updated_by',
        'deleted_by',
    ];

    protected $casts = [
        'batch_size' => 'decimal:2',
        'start_time' => 'datetime',
        'end_time' => 'datetime',
    ];

    public const STATUS_PLANNED = 1;
    public const STATUS_LOADING = 2;
    public const STATUS_DISPATCHED = 3;
    public const STATUS_COMPLETED = 4;
    public const STATUS_CANCELLED = 5;

    public static function statusOptions(): array
    {
        return [
            ['label' => 'Planned', 'value' => self::STATUS_PLANNED],
            // ['label' => 'Loading', 'value' => self::STATUS_LOADING],
            ['label' => 'Dispatched', 'value' => self::STATUS_DISPATCHED],
            // ['label' => 'Completed', 'value' => self::STATUS_COMPLETED],
            ['label' => 'Cancelled', 'value' => self::STATUS_CANCELLED],
        ];
    }

    public static function statusLabel(int $status): string
    {
        return collect(self::statusOptions())->firstWhere('value', $status)['label'] ?? 'Unknown';
    }

    public function workOrder()
    {
        return $this->belongsTo(WorkOrder::class, 'work_order_id');
    }

    public function truck()
    {
        return $this->belongsTo(Machine::class, 'truck_id');
    }

    public function materials()
    {
        return $this->hasMany(BatchMaterial::class, 'batch_id');
    }

    public function dispatches()
    {
        return $this->hasMany(Dispatch::class, 'batch_id');
    }
}
