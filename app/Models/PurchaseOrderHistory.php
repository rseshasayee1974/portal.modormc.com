<?php

namespace App\Models;

use App\Traits\TracksModelChanges;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
class PurchaseOrderHistory extends Model
{
        use HasFactory, SoftDeletes , TracksModelChanges;

    protected $table = 'mm_purchase_order_history';

    protected $fillable = [
        'plant_id', 'order_id', 'order_item_id', 'received_date',
        'product_id', 'uom_id', 'used_quantity', 'received_qty',
        'conversion_quantity', 'conversion_uom_id',
        'convert_volume',
        'unit_price', 'count_quantity', 'inward_no', 'status',
        'truck_id', 'truck_loaded', 'truck_empty', 'attachments',
        'created_by', 'updated_by', 'deleted_by'
    ];

    protected $casts = [
        'attachments' => 'array',
        'conversion_quantity' => 'decimal:4',
        'convert_volume' => 'decimal:6',
    ];

    public function truck()
    {
        return $this->belongsTo(Machine::class, 'truck_id');
    }

    public function order()
    {
        return $this->belongsTo(PurchaseOrder::class, 'order_id');
    }

    public function item()
    {
        return $this->belongsTo(PurchaseOrderItem::class, 'order_item_id');
    }

    public function product()
    {
        return $this->belongsTo(Product::class, 'product_id');
    }

    public function uom()
    {
        return $this->belongsTo(ProductUnit::class, 'uom_id');
    }

    public function conversionUom()
    {
        return $this->belongsTo(ProductUnit::class, 'conversion_uom_id');
    }

    public function loadedWeightImage()
    {
        return $this->hasOne(Image::class, 'ref_no', 'id')
            ->where('category', 'Inward')
            ->where('image_name', 'loaded_weight_snap');
    }

    public function emptyWeightImage()
    {
        return $this->hasOne(Image::class, 'ref_no', 'id')
            ->where('category', 'Inward')
            ->where('image_name', 'empty_weight_snap');
    }

    public static function generateNextInwardNo($plantId, $date = null)
    {
        $settings = CustomSetting::getForModule($plantId, 'batching');
        $year = Payment::getFinancialYearString($date ?? now());
        $compactYear = str_replace('-', '', $year);
        $pattern = '~\AINW/(?:(?:' . preg_quote($year, '~') . '|' . $compactYear
            . ')/([0-9]+)|([0-9]+)/' . preg_quote($year, '~') . ')\z~i';
        $orders = self::withTrashed()->where('plant_id', $plantId)->select('inward_no')
            ->where(function ($query) use ($year, $compactYear) {
                $query->where('inward_no', 'like', 'INW/' . $year . '/%')
                    ->orWhere('inward_no', 'like', 'INW/' . $compactYear . '/%')
                    ->orWhere('inward_no', 'like', 'INW/%/' . $year);
            });
        $highest = 0;
        foreach ($orders->toBase()->cursor() as $order) {
            if (preg_match($pattern, (string) $order->inward_no, $matches, PREG_UNMATCHED_AS_NULL)) {
                $highest = max($highest, (int) ($matches[1] ?? $matches[2]));
            }
        }
        $number = str_pad((string) ($highest + 1), max(0, min(10, (int) ($settings['inward_number_padding'] ?? 0))), '0', STR_PAD_LEFT);
        $format = $settings['inward_number_format'] ?? 'prefix_fy_number';
        if ($format === 'prefix_number_fy') {
            return 'INW/' . $number . '/' . $year;
        }
        return 'INW/' . ($format === 'prefix_compact_fy_number' ? $compactYear : $year) . '/' . $number;
    }
}
