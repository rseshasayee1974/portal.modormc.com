<?php

namespace App\Http\Controllers;

use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderHistory;
use App\Models\PurchaseOrderItem;
use App\Models\ProductUnit;
use App\Models\Quantity;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use App\Http\Controllers\Concerns\AuthorizesModule;

class PurchaseOrderInwardController extends Controller
{
    use AuthorizesModule;
    protected string $module = 'purchase_orders'; // Reusing PO permissions for now or could be 'inwards'

    public function index()
    {
        $this->authorizeModule('menu');
        $allowedPlantIds = $this->allowedPlantIds();

        $inwards = PurchaseOrderHistory::whereIn('plant_id', $allowedPlantIds)
            ->with([
                'order', 
                'product', 
                'uom', 
                'item',
                'order.vendor',
                'order.items.product',
                'order.items.uom',
                'order.items.tax',
                'order.items.history',
                'order.items.history.uom',
                'truck'
            ])
            ->latest()
            ->get();
        $allowedPlantIds = $this->allowedPlantIds();
        $purchase_order_list = PurchaseOrder::whereIn('plant_id', $allowedPlantIds)
            ->where('receipt_status', '<', 2)
            ->where('state', '=', 'approved')
            ->with(['vendor', 'items.product', 'items.uom'])
            ->latest()
            ->get();

        return Inertia::render('PurchaseOrders/Inwards/Index', [
            'inwards' => $inwards,
            'purchaseOrders' => $purchase_order_list,
            'vehicles' => toSelectOptions(VehiclesDropdown($allowedPlantIds), 'registration')
        ]);
    }

    public function create(PurchaseOrder $purchase_order = null)
    {
        $this->authorizeModule('create');
        $allowedPlantIds = $this->allowedPlantIds();

        if ($purchase_order) {
            $this->authorizePlantAccess($purchase_order);
            $purchase_order->load(['items.product', 'items.uom', 'items.tax', 'items.history', 'vendor']);
        }

        $purchaseOrders = PurchaseOrder::whereIn('plant_id', $allowedPlantIds)
            ->where('receipt_status', '<', 2) // Not fully received
            ->where('state','=','approved')
            ->with(['vendor', 'items.product', 'items.uom'])
            ->latest()
            ->get();

        return Inertia::render('PurchaseOrders/Inwards/Create', [
            'purchase_order' => $purchase_order,
            'purchaseOrders' => $purchaseOrders,
        ]);
    }

    public function store(Request $request)
    {
        $this->authorizeModule('create');
        
        $validated = $request->validate([
            'order_id' => 'required|exists:mm_purchase_orders,id',
            'received_date' => 'required|date',
            'inward_no' => 'nullable|string|max:250',
            'truck_id' => 'nullable|exists:mm_machines,id',
            'truck_loaded' => 'nullable|numeric|min:0',
            'items' => 'required|array|min:1',
            'items.*.order_item_id' => 'required|exists:mm_purchase_order_items,id',
            'items.*.received_qty' => 'required|numeric|min:0',
            'items.*.truck_id' => 'nullable|exists:mm_machines,id',
            'items.*.truck_loaded' => 'nullable|numeric|min:0',
        ]);

        $order = PurchaseOrder::findOrFail($validated['order_id']);
        $this->authorizePlantAccess($order);

        // Ensure at least one item has a received quantity > 0 OR we are recording initial truck weight
        $hasTotalReceived = collect($validated['items'])->contains(fn($item) => (float)$item['received_qty'] > 0);
        $hasTruckWeight = !empty($validated['truck_loaded']) || collect($validated['items'])->contains(fn($item) => !empty($item['truck_loaded']));

        if (!$hasTotalReceived && !$hasTruckWeight) {
            return back()->withErrors(['items' => 'At least one item must have a received quantity greater than 0, or truck weight must be recorded.']);
        }

        DB::transaction(function () use ($validated, $order) {
            $userId = Auth::id();
            foreach ($validated['items'] as $itemData) {
                // Item truck data or fallback to master truck data
                $itemTruckId = $itemData['truck_id'] ?? $validated['truck_id'] ?? null;
                $itemTruckLoaded = $itemData['truck_loaded'] ?? $validated['truck_loaded'] ?? null;

                if ($itemData['received_qty'] <= 0 && empty($itemTruckLoaded)) continue;

                $item = PurchaseOrderItem::findOrFail($itemData['order_item_id']);
                
                $remaining = max(0, (float) $item->product_quantity - (float) $item->received_quantity);
                $acceptedQty = min((float)$itemData['received_qty'], $remaining);

                $entryDate = \Carbon\Carbon::parse($validated['received_date'])->toDateString();
                $newReceivedQty = (float) $item->received_quantity + $acceptedQty;

                $history = PurchaseOrderHistory::create([
                    'plant_id' => $order->plant_id,
                    'order_id' => $order->id,
                    'order_item_id' => $item->id,
                    'received_date' => $entryDate,
                    'product_id' => $item->product_id,
                    'uom_id' => $item->product_uom,
                    'used_quantity' => $newReceivedQty,
                    'received_qty' => $acceptedQty,
                    'unit_price' => $item->unit_price,
                    'inward_no' => $validated['inward_no'] ?: PurchaseOrderHistory::generateNextInwardNo($order->plant_id, $entryDate),
                    'truck_id' => $itemTruckId,
                    'truck_loaded' => $itemTruckLoaded,
                    'status' => 1,
                    'created_by' => $userId,
                    'updated_by' => $userId,
                ]);

                if ($acceptedQty > 0) {
                    $item->received_quantity = $newReceivedQty;
                    $item->updated_by = $userId;
                    $item->save();

                    // Update or Create Quantity record (Stock Balance)
                    $quantityRecord = Quantity::firstOrNew([
                        'plant_id' => $order->plant_id,
                        'product_id' => $item->product_id,
                        'uom_id' => $item->product_uom,
                        'date' => $entryDate,
                        'is_warehouse' => true,
                    ]);

                    if (!$quantityRecord->exists) {
                        $quantityRecord->opening_quantity = 0;
                        $quantityRecord->created_by = $userId;
                        $quantityRecord->status = 1;
                    }

                    $quantityRecord->quantity = (float)$quantityRecord->quantity + $acceptedQty;
                    $quantityRecord->date = $entryDate;
                    $quantityRecord->updated_by = $userId;
                    $quantityRecord->save();
                }
            }

            $order->recalculateTotals();
            // refreshReceiptStatus is protected, I might need to make it public or just redo logic
            $this->refreshOrderReceiptStatus($order);
        });

        return redirect()->route('inwards.index')->with('success', 'Inward recorded successfully.');
    }

