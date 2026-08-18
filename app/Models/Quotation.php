<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Support\Facades\DB;
use App\Traits\PlantScoping;
use App\Traits\TracksModelChanges;

class Quotation extends Model
{
        use HasFactory, SoftDeletes, PlantScoping, TracksModelChanges;
    protected $table = 'mm_quotations';

    protected $appends = [
        'is_in_use',
        'encrypted_id',
    ];

    protected $fillable = [
        'plant_id',
        'prefix',
        'reference',
        'patron_id',
        'notes',
        'site_id',
        'sales_executive_id',
        'is_tax_inclusive',
        'quote_date',
        'validity_date',
        'tax_id',
        'amount_untaxed',
        'amount_tax',
        'adjustment',
        'amount_total',
        'status',
        'is_customer_po',
        'created_by',
        'updated_by',
        'deleted_by',
    ];

    protected $casts = [
        'is_customer_po' => 'integer',
        'sales_executive_id' => 'integer',
        'is_tax_inclusive' => 'boolean',
        'pump_rate' => 'decimal:2',
        'amount_untaxed' => 'decimal:2',
        'amount_tax' => 'decimal:2',
        'adjustment' => 'decimal:2',
        'amount_total' => 'decimal:2',
        'quote_date' => 'date:Y-m-d',
        'validity_date' => 'date:Y-m-d',
    ];

    // Statuses
    const STATUS_DRAFT = 0;
    const STATUS_SENT = 1;
    const STATUS_ACCEPTED = 2;
    const STATUS_REJECTED = 3;

