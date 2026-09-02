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
        'customer_po_reference',
        'quotation_id',
        'patron_id',
        'site_id',
        'notes',
        'sales_executive_id',
        'is_tax_inclusive',
        'amount_untaxed',
        'amount_tax',
        'amount_total',
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
        'amount_untaxed' => 'decimal:2',
        'amount_tax' => 'decimal:2',
        'amount_total' => 'decimal:2',
    ];

    protected $appends = ['has_salesorders', 'amount_untaxed', 'amount_tax', 'amount_total'];

    protected $hidden = [
        'created_by',
        'updated_by',
        'deleted_by',
        'deleted_at',
    ];

    public function getHasSalesordersAttribute()
    {
        return $this->salesOrders()->exists();
    }

    public function getAmountUntaxedAttribute($value)
    {
        return $value !== null ? round((float)$value, 2) : round((float)$this->items->sum('untaxed_amount'), 2);
    }

    public function getAmountTaxAttribute($value)
    {
        return $value !== null ? round((float)$value, 2) : round((float)$this->items->sum('tax_amount'), 2);
    }

    public function getAmountTotalAttribute($value)
    {
        return $value !== null ? round((float)$value, 2) : round((float)$this->items->sum('amount_total'), 2);
    }

    public function updateTotals()
    {
        $untaxed = $this->items()->sum('untaxed_amount');
        $taxAmount = $this->items()->sum('tax_amount');
        $amountTotal = $this->items()->sum('amount_total');

        if ($amountTotal == 0 && $this->quotation) {
            $untaxed = $this->quotation->items()->sum('untaxed_amount');
            $taxAmount = $this->quotation->items()->sum('tax_amount');
            $amountTotal = $this->quotation->items()->sum('amount_total');
        }

        $this->update([
            'amount_untaxed' => round((float)$untaxed, 2),
            'amount_tax' => round((float)$taxAmount, 2),
            'amount_total' => round((float)$amountTotal, 2),
        ]);
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

    protected static function booted()
    {
        static::deleting(function ($customerPO) {
            if (auth()->check()) {
                $customerPO->deleted_by = auth()->id();
                $customerPO->saveQuietly();
            }
            foreach ($customerPO->items as $item) {
                if (auth()->check()) {
                    $item->deleted_by = auth()->id();
                    $item->saveQuietly();
                }
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
