<?php

namespace App\Http\Controllers;

use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderHistory;
use App\Models\PurchaseOrderItem;
use App\Models\ProductUnit;
use App\Models\Quantity;
use App\Models\Image;
use App\Models\Plant;
use App\Rules\Base64Image;
use App\Services\PrintDataFormatter;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use App\Http\Controllers\Concerns\AuthorizesModule;

class PurchaseOrderInwardController extends Controller
{
    use AuthorizesModule;
    protected string $module = 'inwards';

    public function index()
    {
        $this->authorizeModule('menu');
        $allowedPlantId = session('active_plant_id');

        $inwards = PurchaseOrderHistory::where('plant_id', $allowedPlantId)
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
                'truck',
                'loadedWeightImage',
                'emptyWeightImage',
            ])
            ->latest()
            ->get();
            
        $purchaseOrders = PurchaseOrder::where('plant_id', $allowedPlantId)
            ->where('receipt_status', '<', 2)
            ->where('state', 'approved')
            ->with(['vendor', 'items.product', 'items.uom'])
            ->latest()
            ->get();

        return Inertia::render('PurchaseOrders/Inwards/Index', [
            'inwards' => $inwards,
            'purchaseOrders' => $purchaseOrders,
            'vehicles' => toSelectOptions(VehiclesDropdown(), 'registration'),
        ]);
    }

    public function edit(PurchaseOrderHistory $inward)
    {
        $this->authorizeModule('edit');
        abort_unless((int) $inward->plant_id === (int) session('active_plant_id'), 404);

        $inward->load([
            'order.vendor', 'order.items.product', 'order.items.uom',
            'order.items.tax', 'order.items.history.uom',
            'product', 'uom', 'item', 'truck', 'loadedWeightImage', 'emptyWeightImage',
        ]);

        return Inertia::render('PurchaseOrders/Inwards/Edit', [
            'inward' => $inward,
        ]);
    }

    public function create(?PurchaseOrder $purchase_order = null)
    {
        $this->authorizeModule('create');
        $allowedPlantId = session('active_plant_id');

        if ($purchase_order) {
            $purchase_order->load(['items.product', 'items.uom', 'items.tax', 'items.history', 'vendor']);
        }

        $purchaseOrders = PurchaseOrder::where('plant_id', $allowedPlantId)
            ->where('receipt_status', '<', 2) // Not fully received
            ->where('state','=','approved')
            ->with(['vendor', 'items.product', 'items.uom'])
            ->latest()
            ->get();

        return Inertia::render('PurchaseOrders/Inwards/Create', [
            'purchase_order' => $purchase_order,
            'purchaseOrders' => $purchaseOrders,
            'vehicles' => toSelectOptions(VehiclesDropdown(), 'registration')
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
            'truck_empty' => 'nullable|numeric|min:0',
            'items' => 'required|array|min:1',
            'items.*.order_item_id' => 'required|exists:mm_purchase_order_items,id',
            'items.*.received_qty' => 'required|numeric|min:0',
            'items.*.truck_id' => 'nullable|exists:mm_machines,id',
            'items.*.truck_loaded' => 'nullable|numeric|min:0',
            'items.*.truck_empty' => 'nullable|numeric|min:0',
            'items.*.loaded_weight_photo' => ['nullable', 'string', new Base64Image],
            'items.*.empty_weight_photo' => ['nullable', 'string', new Base64Image],
        ]);

        $order = PurchaseOrder::findOrFail($validated['order_id']);

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
                $itemTruckEmpty = $itemData['truck_empty'] ?? $validated['truck_empty'] ?? null;

                if ($itemData['received_qty'] <= 0 && empty($itemTruckLoaded)) continue;

                $item = PurchaseOrderItem::findOrFail($itemData['order_item_id']);
                
                $remaining = max(0, (float) $item->product_quantity - (float) $item->received_quantity);
                
                $calcQty = (float)$itemData['received_qty'];
                if ($itemTruckLoaded !== null && $itemTruckEmpty !== null && (float)$itemTruckEmpty > 0) {
                    $calcQty = max(0, (float)$itemTruckLoaded - (float)$itemTruckEmpty);
                }
                $acceptedQty = min($calcQty, $remaining);

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
                    'truck_empty' => $itemTruckEmpty,
                    'status' => 1,
                    'created_by' => $userId,
                    'updated_by' => $userId,
                ]);

                $loadedPhoto = $itemData['loaded_weight_photo'] ?? $validated['loaded_weight_photo'] ?? null;
                if (!empty($loadedPhoto)) {
                    $this->storeInwardImage($history, $loadedPhoto, 'loaded');
                }
                $emptyPhoto = $itemData['empty_weight_photo'] ?? $validated['empty_weight_photo'] ?? null;
                if (!empty($emptyPhoto)) {
                    $this->storeInwardImage($history, $emptyPhoto, 'empty');
                }

                if ($acceptedQty > 0) {
                    $item->received_quantity = $newReceivedQty;
                    $item->updated_by = $userId;
                    $item->save();

                    // Update or Create Quantity record (Stock Balance)
                    $quantityRecord = Quantity::firstOrNew([
                        'plant_id' => $order->plant_id,
                        'product_id' => $item->product_id,
                        'uom_id' => $item->product_uom
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

        $order = $inward->order;
        if ($order && strtolower($order->state) === 'billed') {
            return redirect()->back()->with('error', 'Inward record cannot be deleted because the Purchase Order has already been billed.');
        }

        $stock = Quantity::query()->where([
            'plant_id' => $inward->plant_id,
            'product_id' => $inward->product_id,
            'uom_id' => $inward->uom_id
        ])->first();

        if (!$stock || (float)$stock->quantity < (float)$inward->received_qty) {
            return redirect()->back()->with('error', 'Inward record cannot be deleted because the stock has already been consumed.');
        }

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
            $stock = Quantity::query()->where([
                'plant_id' => $inward->plant_id,
                'product_id' => $inward->product_id,
                'uom_id' => $inward->uom_id
            ])->first();

            if ($stock) {
                $stock->quantity = max(0, (float)$stock->quantity - (float)$inward->received_qty);
                $stock->updated_by = $userId;
                $stock->save();
            }

            // 3. Delete the history record and associated images
            Image::where('category', 'Inward')->where('ref_no', (string)$inward->id)->delete();
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

        $validated = $request->validate([
            'truck_empty' => 'nullable|numeric|min:0',
            'truck_loaded' => 'nullable|numeric|min:0',
            'empty_weight_photo' => ['nullable', 'string', new Base64Image],
            'loaded_weight_photo' => ['nullable', 'string', new Base64Image],
        ]);

        DB::transaction(function () use ($validated, $inward) {
            $userId = Auth::id();
            $oldReceivedQty = (float)$inward->received_qty;

            $loadedWeight = array_key_exists('truck_loaded', $validated) && $validated['truck_loaded'] !== null
                ? (float)$validated['truck_loaded']
                : (float)($inward->truck_loaded ?? 0);

            $emptyWeight = array_key_exists('truck_empty', $validated) && $validated['truck_empty'] !== null
                ? (float)$validated['truck_empty']
                : (float)($inward->truck_empty ?? 0);

            if ($emptyWeight > 0 && $loadedWeight > 0) {
                $newReceivedQty = max(0, $loadedWeight - $emptyWeight);
            } elseif ($loadedWeight > 0) {
                $newReceivedQty = $loadedWeight;
            } else {
                $newReceivedQty = $oldReceivedQty;
            }
            
            $diff = $newReceivedQty - $oldReceivedQty;

            // Update history record
            if (array_key_exists('truck_loaded', $validated) && $validated['truck_loaded'] !== null) {
                $inward->truck_loaded = $validated['truck_loaded'];
            }
            if (array_key_exists('truck_empty', $validated) && $validated['truck_empty'] !== null) {
                $inward->truck_empty = $validated['truck_empty'];
            }
            $inward->received_qty = $newReceivedQty;
            $inward->updated_by = $userId;
            $inward->save();

            if (!empty($validated['loaded_weight_photo'])) {
                $this->storeInwardImage($inward, $validated['loaded_weight_photo'], 'loaded');
            }

            if (!empty($validated['empty_weight_photo'])) {
                $this->storeInwardImage($inward, $validated['empty_weight_photo'], 'empty');
            }

            // Update item total received
            $item = $inward->item;
            if ($item && $diff != 0) {
                $item->received_quantity = max(0, (float)$item->received_quantity + $diff);
                $item->updated_by = $userId;
                $item->save();
            }

            // Update Stock Balance
            if ($diff != 0) {
                $quantityRecord = Quantity::firstOrNew([
                    'plant_id' => $inward->plant_id,
                    'product_id' => $inward->product_id,
                    'uom_id' => $inward->uom_id
                ]);

                if (!$quantityRecord->exists) {
                    $quantityRecord->opening_quantity = 0;
                    $quantityRecord->created_by = $userId;
                    $quantityRecord->status = 1;
                }

                $quantityRecord->quantity = max(0, (float)$quantityRecord->quantity + $diff);
                $quantityRecord->updated_by = $userId;
                $quantityRecord->save();
            }

            // Recalculate Order
            $order = $inward->order;
            if ($order) {
                $order->recalculateTotals();
                $this->refreshOrderReceiptStatus($order);
            }
        });

        return redirect()->back()->with('success', 'Weight and stock balance updated successfully.');
    }

    private function storeInwardImage(PurchaseOrderHistory $inward, ?string $base64Data, string $type): void
    {
        if (empty($base64Data)) {
            Log::warning("storeInwardImage skipped: empty image payload", ['inward_id' => $inward->id, 'type' => $type]);
            return;
        }

        try {
            $decoded = Base64Image::decode($base64Data);
            $data = $decoded['data'];
            $extension = $decoded['extension'];

            $fileName = "inward_{$inward->id}_{$type}_" . time() . ".{$extension}";
            $path = "images/inwards/{$fileName}";
            
            Storage::disk('public')->makeDirectory('images/inwards');
            Storage::disk('public')->put($path, $data);

            try {
                $directDir = public_path('storage/images/inwards');
                if (!file_exists($directDir)) {
                    @mkdir($directDir, 0777, true);
                }
                @file_put_contents($directDir . DIRECTORY_SEPARATOR . $fileName, $data);
            } catch (\Throwable $e) {
                // Ignore if junction handles it
            }

            $image = Image::updateOrCreate(
                [
                    'category' => 'Inward',
                    'ref_no' => (string)$inward->id,
                    'image_name' => "{$type}_weight_snap"
                ],
                [
                    'alt_txt' => ucfirst($type) . ' Weight Photo',
                    'image_path' => $path,
                    'plant_id' => $inward->plant_id ?? session('active_plant_id'),
                    'created_by' => auth()->id() ?? $inward->created_by ?? 1,
                    'updated_by' => auth()->id() ?? $inward->updated_by ?? 1,
                ]
            );

            Log::info("Inward image saved successfully in mm_images table", [
                'image_id' => $image->id,
                'inward_id' => $inward->id,
                'type' => $type,
                'path' => $path,
                'bytes' => strlen($data)
            ]);
        } catch (\Exception $e) {
            Log::error("Failed to store inward image in mm_images: " . $e->getMessage(), [
                'inward_id' => $inward->id,
                'type' => $type,
                'exception' => $e
            ]);
        }
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

    public function receipt(PurchaseOrderHistory $inward, Request $request)
    {
        $this->authorizeModule('view');
        $data = $this->prepareReceiptData($inward);

        return view('pdfs.inwards.receipt', $data);
    }

    public function downloadReceipt(PurchaseOrderHistory $inward)
    {
        $this->authorizeModule('view');
        $data = $this->prepareReceiptData($inward);
        $data['is_pdf'] = true;

        $pdf = Pdf::loadView('pdfs.inwards.receipt', $data)->setPaper('a4', 'portrait');

        $safeInwardNo = str_replace(['/', '\\'], '-', $inward->inward_no);
        $filename = "GRN_{$safeInwardNo}.pdf";

        return $pdf->download($filename);
    }

    private function prepareReceiptData(PurchaseOrderHistory $inward): array
    {
        $inward->loadMissing([
            'order.vendor.contacts.addresses',
            'order.items.product',
            'order.items.uom',
            'product',
            'uom',
            'truck',
            'loadedWeightImage',
            'emptyWeightImage',
        ]);

        $plantId = $inward->plant_id ?? session('active_plant_id') ?? 1;
        $plant = Plant::with(['entity', 'addresses.state'])->find($plantId);
        $company = $plant ? PrintDataFormatter::formatCompany($plant) : [
            'name' => 'MODO RMC',
            'address' => '',
            'city' => '',
            'state' => '',
            'pin' => '',
            'gstin' => '',
            'phone' => '',
            'email' => '',
        ];

        // Format company logo as base64 for DomPDF
        if (!empty($company['logo_path'])) {
            $company['logo_base64'] = $this->fileToBase64($company['logo_path']);
        }

        // Prepare camera snapshots as base64 data URIs so DomPDF renders them reliably without network
        $grossSnapBase64 = null;
        if ($inward->loadedWeightImage && $inward->loadedWeightImage->image_path) {
            $grossSnapBase64 = $this->fileToBase64($inward->loadedWeightImage->image_path);
        }

        $tareSnapBase64 = null;
        if ($inward->emptyWeightImage && $inward->emptyWeightImage->image_path) {
            $tareSnapBase64 = $this->fileToBase64($inward->emptyWeightImage->image_path);
        }

        return compact('inward', 'company', 'grossSnapBase64', 'tareSnapBase64');
    }

    private function fileToBase64(?string $path): ?string
    {
        if (!$path) return null;
        if (str_starts_with($path, 'data:image')) return $path;

        $cleanPath = ltrim($path, '/');
        if (str_starts_with($cleanPath, 'storage/')) {
            $cleanPath = substr($cleanPath, 8);
        }

        $candidates = [
            storage_path('app/public/' . $cleanPath),
            public_path('storage/' . $cleanPath),
            public_path($cleanPath),
        ];

        foreach ($candidates as $file) {
            if (file_exists($file) && is_file($file)) {
                $content = @file_get_contents($file);
                if ($content && strlen($content) > 30) {
                    $ext = strtolower(pathinfo($file, PATHINFO_EXTENSION));
                    $mime = match($ext) {
                        'png' => 'image/png',
                        'gif' => 'image/gif',
                        'webp' => 'image/webp',
                        default => 'image/jpeg',
                    };
                    return "data:{$mime};base64," . base64_encode($content);
                }
            }
        }

        return null;
    }
}
