<?php

namespace App\Http\Controllers;

use App\Models\PumpRate;
use Illuminate\Http\Request;
use Inertia\Inertia;
use App\Http\Controllers\Concerns\AuthorizesModule;

class PumpRateController extends Controller
{
    use AuthorizesModule;
    protected string $module = 'pump_rates';

    public function index()
    {
        $this->authorizeModule('menu');
        $plantId = session('active_plant_id');

        return Inertia::render('PumpRates/Index', [
            'rates' => PumpRate::with(['customer:id,legal_name', 'pump:id,registration', 'site:id,name', 'uom:id,unit_name'])
                ->where('plant_id', $plantId)
                ->latest()
                ->get(),
            'customers' => PatronsDropdown(['Customer']),
            'pumps'     => PumpTypeDropdown(),
            'sites'     => SitesDropdown(),
            'uoms'      => Productunit(),
        ]);
    }

    public function store(Request $request)
    {
        $this->authorizeModule('create');

        $validated = $request->validate([
            'customer_id' => 'nullable|exists:mm_patrons,id',
            'pump_type'   => 'required|exists:mm_machines,id',
            'rate'        => 'required|numeric|min:0',
            'rate_type'   => 'nullable|string|max:100',
            'uom_id'      => 'required|exists:mm_product_units,id',
            'name'        => 'nullable|string|max:255',
            'site_id'     => 'nullable|exists:mm_sites,id',
        ]);

        $validated['plant_id'] = session('active_plant_id', 1);
        $validated['created_by'] = auth()->id();

        PumpRate::create($validated);

        return redirect()->back()->with('success', 'Pump rate configured successfully.');
    }

    public function update(Request $request, PumpRate $pumprate)
    {
        $this->authorizeModule('edit');

        $validated = $request->validate([
            'customer_id' => 'nullable|exists:mm_patrons,id',
            'pump_type'   => 'required|exists:mm_machines,id',
            'rate'        => 'required|numeric|min:0',
            'rate_type'   => 'nullable|string|max:100',
            'uom_id'      => 'required|exists:mm_product_units,id',
            'name'        => 'nullable|string|max:255',
            'site_id'     => 'nullable|exists:mm_sites,id',
            'status'      => 'boolean'
        ]);

        $validated['updated_by'] = auth()->id();

        $pumprate->update($validated);

        return redirect()->back()->with('success', 'Pump rate updated successfully.');
    }

    public function destroy(PumpRate $pumprate)
    {
        $this->authorizeModule('delete');
        
        $pumprate->update(['deleted_by' => auth()->id()]);
        $pumprate->delete();

        return redirect()->back()->with('success', 'Pump rate deleted successfully.');
    }

    public function resolve(Request $request)
    {
        // Require active plant context
        $plantId = session('active_plant_id') ?: $request->query('plant_id');
        $customerId = $request->query('customer_id');
        $siteId = $request->query('site_id');

        // Query active pump rates for this plant
        $query = PumpRate::where('status', true);
        if ($plantId) {
            $query->where('plant_id', $plantId);
        }

        // We want to load the rates matching either:
        // 1. customer_id = $customerId AND site_id = $siteId
        // 2. customer_id = $customerId AND site_id is NULL
        // 3. customer_id is NULL (globally applicable)
        $rates = $query->where(function ($q) use ($customerId, $siteId) {
            $q->where(function ($sub) use ($customerId, $siteId) {
                if ($customerId) {
                    $sub->where('customer_id', $customerId);
                    if ($siteId) {
                        $sub->where(function ($s) use ($siteId) {
                            $s->where('site_id', $siteId)->orWhereNull('site_id');
                        });
                    } else { 
                        $sub->whereNull('site_id');
                    }
                } else {
                    $sub->whereRaw('1 = 0'); // force false
                }
            })->orWhereNull('customer_id');
        })
        ->orderByRaw('
            CASE 
                WHEN customer_id = ? AND site_id = ? THEN 3
                WHEN customer_id = ? AND site_id IS NULL THEN 2
                ELSE 1
            END DESC
        ', [$customerId, $siteId, $customerId])
        ->get();

        // Leveraging Laravel Collection's unique() tool to keep the highest specificity match (first match)
        $resolvedRates = $rates->unique('pump_type')->map(function ($rate) {
            return [
                'pump_type' => $rate->pump_type,
                'pump_rate' => (float)$rate->rate,
                'rate_type' => $rate->rate_type,
                'uom_id' => $rate->uom_id,
                'name' => $rate->name,
            ];
        })->values();

        return response()->json([
            'success' => true,
            'rates' => $resolvedRates
        ]);
    }
}
