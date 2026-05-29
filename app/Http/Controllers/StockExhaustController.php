<?php

namespace App\Http\Controllers;

use App\Models\StockExhaust;
use App\Models\StockExhaustLine;
use App\Models\Machine;
use App\Models\Patron;
use App\Models\Product;
use App\Models\Quantity;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use App\Http\Controllers\Concerns\AuthorizesModule;

class StockExhaustController extends Controller
{
    use AuthorizesModule;
    protected string $module = 'machines';

    public function index()
    {
        $this->authorizeModule('menu');

        $plantId = session('active_plant_id');

        $stockExhausts = StockExhaust::with([
            'partner',
            'lines.vehicle',
            'lines.product'
        ])
        ->where('plant_id', $plantId)
        ->latest()
        ->get();

        $quantitySubQuery = Quantity::query()
            ->selectRaw('product_id, SUM(quantity) as stock_qty')
            ->where('plant_id', $plantId)
            ->groupBy('product_id')
            ->having('stock_qty', '>', 0);

        $availableProducts = Product::where('mm_products.plant_id', $plantId)
            ->joinSub($quantitySubQuery, 'stock_levels', function ($join) {
                $join->on('stock_levels.product_id', '=', 'mm_products.id');
            })
            ->with('unit')
            ->whereNull('mm_products.deleted_at')
            ->get(['mm_products.id', 'mm_products.title', 'mm_products.unit_id', 'stock_levels.stock_qty']);

        return Inertia::render('StockExhausts/Index', [
            'stockExhausts' => $stockExhausts,
            'machines' => MachinesDropdown()->toArray(),
            'vendors' => PatronsDropdown()->toArray(), // Load all patrons/vendors
            'products' => $availableProducts->map(fn($p) => [
                'label' => $p->title,
                'value' => $p->id,
                'unit_id' => $p->unit_id,
                'unit_code' => $p->unit?->unit_code,
                'stock_qty' => (float)$p->stock_qty,
            ])->toArray(),
            'units'   => Productunit(),
        ]);
    }

    public function store(Request $request)
    {
        $this->authorizeModule('create');

        $validated = $request->validate([
            'partner_id' => 'required|exists:mm_patrons,id',
            'bill_number' => 'nullable|string|max:150',
            'billed_date' => 'nullable|date',
            'invoice_status' => 'nullable|integer',
            'status' => 'nullable|integer',
            'issued_date' => 'nullable|date',
            
            // Lines
            'lines' => 'required|array|min:1',
            'lines.*.issue_date' => 'nullable|date',
            'lines.*.quantity_issued' => 'required|numeric',
            'lines.*.no_items_issued' => 'nullable|numeric',
            'lines.*.units' => 'nullable|string|max:255',
            'lines.*.product_id' => 'required|exists:mm_products,id',
            'lines.*.issued_to' => 'nullable|string|max:255',
            'lines.*.vehicle_no' => 'nullable|exists:mm_machines,id',
            'lines.*.changed_km' => 'nullable|numeric',
            'lines.*.notes' => 'nullable|string|max:200',
        ]);

        DB::transaction(function () use ($validated) {
            $plantId = session('active_plant_id');

            $headerData = collect($validated)->except('lines')->toArray();
            $headerData['plant_id'] = $plantId;

            $stockExhaust = StockExhaust::create($headerData);

            foreach ($validated['lines'] as $line) {
                $line['stock_id'] = $stockExhaust->id;
                StockExhaustLine::create($line);
            }

            // Deduct issued quantities from stock
            $this->adjustStock($validated['lines'], $validated['issued_date'], $plantId);
        });

        return redirect()->back()->with('success', 'Stock exhaust voucher registered successfully.');
    }

