<?php

namespace App\Models;

use App\Traits\AuditFields;
use App\Traits\PlantScoping;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class WorkOrder extends Model
{
    use HasFactory, SoftDeletes, AuditFields, PlantScoping;

    protected static function boot()
    {
        parent::boot();

        static::updated(function (WorkOrder $workorder) {
            try {
                foreach ($workorder->getDirty() as $key => $newValue) {
                    if (in_array($key, ['updated_at', 'created_at', 'deleted_at', 'produced_qty'])) {
                        continue;
                    }
                    $oldValue = $workorder->getOriginal($key);

                    // Safe fallback for the decimal columns in case a string/date is changed
                    $logFrom = is_numeric($oldValue) ? $oldValue : 0;
                    $logTo = is_numeric($newValue) ? $newValue : 0;

                    \App\Models\InventoryAuditLog::create([
                        'plant_id' => $workorder->plant_id,
                        'transaction_type' => 'work_order',
                        'reference_type' => 'Update ' . ucfirst(str_replace('_', ' ', $key)),
                        'reference_id' => $workorder->id,
                        'log_from' => $logFrom,
                        'log_to' => $logTo,
                        'user_id' => \Illuminate\Support\Facades\Auth::id(),
                        'remarks' => "Updated {$key}: '{$oldValue}' => '{$newValue}'",
                        'ip_address' => request()->ip(),
                    ]);
                }
            } catch (\Exception $e) {
                \Illuminate\Support\Facades\Log::error('Failed to log work order update: ' . $e->getMessage());
            }
        });
    }

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
        'sales_order_id',
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

    public function salesOrder()
    {
        return $this->belongsTo(SalesOrder::class, 'sales_order_id');
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
// dd($fullPrefix,str_pad((string)$nextSequence, 4, '0', STR_PAD_LEFT));
        return [
            'prefix' => $fullPrefix,
            'next_number' => str_pad((string)$nextSequence, 4, '0', STR_PAD_LEFT),
            'full_number' => $fullPrefix . str_pad((string)$nextSequence, 4, '0', STR_PAD_LEFT)
        ];
    }
    public function refreshProduction(?int $manualStatus = null): void
    {
        $producedQty = (float) $this->batches()
            ->where('status', '!=', Batch::STATUS_CANCELLED)
            ->sum('batch_size');

        // Reload current status from DB (in case it was just updated)
        $this->refresh();
        $status = $this->status;

        if ($producedQty <= 0) {
            // No production yet — respect the manually set status (e.g. In Progress, Cancelled)
            // Only auto-set to Scheduled if user hasn't changed it away from Scheduled
            $status = $manualStatus ?? $this->status;
        } elseif ($producedQty > 0 && $producedQty < (float) $this->total_qty && $this->status !== self::STATUS_CANCELLED) {
            $status = self::STATUS_IN_PROGRESS;
        } elseif ($producedQty >= (float) $this->total_qty && $this->status !== self::STATUS_CANCELLED) {
            $status = self::STATUS_COMPLETED;
        }

        $this->update([
            'produced_qty' => $producedQty,
            'status'       => $status,
        ]);
    }
}