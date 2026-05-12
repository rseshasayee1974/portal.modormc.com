<?php

namespace App\Models;

use App\Traits\AuditFields;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class WorkOrder extends Model
{
    use HasFactory, SoftDeletes, AuditFields;

    protected $table = 'mm_work_orders';

    protected $fillable = [
        'prefix',
        'order_no',
        'plant_id',
        'customer_id',
        'site_id',
        'mix_design_id',
        'total_qty',
        'produced_qty',
        'scheduled_start',
        'scheduled_end',
        'status',
        'created_by',
        'updated_by',
        'deleted_by',
    ];

    protected $casts = [
        'scheduled_start' => 'datetime',
        'scheduled_end' => 'datetime',
        'total_qty' => 'decimal:3',
        'produced_qty' => 'decimal:3',
    ];

    public const STATUS_SCHEDULED = 1;
    public const STATUS_IN_PROGRESS = 2;
    public const STATUS_COMPLETED = 3;
    public const STATUS_CANCELLED = 4;

    public function plant()
    {
        return $this->belongsTo(Plant::class, 'plant_id');
    }

    public function customer()
    {
        return $this->belongsTo(Patron::class, 'customer_id');
    }

    public function site()
    {
        return $this->belongsTo(Site::class, 'site_id');
    }

    public function mixDesign()
    {
        return $this->belongsTo(MixDesign::class, 'mix_design_id');
    }

    public function batches()
    {
        return $this->hasMany(Batch::class, 'work_order_id');
    }

    public function dispatches()
    {
        return $this->hasMany(Dispatch::class, 'work_order_id');
    }

    public static function statusOptions(): array
    {
        return [
            ['label' => 'Scheduled', 'value' => self::STATUS_SCHEDULED],
            ['label' => 'In Progress', 'value' => self::STATUS_IN_PROGRESS],
            ['label' => 'Completed', 'value' => self::STATUS_COMPLETED],
            ['label' => 'Cancelled', 'value' => self::STATUS_CANCELLED],
        ];
    }

    public static function statusLabel(int $status): string
    {
        return collect(self::statusOptions())
            ->firstWhere('value', $status)['label'] ?? 'Unknown';
    }

    protected $appends = [
        'full_number',
    ];

    public function getFullNumberAttribute()
    {
        return sprintf('%s%04d', $this->prefix, (int)$this->order_no);
    }

    public static function generateOrderNo(int $plantId, string $prefix = 'WO'): array
    {
        $now = now();
        // Financial Year: April to March
        $startYear = $now->month >= 4 ? $now->year : $now->year - 1;
        $endYear = $startYear + 1;
        $fyString = substr($startYear, -2) . substr($endYear, -2);
        
        $fullPrefix = "{$prefix}/{$fyString}/";

        // Get the latest order number for this plant and prefix
        $latest = self::where('plant_id', $plantId)
            ->where('prefix', $fullPrefix)
            ->whereNull('deleted_at')
            ->orderByRaw('CAST(order_no AS UNSIGNED) DESC')
            ->value('order_no');

        $nextSequence = 1;
        if ($latest) {
            $nextSequence = (int)$latest + 1;
        }

        return [
            'prefix' => $fullPrefix,
            'next_number' => str_pad((string)$nextSequence, 4, '0', STR_PAD_LEFT),
            'full_number' => $fullPrefix . str_pad((string)$nextSequence, 4, '0', STR_PAD_LEFT)
        ];
    }
}
