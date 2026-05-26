<?php

namespace App\Http\Controllers;

use App\Models\StockExhaust;
use App\Models\StockExhaustLine;
use App\Models\Machine;
use App\Models\Patron;
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

        $stockExhausts = StockExhaust::with([
            'partner',
            'lines.vehicle'
        ])
        ->where('plant_id', session('active_plant_id'))
        ->latest()
        ->get();

        return Inertia::render('StockExhausts/Index', [
            'stockExhausts' => $stockExhausts,
            'machines' => MachinesDropdown()->toArray(),
            'vendors' => PatronsDropdown()->toArray(), // Load all patrons/vendors
        ]);
    }

    public function store(Request $request)
    {
        $this->authorizeModule('create');

        $validated = $request->validate([
            'partner_id' => 'required|exists:mm_patrons,id',
            'name' => 'required|string|max:250',
            'bill_number' => 'nullable|string|max:150',
            'billed_date' => 'required|date',
            'invoice_status' => 'required|integer',
            'status' => 'required|integer',
            'issued_date' => 'required|date',
            
            // Lines
            'lines' => 'required|array|min:1',
            'lines.*.issue_date' => 'required|date',
            'lines.*.quantity_issued' => 'nullable|numeric',
            'lines.*.no_items_issued' => 'required|numeric',
            'lines.*.units' => 'required|string|max:255',
            'lines.*.issued_to' => 'required|string|max:255',
            'lines.*.vehicle_no' => 'required|exists:mm_machines,id',
            'lines.*.changed_km' => 'required|numeric',
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
        });

        return redirect()->back()->with('success', 'Stock exhaust voucher registered successfully.');
    }

    public function update(Request $request, StockExhaust $stockExhaust)
    {
        $this->authorizeModule('edit');

        $validated = $request->validate([
            'partner_id' => 'required|exists:mm_patrons,id',
            'name' => 'required|string|max:250',
            'bill_number' => 'nullable|string|max:150',
            'billed_date' => 'required|date',
            'invoice_status' => 'required|integer',
            'status' => 'required|integer',
            'issued_date' => 'required|date',
            
            // Lines
            'lines' => 'required|array|min:1',
            'lines.*.issue_date' => 'required|date',
            'lines.*.quantity_issued' => 'nullable|numeric',
            'lines.*.no_items_issued' => 'required|numeric',
            'lines.*.units' => 'required|string|max:255',
            'lines.*.issued_to' => 'required|string|max:255',
            'lines.*.vehicle_no' => 'required|exists:mm_machines,id',
            'lines.*.changed_km' => 'required|numeric',
            'lines.*.notes' => 'nullable|string|max:200',
        ]);

        DB::transaction(function () use ($stockExhaust, $validated) {
            $headerData = collect($validated)->except('lines')->toArray();
            $stockExhaust->update($headerData);

            // Re-sync lines
            $stockExhaust->lines()->delete();

            foreach ($validated['lines'] as $line) {
                $line['stock_id'] = $stockExhaust->id;
                StockExhaustLine::create($line);
            }
        });

        return redirect()->back()->with('success', 'Stock exhaust voucher updated successfully.');
    }

    public function destroy(StockExhaust $stockExhaust)
    {
        $this->authorizeModule('delete');
        $stockExhaust->delete();

        return redirect()->back()->with('success', 'Stock exhaust voucher deleted successfully.');
    }
}