    public function update(Request $request, StockExhaust $stockExhaust)
    {
        $this->authorizeModule('edit');

        $validated = $request->validate([
            'partner_id' => 'required|exists:mm_patrons,id',
            'name' => 'nullable|string|max:250',
            'bill_number' => 'nullable|string|max:150',
            'billed_date' => 'nullable|date',
            'invoice_status' => 'nullable|integer',
            'status' => 'nullable|integer',
            'issued_date' => 'nullable|date',
            
            // Lines
            'lines' => 'required|array|min:1',
            'lines.*.issue_date' => 'nullable|date',
            'lines.*.quantity_issued' => 'required|numeric',
            'lines.*.no_items_issued' => 'nullable|numeric',
            'lines.*.units' => 'nullable|string|max:255',
            'lines.*.product_id' => 'required|exists:mm_products,id',
            'lines.*.issued_to' => 'nullable|string|max:255',
            'lines.*.vehicle_no' => 'nullable|exists:mm_machines,id',
            'lines.*.changed_km' => 'nullable|numeric',
            'lines.*.notes' => 'nullable|string|max:200',
        ]);

        DB::transaction(function () use ($stockExhaust, $validated) {
            $plantId = $stockExhaust->plant_id ?? session('active_plant_id');

            // 1. Revert old lines stock before re-syncing
            $oldLines = $stockExhaust->lines->toArray();
            $oldDate  = $stockExhaust->issued_date?->toDateString() ?? now()->toDateString();
            $this->adjustStock($oldLines, $oldDate, $plantId, true);

            // 2. Update header
            $headerData = collect($validated)->except('lines')->toArray();
            $stockExhaust->update($headerData);

            // 3. Re-sync lines
            $stockExhaust->lines()->delete();

            foreach ($validated['lines'] as $line) {
                $line['stock_id'] = $stockExhaust->id;
                StockExhaustLine::create($line);
            }

            // 4. Deduct stock for new lines
            $this->adjustStock($validated['lines'], $validated['issued_date'], $plantId);
        });

        return redirect()->back()->with('success', 'Stock exhaust voucher updated successfully.');
    }

    public function destroy(StockExhaust $stockExhaust)
    {
        $this->authorizeModule('delete');

        DB::transaction(function () use ($stockExhaust) {
            $plantId = $stockExhaust->plant_id ?? session('active_plant_id');
            $date    = $stockExhaust->issued_date?->toDateString() ?? now()->toDateString();

            // Revert all issued quantities back to stock
            $lines = $stockExhaust->lines->toArray();
            $this->adjustStock($lines, $date, $plantId, true);

            $stockExhaust->lines()->delete();
            $stockExhaust->delete();
        });

        return redirect()->back()->with('success', 'Stock exhaust voucher deleted successfully.');
    }

    /**
     * Adjust stock levels in mm_quantity for each exhaust line.
     *
     * @param  iterable  $lines         Array of line data arrays (must contain product_id, units, quantity_issued).
     * @param  string    $date          The issued date string used to scope the stock record.
     * @param  int       $plantId       Active plant ID.
     * @param  bool      $isReverting   If true, quantities are added back; if false, quantities are subtracted.
     */
    private function adjustStock(iterable $lines, string $date, int $plantId, bool $isReverting = false): void
    {
        $userId = auth()->id();

        foreach ($lines as $line) {
            $productId = $line['product_id'] ?? null;
            $uomId     = $line['units'] ?? null;          // 'units' stores the UOM integer ID
            $qty       = (float) ($line['quantity_issued'] ?? 0);

            if (empty($productId) || empty($uomId) || $qty <= 0) {
                continue;
            }

            $record = Quantity::firstOrNew([
                'plant_id'     => $plantId,
                'product_id'   => $productId,
                'uom_id'       => $uomId,
                'date'         => $date,
                'is_warehouse' => true,
            ]);

            if (!$record->exists) {
                $record->opening_quantity = 0;
                $record->created_by       = $userId;
                $record->status           = 1;
            }

            if ($isReverting) {
                $record->quantity = (float) $record->quantity + $qty;
            } else {
                $record->quantity = max(0, (float) $record->quantity - $qty);
            }

            $record->updated_by = $userId;
            $record->save();
        }
    }
}