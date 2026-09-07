<?php

namespace App\Models;

use App\Traits\PlantScoping;
use App\Traits\TracksModelChanges;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class PumpBoomDeploymentSchedule extends Model
{
    use HasFactory, SoftDeletes, PlantScoping, TracksModelChanges;

    protected $table = 'mm_pump_boom_deployment_schedule';

    protected $guarded = [];

    protected $casts = [
        'schedule_date'      => 'date:Y-m-d',
        'planned_qty_m3'     => 'decimal:3',
        'boom_length_m'      => 'decimal:2',
        'pump_arrival_time'  => 'datetime',
        'setup_start_time'   => 'datetime',
        'setup_end_time'     => 'datetime',
        'pour_start_time'    => 'datetime',
        'planned_end_time'   => 'datetime',
        'actual_start_time'  => 'datetime',
        'actual_end_time'    => 'datetime',
    ];

    protected $appends = [
        'setup_duration_minutes',
        'pumping_duration_minutes',
        'pumping_rate_m3_per_hour',
        'is_delayed',
        'delay_minutes',
    ];

    /**
     * Auto-sync site_name, grade, pump_no, and operator_name from relations when creating.
     */
    protected static function booted()
    {
        static::creating(function (self $model) {
            if ($model->site_id && empty($model->site_name)) {
                $model->site_name = Site::find($model->site_id)?->name;
            }
            if ($model->mix_design_id && empty($model->grade)) {
                $model->grade = MixDesign::find($model->mix_design_id)?->name;
            }
            if ($model->pump_vehicle_id && empty($model->pump_no)) {
                $model->pump_no = Machine::find($model->pump_vehicle_id)?->registration;
            }
            if ($model->operator_id && empty($model->operator_name)) {
                $p = Personnel::find($model->operator_id);
                $model->operator_name = $p ? ($p->first_name . ' ' . ($p->last_name ?? '')) : null;
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

    public function pumpMachine(): BelongsTo
    {
        return $this->belongsTo(Machine::class, 'pump_vehicle_id');
    }

    public function operator(): BelongsTo
    {
        return $this->belongsTo(Personnel::class, 'operator_id');
    }

    /**
     * Linked Transit Mixer trip schedules under the same pour reference.
     */
    public function batchSchedules(): HasMany
    {
        return $this->hasMany(ConcreteBatchingSchedule::class, 'pour_reference', 'pour_reference');
    }

    /**
     * Operational Accessors
     */

    /**
     * Duration in minutes spent rigging up outriggers, booms, and pipelines.
     */
    public function getSetupDurationMinutesAttribute(): ?int
    {
        if (!$this->setup_start_time || !$this->setup_end_time) {
            return null;
        }

        return max(0, (int) $this->setup_start_time->diffInMinutes($this->setup_end_time));
    }

    /**
     * Duration in minutes of active concrete placement.
     */
    public function getPumpingDurationMinutesAttribute(): ?int
    {
        $start = $this->actual_start_time ?? $this->pour_start_time;
        if (!$start) {
            return null;
        }

        $end = $this->actual_end_time ?? ($this->status === 'pumping' ? now() : null);
        if (!$end) {
            return null;
        }

        return max(0, (int) $start->diffInMinutes($end));
    }

    /**
     * Average pumping speed achieved in m3 per hour.
     */
    public function getPumpingRateM3PerHourAttribute(): ?float
    {
        $mins = $this->pumping_duration_minutes;
        if (!$mins || $mins <= 0) {
            return null;
        }

        $qty = (float) $this->planned_qty_m3;
        $hours = $mins / 60.0;

        return round($qty / $hours, 1);
    }

    /**
     * Flag if the pour started later than planned.
     */
    public function getIsDelayedAttribute(): bool
    {
        if (!$this->pour_start_time || !$this->actual_start_time) {
            return false;
        }

        return $this->actual_start_time->gt($this->pour_start_time);
    }

    /**
     * Minutes delayed beyond target pour start time.
     */
    public function getDelayMinutesAttribute(): int
    {
        if (!$this->is_delayed) {
            return 0;
        }

        return (int) $this->pour_start_time->diffInMinutes($this->actual_start_time);
    }
}
