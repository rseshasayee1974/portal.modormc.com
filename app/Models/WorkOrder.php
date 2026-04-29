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

    public static function generateOrderNo(string $prefix = 'WO'): string
    {
        $normalizedPrefix = Str::upper(trim($prefix)) ?: 'WO';
        
        $now = now();
        $startYear = $now->month >= 4 ? $now->year : $now->year - 1;
        $fyString = substr($startYear, -2) . substr($startYear + 1, -2);

        $hasPrefixColumn = Schema::hasColumn('mm_work_orders', 'prefix');
        $hasOrderNoColumn = Schema::hasColumn('mm_work_orders', 'order_no');
        $hasLegacyOrderColumn = Schema::hasColumn('mm_work_orders', 'work_order_number');

        if (!$hasOrderNoColumn && !$hasLegacyOrderColumn) {
            return sprintf('%s-%s-%04d', $normalizedPrefix, $fyString, 1);
        }

        $identifierColumn = $hasOrderNoColumn ? 'order_no' : 'work_order_number';
        $latestQuery = self::query();
        if ($hasPrefixColumn) {
            $latestQuery->where('prefix', $normalizedPrefix);
        }
        
        $latestQuery->where($identifierColumn, 'LIKE', "{$normalizedPrefix}-{$fyString}-%");

        $latest = $latestQuery->latest('id')->value($identifierColumn);

        $sequence = 1;
        if ($latest && preg_match('/(\d{4})$/', $latest, $matches)) {
            $sequence = ((int) $matches[1]) + 1;
        }

        return sprintf('%s-%s-%04d', $normalizedPrefix, $fyString, $sequence);
    }
}