    public function destroy(PurchaseOrderHistory $inward)
    {
        $this->authorizeModule('delete');
        $this->authorizePlantAccess($inward->order);

        DB::transaction(function () use ($inward) {
            $userId = Auth::id();
            $item = $inward->item;
            
            // 1. Decrement received quantity from PO item
            if ($item) {
                $item->received_quantity = max(0, (float)$item->received_quantity - (float)$inward->received_qty);
                $item->updated_by = $userId;
                $item->save();
            }

            // 2. Decrement stock balance from Quantity table
            $stock = Quantity::where([
                'plant_id' => $inward->plant_id,
                'product_id' => $inward->product_id,
                'uom_id' => $inward->uom_id,
                'date' => $inward->received_date,
                'is_warehouse' => true,
            ])->first();

            if ($stock) {
                $stock->quantity = max(0, (float)$stock->quantity - (float)$inward->received_qty);
                $stock->updated_by = $userId;
                $stock->save();
            }

            // 3. Delete the history record
            $inward->delete();

            // 4. Update order status/totals
            $order = $inward->order;
            $order->recalculateTotals();
            $this->refreshOrderReceiptStatus($order);
        });

        return redirect()->back()->with('success', 'Inward record deleted and stock adjusted.');
    }

    public function updateWeight(Request $request, PurchaseOrderHistory $inward)
    {
        $this->authorizeModule('edit');
        $this->authorizePlantAccess($inward->order);

        $validated = $request->validate([
            'truck_empty' => 'required|numeric|min:0',
        ]);

        DB::transaction(function () use ($validated, $inward) {
            $userId = Auth::id();
            $oldReceivedQty = (float)$inward->received_qty;
            $newReceivedQty = max(0, (float)$inward->truck_loaded - (float)$validated['truck_empty']);
            
            $diff = $newReceivedQty - $oldReceivedQty;

            // Update history record
            $inward->truck_empty = $validated['truck_empty'];
            $inward->received_qty = $newReceivedQty;
            $inward->updated_by = $userId;
            $inward->save();

            // Update item total received
            $item = $inward->item;
            if ($item) {
                $item->received_quantity = (float)$item->received_quantity + $diff;
                $item->updated_by = $userId;
                $item->save();
            }

            // Update Stock Balance
            $quantityRecord = Quantity::firstOrNew([
                'plant_id' => $inward->plant_id,
                'product_id' => $inward->product_id,
                'uom_id' => $inward->uom_id,
                'date' => $inward->received_date,
                'is_warehouse' => true,
            ]);

            if (!$quantityRecord->exists) {
                $quantityRecord->opening_quantity = 0;
                $quantityRecord->created_by = $userId;
                $quantityRecord->status = 1;
            }

            $quantityRecord->quantity = (float)$quantityRecord->quantity + $diff;
            $quantityRecord->updated_by = $userId;
            $quantityRecord->save();

            // Recalculate Order
            $order = $inward->order;
            $order->recalculateTotals();
            $this->refreshOrderReceiptStatus($order);
        });

        return redirect()->back()->with('success', 'Net weight calculated and stock updated.');
    }

    protected function refreshOrderReceiptStatus(PurchaseOrder $order)
    {
        $totals = $order->items()
            ->selectRaw('COALESCE(SUM(product_quantity), 0) as ordered_qty, COALESCE(SUM(received_quantity), 0) as received_qty')
            ->first();

        $orderedQty = (float) ($totals->ordered_qty ?? 0);
        $receivedQty = (float) ($totals->received_qty ?? 0);

        $status = 0; // none
        if ($receivedQty > 0 && $orderedQty > 0) {
            $status = $receivedQty >= $orderedQty ? 2 : 1; // 1: partial, 2: full
        }

        if ((int) $order->receipt_status !== $status) {
            $order->receipt_status = $status;
            $order->save();
        }
    }

    protected function allowedPlantIds(): array
    {
        $plantIds = Auth::user()
            ->entityUsers()
            ->pluck('plant_id')
            ->map(fn ($id) => (int) $id)
            ->filter()
            ->values()
            ->toArray();

        $activePlantId = (int) session('active_plant_id');
        if ($activePlantId > 0 && !in_array($activePlantId, $plantIds, true)) {
            $plantIds[] = $activePlantId;
        }

        return $plantIds;
    }

    protected function authorizePlantAccess(PurchaseOrder $purchaseOrder): void
    {
        abort_unless(in_array((int) $purchaseOrder->plant_id, $this->allowedPlantIds(), true), 403);
    }
}
