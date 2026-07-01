<?php

namespace App\Services\SCM;

use App\Models\Product;
use App\Models\PurchaseOrderHistory;
use App\Models\BatchMaterial;
use App\Models\StockExhaustLine;
use App\Models\Quantity;
use Carbon\Carbon;

class InventoryValuationService
{
    /**
     * Calculate inventory valuation metrics for a plant's products.
     *
     * @param int $plantId
     * @param string $startDate
     * @param string $endDate
     * @param string $method 'FIFO' or 'AVERAGE'
     * @param int|null $productId optional single product filter
     * @return array
     */
    public function calculate(int $plantId, string $startDate, string $endDate, string $method = 'FIFO', ?int $productId = null): array
    {
        $productsQuery = Product::where('plant_id', $plantId)->with(['unit', 'category']);
        
        if ($productId) {
            $productsQuery->where('id', $productId);
        } else {
            // Focus on aggregate/sand/cement categories but support all products if needed
            // Let's filter products to only those with matching categories OR simply load all products.
            // Loading all products is the safest to not miss any custom aggregates.
        }

        $products = $productsQuery->get();
        $summary = [];

        foreach ($products as $product) {
            $productValuation = $this->calculateForProduct($plantId, $product, $startDate, $endDate, $method);
            if ($productValuation) {
                $summary[] = $productValuation;
            }
        }

        return [
            'method' => $method,
            'start_date' => $startDate,
            'end_date' => $endDate,
            'products' => $summary,
        ];
    }

