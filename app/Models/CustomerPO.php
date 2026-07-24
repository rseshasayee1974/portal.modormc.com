<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use App\Traits\TracksModelChanges;

class CustomerPO extends Model
{
        use HasFactory, SoftDeletes, TracksModelChanges;

    /** Override auto-derived module name (Str::snake('CustomerPO') produces 'customer_p_o') */
    public static string $permissionModule = 'customer_po';

    protected $table = 'mm_customer_pos';

    protected $fillable = [
        'plant_id',
        'prefix',
        'reference',
        'quotation_id',
        'patron_id',
        'site_id',
        'notes',
        'sales_executive_id',
        'concrete_pump',
        'is_tax_inclusive',
        'order_date',
        'status',
        'converted_by_user_id',
        'created_by',
        'updated_by',
        'deleted_by',
    ];

    protected $casts = [
        'order_date' => 'date',
        'sales_executive_id' => 'integer',
        'is_tax_inclusive' => 'boolean',
    ];

    protected $appends = ['has_salesorders', 'amount_untaxed', 'amount_tax', 'amount_total'];

    public function getHasSalesordersAttribute()
    {
        return $this->salesOrders()->exists();
    }

    public function getAmountUntaxedAttribute()
    {
        return round((float)$this->items->sum('untaxed_amount'), 2);
    }

    public function getAmountTaxAttribute()
    {
        return round((float)$this->items->sum('tax_amount'), 2);
    }

    public function getAmountTotalAttribute()
    {
        return round((float)$this->items->sum('amount_total'), 2);
    }

    const STATUS_DRAFT = 0;
    const STATUS_CONFIRMED = 1;
    const STATUS_COMPLETED = 2;

    public function plant()
    {
        return $this->belongsTo(Plant::class);
    }

    public function patron()
    {
        return $this->belongsTo(Patron::class);
    }

    public function site()
    {
        return $this->belongsTo(Site::class);
    }

    public function quotation()
    {
        return $this->belongsTo(Quotation::class);
    }

    public function dispatches()
    {
        return $this->hasMany(Dispatch::class, 'customer_po_id');
    }

    public function converter()
    {
        return $this->belongsTo(User::class, 'converted_by_user_id');
    }

    public function salesExecutive()
    {
        return $this->belongsTo(Personnel::class, 'sales_executive_id', 'id');
    }

    public function salesOrders()
    {
        return $this->hasMany(SalesOrder::class, 'customer_po_id');
    }

    public function items()
    {
        return $this->hasMany(CustomerPOItem::class, 'customer_po_id');
    }
    public function tax()
    {
        return $this->belongsTo(Tax::class, 'tax_id');
    }

    public function concretePump()
    {
        return $this->belongsTo(Machine::class, 'concrete_pump');
    }

    protected static function booted()
    {
        static::deleting(function ($customerPO) {
            foreach ($customerPO->items as $item) {
                $item->delete();
            }
        });
    }

    public static function generateReference($plantId, $customPrefix = null)
    {
        $now = now();
        $startYear = $now->month >= 4 ? $now->year : $now->year - 1;
        $fyString = substr($startYear, -2) . substr($startYear + 1, -2);
        
        if (empty($customPrefix)) {
            $customPrefix = 'CPO';
            $settings = \App\Models\CustomSetting::getForModule($plantId, 'batching');
            if (!empty($settings['cpo_prefix'])) {
                $customPrefix = $settings['cpo_prefix'];
            }
        }
        
        $prefix = "{$customPrefix}-{$fyString}-";

        $latest = self::where('plant_id', $plantId)
                      ->where('prefix', $prefix)
                      ->whereNull('deleted_at')
                      ->orderBy('id', 'desc')
                      ->value('reference');
                      
        $sequence = 1;
        if ($latest && preg_match('/-(\d{4})$/', $latest, $matches)) {
            $sequence = ((int) $matches[1]) + 1;
        }

        return [
            'prefix' => $prefix,
            'reference' => sprintf('%s%04d', $prefix, $sequence)
        ];
    }
}
