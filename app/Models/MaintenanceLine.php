<?php

namespace App\Models;
use App\Traits\TracksModelChanges;

use Illuminate\Database\Eloquent\SoftDeletes;

use App\Traits\PlantScoping;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Support\Facades\Auth;

class MaintenanceLine extends Model
{
    use SoftDeletes, HasFactory, PlantScoping, TracksModelChanges;

    protected $table = 'mm_machine_maintanence_lines';

    public const CREATED_AT = 'created';
    public const UPDATED_AT = 'modified';

    protected $fillable = [
        'name',
        'product_quantity',
        'date_planned',
        'product_uom',
        'product_id',
        'description',
        'price_unit',
        'price_subtotal',
        'price_total',
        'tax_id',
        'price_tax',
        'order_id',
        'plant_id',
        'status',
        'priority',
        'invoiced_quantity',
        'received_quantity',
        'received_price',
        'partner_id',
        'created_by',
        'modified_by',
        'deleted_by',
    ];

    protected $casts = [
        'date_planned' => 'datetime',
        'created' => 'datetime',
        'modified' => 'datetime',
        'price_unit' => 'decimal:2',
        'price_subtotal' => 'decimal:2',
        'price_total' => 'decimal:2',
        'price_tax' => 'decimal:2',
        'received_price' => 'decimal:2',
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

    public function request()
    {
        return $this->belongsTo(MaintenanceRequest::class, 'order_id');
    }

    public function product()
    {
        return $this->belongsTo(Product::class, 'product_id');
    }

    public function uom()
    {
        return $this->belongsTo(ProductUnit::class, 'product_uom');
    }

    public function tax()
    {
        return $this->belongsTo(Tax::class, 'tax_id');
    }

    public function partner()
    {
        return $this->belongsTo(Patron::class, 'partner_id');
    }
}
