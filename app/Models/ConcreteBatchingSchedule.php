<?php

namespace App\Models;

use App\Traits\PlantScoping;
use App\Traits\TracksModelChanges;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class ConcreteBatchingSchedule extends Model
{
    use HasFactory, SoftDeletes, PlantScoping, TracksModelChanges;

    public const PUMP_TYPES = [
        'boom_pump',
        'line_pump',
        'crane_bucket',
        'direct_pour',
        'stationary_pump',
    ];

    protected $table = 'mm_concrete_batching_schedules';

    protected $guarded = [];

    protected $casts = [
        'schedule_date'       => 'date:Y-m-d',
        'batching_time'       => 'datetime',
        'dispatch_time'       => 'datetime',
        'eta_site'            => 'datetime',
        'unloading_start'     => 'datetime',
        'unloading_end'       => 'datetime',
        'qty_m3'              => 'decimal:3',
        'order_volume_m3'     => 'decimal:3',
        'remaining_volume_m3' => 'decimal:3',
    ];

    protected $appends = [
        'hydration_age_minutes',
        'hydration_status',
        'turnaround_minutes',
        'transit_duration_minutes',
        'discharge_duration_minutes',
        'pour_progress_percent',
    ];

    /**
     * Boot model events to auto-recalculate pour remaining balances.
     */
    protected static function booted()
    {
        static::saved(function (self $model) {
            if ($model->pour_reference) {
                self::recalculatePourBalances($model->pour_reference, $model->plant_id);
            }
        });

        static::deleted(function (self $model) {
            if ($model->pour_reference) {
                self::recalculatePourBalances($model->pour_reference, $model->plant_id);
            }
        });
    }

    /**
     * Relationships
     */
    public function plant(): BelongsTo
    {
        return $this->belongsTo(Plant::class);
    }

    public function site(): BelongsTo
    {
        return $this->belongsTo(Site::class);
    }

    public function mixDesign(): BelongsTo
    {
        return $this->belongsTo(MixDesign::class, 'mix_design_id');
    }

    public function vehicle(): BelongsTo
    {
        return $this->belongsTo(Machine::class, 'vehicle_id');
    }

    public function transitMixer(): BelongsTo
    {
        return $this->belongsTo(Machine::class, 'vehicle_id');
    }

    public function driver(): BelongsTo
    {
        return $this->belongsTo(Personnel::class, 'driver_id');
    }

    public function pumpVehicle(): BelongsTo
    {
        return $this->belongsTo(Machine::class, 'pump_vehicle_id');
    }

    public function salesOrder(): BelongsTo
    {
        return $this->belongsTo(SalesOrder::class, 'sales_order_id');
    }

    public function batch(): BelongsTo
    {
        return $this->belongsTo(Batch::class, 'batch_id');
    }

    public function dispatch(): BelongsTo
    {
        return $this->belongsTo(Dispatch::class, 'dispatch_id');
    }

    /**
     * Accessors & Real-Time Hydration / Transit Monitoring
     */

    /**
     * Elapsed minutes since concrete batching water contact.
     */
    public function getHydrationAgeMinutesAttribute(): ?int
    {
        if (!$this->batching_time) {
            return null;
        }

        $endTime = $this->unloading_end ?? now();
        return max(0, (int) $this->batching_time->diffInMinutes($endTime));
    }

    /**
     * Hydration state alert based on ASTM C94 / IS 4926 (90-120 min max threshold).
     */
    public function getHydrationStatusAttribute(): string
    {
        if (!$this->batching_time) {
            return 'not_started';
        }

        if ($this->status === 'completed') {
            return 'discharged';
        }

        $mins = $this->hydration_age_minutes ?? 0;

        if ($mins < 90) {
            return 'normal'; // Within fresh workable hydration window
        } elseif ($mins <= 120) {
            return 'warning'; // Approaching initial set (90 - 120 mins)
        } else {
            return 'critical'; // Exceeded 120 mins - risk of cold joint / slump loss
        }
    }

    /**
     * Total cycle turnaround duration (batching to discharge completion).
     */
    public function getTurnaroundMinutesAttribute(): ?int
    {
        if (!$this->batching_time || !$this->unloading_end) {
            return null;
        }

        return max(0, (int) $this->batching_time->diffInMinutes($this->unloading_end));
    }

    /**
     * Haulage / transit travel duration from plant to site.
     */
    public function getTransitDurationMinutesAttribute(): ?int
    {
        if (!$this->dispatch_time) {
            return null;
        }

        $arrival = $this->unloading_start ?? $this->eta_site ?? now();
        return max(0, (int) $this->dispatch_time->diffInMinutes($arrival));
    }

    /**
     * Time spent pumping / discharging concrete on-site.
     */
    public function getDischargeDurationMinutesAttribute(): ?int
    {
        if (!$this->unloading_start || !$this->unloading_end) {
            return null;
        }

        return max(0, (int) $this->unloading_start->diffInMinutes($this->unloading_end));
    }

    /**
     * Pour completion percentage based on total ordered volume.
     */
    public function getPourProgressPercentAttribute(): float
    {
        $orderVol = (float) $this->order_volume_m3;
        if ($orderVol <= 0) {
            return 0.0;
        }

        $delivered = $orderVol - (float) $this->remaining_volume_m3;
        return (float) min(100, round(($delivered / $orderVol) * 100, 1));
    }

    /**
     * Recalculate remaining volumes chronologically for all schedule slots under a pour reference.
     */
    public static function recalculatePourBalances(string $pourReference, ?int $plantId = null): void
    {
        $query = static::withoutGlobalScopes()
            ->where('pour_reference', $pourReference)
            ->orderBy('schedule_date', 'asc')
            ->orderBy('batching_time', 'asc')
            ->orderBy('id', 'asc');

        if ($plantId) {
            $query->where('plant_id', $plantId);
        }

        $schedules = $query->get();
        if ($schedules->isEmpty()) {
            return;
        }

        $salesOrderId = $schedules->first(fn($s) => !empty($s->sales_order_id))?->sales_order_id;
        $orderVolume = 0.0;
        if ($salesOrderId) {
            $salesOrder = SalesOrder::find($salesOrderId);
            if ($salesOrder && (float)$salesOrder->total_qty > 0) {
                $orderVolume = (float) $salesOrder->total_qty;
            }
        }
        if ($orderVolume <= 0) {
            $orderVolume = (float) ($schedules->first()->order_volume_m3 ?? 0);
        }

        $cumulative = 0.0;

        foreach ($schedules as $item) {
            // Count loads that are not cancelled
            if ($item->status !== 'cancelled') {
                $cumulative += (float) $item->qty_m3;
            }

            $remaining = max(0.0, $orderVolume - $cumulative);

            if (abs((float)$item->remaining_volume_m3 - $remaining) > 0.001 || abs((float)$item->order_volume_m3 - $orderVolume) > 0.001) {
                // Update without triggering boot events in loop
                static::withoutEvents(function () use ($item, $orderVolume, $remaining) {
                    $item->updateQuietly([
                        'order_volume_m3'     => $orderVolume,
                        'remaining_volume_m3' => $remaining,
                    ]);
                });
            }
        }
    }
}
