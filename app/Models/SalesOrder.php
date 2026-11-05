<?php

namespace App\Models;

use App\Services\SalesOrders\SalesOrderEmails;
use App\Traits\LogsPushNotifications;
use App\Traits\PlantScoping;
use App\Traits\TracksModelChanges;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class SalesOrder extends Model
{
    use HasFactory, LogsPushNotifications, PlantScoping, SoftDeletes, TracksModelChanges;

    protected $table = 'mm_sales_orders';

    protected static function booted(): void
    {
        static::created(fn (SalesOrder $order) => app(SalesOrderEmails::class)->record($order));
    }

    protected $fillable = [
        'prefix',
        'order_no',
        'plant_id',
        'sales_executive_id',
        'customer_id',
        'site_id',
        'mix_design_id',
        'total_qty',
        'rate',
        'tax_id',
        'is_tax_inclusive',
        'produced_qty',
        'scheduled_start',
        'scheduled_end',
        'status',
        'customer_po_id',
        'concrete_pump',
        'pump_rate',
        'pump_type',
        'uom_id',
        'created_by',
        'updated_by',
        'deleted_by',
    ];

    protected $casts = [
        'plant_id' => 'integer',
        'customer_id' => 'integer',
        'site_id' => 'integer',
        'mix_design_id' => 'integer',
        'customer_po_id' => 'integer',
        'status' => 'integer',
        'uom_id' => 'integer',
        'scheduled_start' => 'datetime',
        'scheduled_end' => 'datetime',
        'total_qty' => 'decimal:3',
        'produced_qty' => 'decimal:3',
        'rate' => 'decimal:3',
        'tax_id' => 'integer',
        'is_tax_inclusive' => 'boolean',
        'sales_executive_id' => 'integer',
    ];

    protected $hidden = [
        'created_by',
        'updated_by',
        'deleted_by',
        'deleted_at',
    ];

    public function getIsTaxInclusiveAttribute($value): bool
    {
        if ($value !== null) {
            return (bool) $value;
        }

        return (bool) ($this->customerPO?->is_tax_inclusive ?? false);
    }

    public const STATUS_DRAFT = 0;

    public const STATUS_CONFIRMED = 1;

    public const STATUS_SCHEDULED = 1;

    public const STATUS_IN_PROGRESS = 2;

    public const STATUS_COMPLETED = 3;

    public const STATUS_CANCELLED = 4;

    public function plant()
    {
        return $this->belongsTo(Plant::class, 'plant_id');
    }

    public function salesExecutive()
    {
        return $this->belongsTo(Personnel::class, 'sales_executive_id');
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
        return $this->hasMany(Batch::class, 'sales_order_id');
    }

    public function items()
    {
        return $this->hasMany(SalesOrderItem::class, 'sales_order_id');
    }

    public function dispatches()
    {
        return $this->hasMany(Dispatch::class, 'sales_order_id');
    }

    public function latestDispatch()
    {
        return $this->hasOne(Dispatch::class, 'sales_order_id')->latest();
    }

    public function customerPO()
    {
        return $this->belongsTo(CustomerPO::class, 'customer_po_id');
    }

    public function tax()
    {
        return $this->belongsTo(Tax::class, 'tax_id');
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
        return $this->prefix . $this->order_no;
    }

    public function getRateAttribute($value)
    {
        // If a rate is stored directly on this record, use it
        if ($value !== null && (float) $value > 0) {
            return (float) $value;
        }

        // Fall back to CustomerPO-derived rate
        if ($this->customerPO) {
            $item = $this->customerPO->items()
                ->where('mix_design_id', $this->mix_design_id)
                ->first();
            if ($item) {
                return (float) $item->rate;
            }
        }

        return 0.0;
    }

    public function getPumpRateAttribute($value)
    {
        if ($value !== null && (float) $value > 0) {
            return (float) $value;
        }

        if ($this->customerPO) {
            $item = $this->customerPO->items()
                ->where('mix_design_id', $this->mix_design_id)
                ->first();
            if ($item && $item->pump_rate) {
                return (float) $item->pump_rate;
            }
        }

        return 0.0;
    }

    public function getTaxIdAttribute($value)
    {
        // If a tax_id is stored directly on this record, use it
        if ($value !== null && (int) $value > 0) {
            return (int) $value;
        }

        // Fall back to CustomerPO-derived tax_id
        if ($this->customerPO) {
            $item = $this->customerPO->items()
                ->where('mix_design_id', $this->mix_design_id)
                ->first();
            if ($item) {
                return $item->tax_id;
            }
        }

        return null;
    }

    public static function generateOrderNo(int $plantId, ?int $customerId, string $prefix = 'SO'): array
    {
        $now = now();
        // Financial Year: April to March
        $startYear = $now->month >= 4 ? $now->year : $now->year - 1;
        $endYear = $startYear + 1;
        $fyString = substr($startYear, -2) . '-' . substr($endYear, -2);

        $customPrefix = $prefix;
        $settings = CustomSetting::getForModule($plantId, 'batching');
        if ($prefix === 'SO') {
            if (! empty($settings['so_prefix'])) {
                $customPrefix = $settings['so_prefix'];
            }
        }

        $fullPrefix = "{$customPrefix}/{$fyString}/";
        $legacyYear = str_replace('-', '', $fyString);
        $legacyPrefix = "{$customPrefix}/{$legacyYear}/";
        $sequencePrefix = "{$customPrefix}/";
        $yearSuffix = "/{$fyString}";
        // Customer ID is retained in the method signature for existing callers.
        // All customers in a plant share the series, using only active orders.
        $orders = self::where('plant_id', $plantId)->whereNull('deleted_at');

        // Keep the plant's compact format across financial years for this series.
        $prefixPattern = strtr($customPrefix, ['!' => '!!', '%' => '!%', '_' => '!_']);
        $usesLegacyFormat = (clone $orders)
            ->whereRaw("prefix LIKE ? ESCAPE '!'", ["{$prefixPattern}/____/"])
            ->exists();

        // All layouts for the same financial year share the plant sequence.
        $latest = $orders
            ->where(function ($query) use ($fullPrefix, $legacyPrefix, $sequencePrefix, $yearSuffix) {
                $query->whereIn('prefix', [$fullPrefix, $legacyPrefix])
                    ->orWhere(fn ($suffixSeries) => $suffixSeries->where('prefix', $sequencePrefix)
                        ->where('order_no', 'like', '%' . $yearSuffix));
            })
            ->orderByRaw("CAST(REPLACE(order_no, ?, '') AS UNSIGNED) DESC", [$yearSuffix])
            ->value('order_no');

        $nextSequence = 1;
        if ($latest) {
            $nextSequence = (int) $latest + 1;
        }

        $yearAfterNumber = ($settings['so_number_format'] ?? null) === 'prefix_number_fy';
        $numberPrefix = $yearAfterNumber ? $sequencePrefix : ($usesLegacyFormat ? $legacyPrefix : $fullPrefix);
        $padding = max(0, min(10, (int) ($settings['so_number_padding'] ?? 4)));
        $nextNumber = str_pad((string) $nextSequence, $padding, '0', STR_PAD_LEFT);
        if ($yearAfterNumber) {
            $nextNumber .= $yearSuffix;
        }

        return [
            'prefix' => $numberPrefix,
            'next_number' => $nextNumber,
            'full_number' => $numberPrefix . $nextNumber,
        ];
    }

    public function refreshProduction(?int $manualStatus = null): void
    {
        $producedQty = (float) $this->batches()
            ->where('status', '!=', Batch::STATUS_CANCELLED)
            ->sum('batch_size');

        // Reload current status from DB
        $this->refresh();
        $status = $this->status;

        if ($producedQty <= 0) {
            $status = $manualStatus ?? $this->status;
        } elseif ($producedQty > 0 && $producedQty < (float) $this->total_qty && $this->status !== self::STATUS_CANCELLED) {
            $status = self::STATUS_IN_PROGRESS;
        } elseif ($producedQty >= (float) $this->total_qty && $this->status !== self::STATUS_CANCELLED) {
            $status = self::STATUS_COMPLETED;
        }

        $this->update([
            'produced_qty' => $producedQty,
            'status' => $status,
        ]);
    }
}