    /**
     * Calculate valuation details for a single product.
     */
    private function calculateForProduct(int $plantId, Product $product, string $startDate, string $endDate, string $method): ?array
    {
        $productId = $product->id;
        $events = [];

        // 1. Load opening record if any
        $firstQtyRecord = Quantity::where('plant_id', $plantId)
            ->where('product_id', $productId)
            ->orderBy('date', 'asc')
            ->first();

        if ($firstQtyRecord && (float)$firstQtyRecord->opening_quantity > 0) {
            $events[] = [
                'type' => 'inward',
                'qty' => (float)$firstQtyRecord->opening_quantity,
                'price' => (float)($product->purchase_price ?? 0.0),
                'time' => Carbon::parse($firstQtyRecord->date)->startOfDay(),
                'doc_no' => 'OPENING-STOCK',
                'ref' => 'Opening Stock Balance',
            ];
        }

        // 2. Fetch Inwards
        $inwards = PurchaseOrderHistory::where('plant_id', $plantId)
            ->where('product_id', $productId)
            ->where('received_qty', '>', 0)
            ->with(['uom', 'order'])
            ->get();

        foreach ($inwards as $inw) {
            $qty = (float)$inw->received_qty;
            if ($inw->uom_id && $product->unit_id && $inw->uom_id != $product->unit_id) {
                $qty = $this->convertQty($qty, $inw->uom, $product->unit, $product);
            }
            
            $events[] = [
                'type' => 'inward',
                'qty' => $qty,
                'price' => (float)$inw->unit_price,
                'time' => Carbon::parse($inw->received_date)->startOfDay(),
                'doc_no' => $inw->inward_no ?? ('INW-' . $inw->id),
                'ref' => $inw->order->po_number ?? 'PO Receipt',
            ];
        }

        // 3. Fetch Batch Consumptions (actual_qty is in kg, matches product unit kg)
        $batchMaterials = BatchMaterial::where('mm_batch_materials.plant_id', $plantId)
            ->where('mm_batch_materials.product_id', $productId)
            ->where('mm_batch_materials.actual_qty', '>', 0)
            ->join('mm_batches', 'mm_batches.id', '=', 'mm_batch_materials.batch_id')
            ->whereNull('mm_batches.deleted_at')
            ->select('mm_batch_materials.*', 'mm_batches.start_time', 'mm_batches.batch_no')
            ->get();

        foreach ($batchMaterials as $bm) {
            $events[] = [
                'type' => 'consumption',
                'qty' => (float)$bm->actual_qty,
                'time' => Carbon::parse($bm->start_time),
                'doc_no' => $bm->batch_no ?? ('BAT-' . $bm->batch_id),
                'ref' => 'PLC Batching',
            ];
        }

        // 4. Fetch Stock Exhaust adjustments
        $stockExhausts = StockExhaustLine::whereHas('stockExhaust', function($q) use ($plantId) {
                $q->where('plant_id', $plantId);
            })
            ->where('product_id', $productId)
            ->where('quantity_issued', '>', 0)
            ->with('stockExhaust')
            ->get();

        foreach ($stockExhausts as $se) {
            $events[] = [
                'type' => 'consumption',
                'qty' => (float)$se->quantity_issued,
                'time' => Carbon::parse($se->issue_date ?? $se->stockExhaust->issued_date ?? $se->created),
                'doc_no' => $se->stockExhaust->name ?? ('SE-' . $se->stock_id),
                'ref' => $se->notes ?? 'Manual Stock Exhaust',
            ];
        }

        // If no events exist for this product, skip it to keep the report clean
        if (empty($events)) {
            return null;
        }

        // Sort chronologically (Inwards first for same timestamp)
        usort($events, function($a, $b) {
            $timeA = $a['time']->timestamp;
            $timeB = $b['time']->timestamp;
            if ($timeA === $timeB) {
                if ($a['type'] === 'inward' && $b['type'] === 'consumption') return -1;
                if ($a['type'] === 'consumption' && $b['type'] === 'inward') return 1;
                return 0;
            }
            return $timeA <=> $timeB;
        });

        // Run simulation
        $startCarbon = Carbon::parse($startDate)->startOfDay();
        $endCarbon = Carbon::parse($endDate)->endOfDay();

        $openingQty = 0.0;
        $openingValue = 0.0;
        
        $inwardQtyPeriod = 0.0;
        $inwardValuePeriod = 0.0;
        
        $consumedQtyPeriod = 0.0;
        $consumedValuePeriod = 0.0;

        $detailedEvents = [];

        if (strtoupper($method) === 'FIFO') {
            $queue = []; // items of ['qty' => float, 'price' => float]
            $currentQty = 0.0;
            $currentVal = 0.0;

            foreach ($events as $event) {
                $eventTime = $event['time'];
                $isBeforeStart = $eventTime->lt($startCarbon);
                $isAfterEnd = $eventTime->gt($endCarbon);

                if ($isBeforeStart) {
                    if ($event['type'] === 'inward') {
                        $queue[] = ['qty' => $event['qty'], 'price' => $event['price']];
                        $currentQty += $event['qty'];
                        $currentVal += $event['qty'] * $event['price'];
                    } else {
                        // consume
                        $qtyToConsume = $event['qty'];
                        while ($qtyToConsume > 0 && count($queue) > 0) {
                            $first = &$queue[0];
                            if ($first['qty'] > $qtyToConsume) {
                                $first['qty'] -= $qtyToConsume;
                                $currentQty -= $qtyToConsume;
                                $currentVal -= $qtyToConsume * $first['price'];
                                $qtyToConsume = 0;
                            } else {
                                $qtyToConsume -= $first['qty'];
                                $currentQty -= $first['qty'];
                                $currentVal -= $first['qty'] * $first['price'];
                                array_shift($queue);
                            }
                        }
                        if ($qtyToConsume > 0) {
                            $fallbackPrice = (float)($product->purchase_price ?? 0.0);
                            $currentQty -= $qtyToConsume;
                            $currentVal -= $qtyToConsume * $fallbackPrice;
                        }
                    }
                } else if (!$isAfterEnd) {
                    if (empty($detailedEvents)) {
                        $openingQty = $currentQty;
                        $openingValue = $currentVal;
                    }

                    $cogs = 0.0;
                    if ($event['type'] === 'inward') {
                        $queue[] = ['qty' => $event['qty'], 'price' => $event['price']];
                        $currentQty += $event['qty'];
                        $currentVal += $event['qty'] * $event['price'];
                        
                        $inwardQtyPeriod += $event['qty'];
                        $inwardValuePeriod += $event['qty'] * $event['price'];
                    } else {
                        $qtyToConsume = $event['qty'];
                        $originalConsume = $qtyToConsume;
                        while ($qtyToConsume > 0 && count($queue) > 0) {
                            $first = &$queue[0];
                            if ($first['qty'] > $qtyToConsume) {
                                $cogs += $qtyToConsume * $first['price'];
                                $first['qty'] -= $qtyToConsume;
                                $currentQty -= $qtyToConsume;
                                $currentVal -= $qtyToConsume * $first['price'];
                                $qtyToConsume = 0;
                            } else {
                                $cogs += $first['qty'] * $first['price'];
                                $qtyToConsume -= $first['qty'];
                                $currentQty -= $first['qty'];
                                $currentVal -= $first['qty'] * $first['price'];
                                array_shift($queue);
                            }
                        }
                        if ($qtyToConsume > 0) {
                            $fallbackPrice = (float)($product->purchase_price ?? 0.0);
                            $cogs += $qtyToConsume * $fallbackPrice;
                            $currentQty -= $qtyToConsume;
                            $currentVal -= $qtyToConsume * $fallbackPrice;
                        }
                        $consumedQtyPeriod += $originalConsume;
                        $consumedValuePeriod += $cogs;
                    }

                    $detailedEvents[] = [
                        'date' => $event['time']->toDateString(),
                        'type' => $event['type'],
                        'doc_no' => $event['doc_no'],
                        'ref' => $event['ref'],
                        'qty' => $event['qty'],
                        'price' => $event['type'] === 'inward' ? $event['price'] : ($originalConsume > 0 ? $cogs / $originalConsume : 0),
                        'value' => $event['type'] === 'inward' ? ($event['qty'] * $event['price']) : $cogs,
                        'running_qty' => $currentQty,
                        'running_val' => $currentVal,
                    ];
                } else {
                    if (empty($detailedEvents)) {
                        $openingQty = $currentQty;
                        $openingValue = $currentVal;
                    }
                    if ($event['type'] === 'inward') {
                        $currentQty += $event['qty'];
                        $currentVal += $event['qty'] * $event['price'];
                    } else {
                        $qtyToConsume = $event['qty'];
                        while ($qtyToConsume > 0 && count($queue) > 0) {
                            $first = &$queue[0];
                            if ($first['qty'] > $qtyToConsume) {
                                $first['qty'] -= $qtyToConsume;
                                $currentQty -= $qtyToConsume;
                                $currentVal -= $qtyToConsume * $first['price'];
                                $qtyToConsume = 0;
                            } else {
                                $qtyToConsume -= $first['qty'];
                                $currentQty -= $first['qty'];
                                $currentVal -= $first['qty'] * $first['price'];
                                array_shift($queue);
                            }
                        }
                        if ($qtyToConsume > 0) {
                            $fallbackPrice = (float)($product->purchase_price ?? 0.0);
                            $currentQty -= $qtyToConsume;
                            $currentVal -= $qtyToConsume * $fallbackPrice;
                        }
                    }
                }
            }
        } else {
            // AVERAGE (Weighted Average method)
            $currentQty = 0.0;
            $currentVal = 0.0;
            $avgPrice = (float)($product->purchase_price ?? 0.0);

            foreach ($events as $event) {
                $eventTime = $event['time'];
                $isBeforeStart = $eventTime->lt($startCarbon);
                $isAfterEnd = $eventTime->gt($endCarbon);

                if ($isBeforeStart) {
                    if ($event['type'] === 'inward') {
                        $currentQty += $event['qty'];
                        $currentVal += $event['qty'] * $event['price'];
                        if ($currentQty > 0) {
                            $avgPrice = $currentVal / $currentQty;
                        }
                    } else {
                        $currentQty -= $event['qty'];
                        $currentVal = $currentQty * $avgPrice;
                    }
                } else if (!$isAfterEnd) {
                    if (empty($detailedEvents)) {
                        $openingQty = $currentQty;
                        $openingValue = $currentVal;
                    }

                    $cogs = 0.0;
                    if ($event['type'] === 'inward') {
                        $currentQty += $event['qty'];
                        $currentVal += $event['qty'] * $event['price'];
                        if ($currentQty > 0) {
                            $avgPrice = $currentVal / $currentQty;
                        }
                        $inwardQtyPeriod += $event['qty'];
                        $inwardValuePeriod += $event['qty'] * $event['price'];
                    } else {
                        $cogs = $event['qty'] * $avgPrice;
                        $currentQty -= $event['qty'];
                        $currentVal = $currentQty * $avgPrice;
                        
                        $consumedQtyPeriod += $event['qty'];
                        $consumedValuePeriod += $cogs;
                    }

                    $detailedEvents[] = [
                        'date' => $event['time']->toDateString(),
                        'type' => $event['type'],
                        'doc_no' => $event['doc_no'],
                        'ref' => $event['ref'],
                        'qty' => $event['qty'],
                        'price' => $event['type'] === 'inward' ? $event['price'] : $avgPrice,
                        'value' => $event['type'] === 'inward' ? ($event['qty'] * $event['price']) : $cogs,
                        'running_qty' => $currentQty,
                        'running_val' => $currentVal,
                    ];
                } else {
                    if (empty($detailedEvents)) {
                        $openingQty = $currentQty;
                        $openingValue = $currentVal;
                    }
                    if ($event['type'] === 'inward') {
                        $currentQty += $event['qty'];
                        $currentVal += $event['qty'] * $event['price'];
                        if ($currentQty > 0) {
                            $avgPrice = $currentVal / $currentQty;
                        }
                    } else {
                        $currentQty -= $event['qty'];
                        $currentVal = $currentQty * $avgPrice;
                    }
                }
            }
        }

        if (empty($detailedEvents)) {
            $openingQty = $currentQty;
            $openingValue = $currentVal;
        }
        $endingQty = $currentQty;
        $endingValue = $currentVal;

        return [
            'product_id' => $product->id,
            'product_name' => $product->title,
            'uom' => $product->unit->unit_code ?? 'KGS',
            'category' => $product->category->name ?? 'Uncategorized',
            'opening_qty' => round($openingQty, 2),
            'opening_value' => round($openingValue, 2),
            'inward_qty' => round($inwardQtyPeriod, 2),
            'inward_value' => round($inwardValuePeriod, 2),
            'consumed_qty' => round($consumedQtyPeriod, 2),
            'consumed_value' => round($consumedValuePeriod, 2),
            'ending_qty' => round($endingQty, 2),
            'ending_value' => round($endingValue, 2),
            'avg_unit_cost' => $endingQty > 0 ? round($endingValue / $endingQty, 2) : round((float)($product->purchase_price ?? 0.0), 2),
            'detailed_events' => $detailedEvents,
        ];
    }

    /**
     * Convert quantities between units.
     */
    private function convertQty(float $qty, ?ProductUnit $fromUom, ?ProductUnit $toUom, Product $product): float
    {
        if (!$fromUom || !$toUom || $fromUom->id == $toUom->id) {
            return $qty;
        }

        $fromCode = strtoupper($fromUom->unit_code);
        $toCode = strtoupper($toUom->unit_code);

        // MT/TON to KG conversion
        if (in_array($fromCode, ['MT', 'TON', 'TONS']) && in_array($toCode, ['KG', 'KGS'])) {
            $ratio = $product->conversion_quantity > 0 ? (float)$product->conversion_quantity : 1000.0;
            return $qty * $ratio;
        }

        // KG to MT/TON conversion
        if (in_array($fromCode, ['KG', 'KGS']) && in_array($toCode, ['MT', 'TON', 'TONS'])) {
            $ratio = $product->conversion_quantity > 0 ? (float)$product->conversion_quantity : 1000.0;
            return $qty / $ratio;
        }

        return $qty;
    }
}