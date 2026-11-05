<?php

namespace App\Models;

use App\Traits\LogsPushNotifications;
use App\Traits\TracksModelChanges;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class CustomerPO extends Model
{
    use HasFactory, LogsPushNotifications, SoftDeletes, TracksModelChanges;

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
        'attachments',
        'sales_executive_id',
        'is_tax_inclusive',
        'amount_untaxed',
        'amount_tax',
        'amount_total',
        'order_date',
        'due_date',
        'concrete_pump',
        'pump_rate',
        'adjustment',
        'is_sales_order',
        'status',
        'converted_by_user_id',
        'created_by',
        'updated_by',
        'deleted_by',
    ];

    protected $casts = [
        'attachments' => 'array',
        'status' => 'integer',
        'patron_id' => 'integer',
        'site_id' => 'integer',
        'order_date' => 'date',
        'due_date' => 'date:Y-m-d',
        'pump_rate' => 'decimal:2',
        'adjustment' => 'decimal:2',
        'is_sales_order' => 'integer',
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
        return $value !== null ? round((float) $value, 2) : round((float) $this->items->sum('untaxed_amount'), 2);
    }

    public function getAmountTaxAttribute($value)
    {
        return $value !== null ? round((float) $value, 2) : round((float) $this->items->sum('tax_amount'), 2);
    }

    public function getAmountTotalAttribute($value)
    {
        return $value !== null ? round((float) $value, 2) : round((float) $this->items->sum('amount_total') + (float) ($this->adjustment ?? 0), 2);
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
            'amount_untaxed' => round((float) $untaxed, 2),
            'amount_tax' => round((float) $taxAmount, 2),
            'amount_total' => round((float) $amountTotal + (float) ($this->adjustment ?? 0), 2),
        ]);
    }

    const STATUS_DRAFT = 0;

    const STATUS_CONFIRMED = 1;

    const STATUS_COMPLETED = 2;

    public static function statusOptions(): array
    {
        return [
            ['label' => 'Draft', 'value' => self::STATUS_DRAFT],
            ['label' => 'Confirmed', 'value' => self::STATUS_CONFIRMED],
            ['label' => 'Completed', 'value' => self::STATUS_COMPLETED],
        ];
    }

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
        // Dispatch history still protects a PO when its sales order was soft-deleted.
        return $this->hasManyThrough(Dispatch::class, SalesOrder::class, 'customer_po_id', 'sales_order_id')
            ->withTrashedParents();
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
        $fyString = substr($startYear, -2) . '-' . substr($startYear + 1, -2);
        $settings = CustomSetting::getForModule($plantId, 'batching');

        if (empty($customPrefix)) {
            $customPrefix = 'CPO';
            if (! empty($settings['cpo_prefix'])) {
                $customPrefix = $settings['cpo_prefix'];
            }
        }

        $basePrefix = rtrim($customPrefix, '/') . '/';
        $compactYear = str_replace('-', '', $fyString);
        $patternPrefix = strtr($basePrefix, ['!' => '!!', '%' => '!%', '_' => '!_']);
        $orders = self::withTrashed()->where('plant_id', $plantId)->select('reference')
            ->where(function ($query) use ($patternPrefix, $fyString, $compactYear) {
                $query->whereRaw("reference LIKE ? ESCAPE '!'", [$patternPrefix . $fyString . '/%'])
                    ->orWhereRaw("reference LIKE ? ESCAPE '!'", [$patternPrefix . $compactYear . '/%'])
                    ->orWhereRaw("reference LIKE ? ESCAPE '!'", [$patternPrefix . '%/' . $fyString]);
            });
        $pattern = '~\A' . preg_quote($basePrefix, '~') . '(?:(?:' . preg_quote($fyString, '~') . '|' . $compactYear
            . ')/([0-9]+)|([0-9]+)/' . preg_quote($fyString, '~') . ')\z~i';
        $highest = 0;
        foreach ($orders->toBase()->cursor() as $order) {
            if (preg_match($pattern, (string) $order->reference, $matches, PREG_UNMATCHED_AS_NULL)) {
                $highest = max($highest, (int) ($matches[1] ?? $matches[2]));
            }
        }
        $sequence = str_pad((string) ($highest + 1), max(0, min(10, (int) ($settings['cpo_number_padding'] ?? 0))), '0', STR_PAD_LEFT);
        $format = $settings['cpo_number_format'] ?? 'prefix_fy_number';
        $yearAfterNumber = $format === 'prefix_number_fy';
        $prefix = $yearAfterNumber ? $basePrefix : $basePrefix . ($format === 'prefix_compact_fy_number' ? $compactYear : $fyString) . '/';

        return [
            'prefix' => $prefix,
            'reference' => $prefix . $sequence . ($yearAfterNumber ? '/' . $fyString : ''),
        ];
    }
}