    public function items()
    {
        return $this->hasMany(QuotationItem::class);
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function modifier()
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    /**
     * Fat Model: Handles creation of quotation along with its items.
     */
    public static function createWithItems(array $data, $plantId)
    {
        return DB::transaction(function () use ($data, $plantId) {
            $quotationData = collect($data)->except(['items', 'new_site_name'])->toArray();
            $quotationData['plant_id'] = $plantId ?: 1;

            // Handle Dynamic Site Creation
            if (!empty($data['new_site_name'])) {
                $site = \App\Models\Site::create([
                    'name' => $data['new_site_name'],
                    'plant_id' => $quotationData['plant_id'],
                    'type' => 'unloading',
                    'status' => 'Active',
                    'is_active' => true,
                    'created_by' => auth()->id()
                ]);
                $quotationData['site_id'] = $site->id;
            }

            if (empty($quotationData['reference'])) {
                $quotationData['reference'] = self::generateReference($quotationData['plant_id']);
            }
            $quotationData['quote_date'] = \Carbon\Carbon::parse($quotationData['quote_date'])->toDateString();
            
            if (isset($quotationData['validity_date'])) {
                $quotationData['validity_date'] = \Carbon\Carbon::parse($quotationData['validity_date'])->toDateString();
            }

            $quotation = self::create($quotationData);

            foreach ($data['items'] as $itemData) {
                $pumpRates = $itemData['pump_rates'] ?? [];
                $itemPayload = collect($itemData)->except('pump_rates')->toArray();

                if (empty($itemPayload['concrete_pump']) && !empty($pumpRates[0]['concrete_pump'])) {
                    $itemPayload['concrete_pump'] = $pumpRates[0]['concrete_pump'];
                }
                if (!isset($itemPayload['pump_rate']) && isset($pumpRates[0]['pump_rate'])) {
                    $itemPayload['pump_rate'] = $pumpRates[0]['pump_rate'];
                }

                $item = $quotation->items()->create($itemPayload);
                if (!empty($pumpRates)) {
                    $item->syncPumpRates($pumpRates);
                }
            }

            $quotation->updateTotals();
            return $quotation;
        });
    }

    /**
     * Fat Model: Handles update of quotation and its items.
     */
    public function updateWithItems(array $data)
    {
        return DB::transaction(function () use ($data) {
            $quotationData = collect($data)->except('items')->toArray();
            $quotationData['quote_date'] = \Carbon\Carbon::parse($quotationData['quote_date'])->toDateString();
            
            if (isset($quotationData['validity_date'])) {
                $quotationData['validity_date'] = \Carbon\Carbon::parse($quotationData['validity_date'])->toDateString();
            }

            $this->update($quotationData);

            $itemIds = collect($data['items'])->pluck('id')->filter()->toArray();
            $staleItems = $this->items()->whereNotIn('id', $itemIds)->get();
            foreach ($staleItems as $staleItem) {
                $staleItem->delete();
            }

            foreach ($data['items'] as $itemData) {
                $pumpRates = $itemData['pump_rates'] ?? [];
                $itemPayload = collect($itemData)->except('pump_rates')->toArray();

                if (empty($itemPayload['concrete_pump']) && !empty($pumpRates[0]['concrete_pump'])) {
                    $itemPayload['concrete_pump'] = $pumpRates[0]['concrete_pump'];
                }
                if (!isset($itemPayload['pump_rate']) && isset($pumpRates[0]['pump_rate'])) {
                    $itemPayload['pump_rate'] = $pumpRates[0]['pump_rate'];
                }

                if (isset($itemData['id'])) {
                    $item = $this->items()->find($itemData['id']);
                    if ($item) {
                        $item->update(collect($itemPayload)->except('id')->toArray());
                    }
                } else {
                    $item = $this->items()->create($itemPayload);
                }

                if ($item) {
                    $item->syncPumpRates($pumpRates);
                }
            }

            $this->updateTotals();
        });
    }

   
    public function tax()
    {
        return $this->belongsTo(Tax::class, 'tax_id');
    }

    public function patron()
    {
        return $this->belongsTo(Patron::class);
    }

    public function plant()
    {
        return $this->belongsTo(Plant::class);
    }

    public function site()
    {
        return $this->belongsTo(Site::class);
    }
    
    public function salesExecutive()
    {
        return $this->belongsTo(Personnel::class, 'sales_executive_id', 'id');
    }

    public function customerPOs()
    {
        return $this->hasMany(CustomerPO::class, 'quotation_id');
    }

    // Business Logic
    public static function generateReference($plantId)
    {
        $now = now();
        $startYear = $now->month >= 4 ? $now->year : $now->year - 1;
        $fyString = substr($startYear, -2) . substr($startYear + 1, -2);
        
        $customPrefix = 'QT';
        $settings = CustomSetting::getForModule($plantId, 'batching');
        if (!empty($settings['quote_prefix'])) {
            $customPrefix = $settings['quote_prefix'];
        }
        
        $prefixSearch = "{$customPrefix}-{$fyString}-";

        $latest = self::where('plant_id', $plantId)
                      ->where('reference', 'LIKE', "{$prefixSearch}%")
                      ->orderBy('id', 'desc')
                      ->value('reference');
                      
        $sequence = 1;
        if ($latest && preg_match('/(\d{4})$/', $latest, $matches)) {
            $sequence = ((int) $matches[1]) + 1;
        }

        return sprintf('%s-%s-%04d', $customPrefix, $fyString, $sequence);
    }

    public function updateTotals()
    {
        $untaxed = $this->items()->sum('untaxed_amount');
        $taxAmount = $this->items()->sum('tax_amount');
        
        $this->update([
            'amount_untaxed' => $untaxed,
            'amount_tax' => $taxAmount,
            'amount_total' => $untaxed + $taxAmount + ($this->adjustment ?? 0),
        ]);
    }

    public function getIsInUseAttribute(): bool
    {
        return $this->items()->exists();
    }

    public function getEncryptedIdAttribute()
    {
        return encrypt($this->id);
    }

    protected static function booted()
    {
        static::deleting(function ($quotation) {
            foreach ($quotation->items as $item) {
                $item->delete();
            }
        });
    }
}
