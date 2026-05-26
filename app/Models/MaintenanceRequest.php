<?php

namespace App\Models;

use App\Traits\PlantScoping;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Support\Facades\Auth;

class MaintenanceRequest extends Model
{
    use HasFactory, PlantScoping;

    protected $table = 'mm_machine_maintanence_request';

    public const CREATED_AT = 'created';
    public const UPDATED_AT = 'modified';

    protected $fillable = [
        'name',
        'description',
        'machine_id',
        'plant_id',
        'max_idle_days',
        'inventory_req_lines',
        'maintanence_type',
        'service_km',
        'priority',
        'responsible_id',
        'repair_location',
        'repair_vendor_id',
        'bill_no',
        'order_no',
        'discount_amount',
        'shipping_charges',
        'shipping_tax_id',
        'adjustment',
        'rounding_value',
        'filename',
        'status',
        'bill_status',
        'dead_line',
        'start_date',
        'end_date',
        'created_by',
        'modified_by'
    ];

    protected $casts = [
        'dead_line' => 'datetime',
        'start_date' => 'datetime',
        'end_date' => 'datetime',
        'created' => 'datetime',
        'modified' => 'datetime',
        'service_km' => 'decimal:2',
        'discount_amount' => 'decimal:2',
        'shipping_charges' => 'decimal:2',
        'adjustment' => 'decimal:2',
        'rounding_value' => 'decimal:2',
    ];

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($model) {
            if (Auth::check()) {
                $model->created_by = Auth::id();
                $model->modified_by = Auth::id();
            }
        });

        static::updating(function ($model) {
            if (Auth::check()) {
                $model->modified_by = Auth::id();
            }
        });
    }

    public function machine()
    {
        return $this->belongsTo(Machine::class, 'machine_id');
    }

    public function plant()
    {
        return $this->belongsTo(Plant::class, 'plant_id');
    }

    public function responsible()
    {
        return $this->belongsTo(User::class, 'responsible_id');
    }

    public function vendor()
    {
        return $this->belongsTo(Patron::class, 'repair_vendor_id');
    }

    public function shippingTax()
    {
        return $this->belongsTo(Tax::class, 'shipping_tax_id');
    }

    public function lines()
    {
        return $this->hasMany(MaintenanceLine::class, 'order_id');
    }
}
