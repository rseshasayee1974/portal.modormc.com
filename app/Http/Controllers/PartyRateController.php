<?php

namespace App\Http\Controllers;

use App\Models\PartyRate;
use Illuminate\Http\Request;
use Inertia\Inertia;
use App\Http\Controllers\Concerns\AuthorizesModule;

class PartyRateController extends Controller
{
    use AuthorizesModule;
    protected string $module = 'party_rates';

    public function index()
    {
        $this->authorizeModule('menu');
        $plantId = session('active_plant_id');

        return Inertia::render('PartyRates/Index', [
            'rates' => PartyRate::with(['patron:id,legal_name', 'loadingSite:id,name', 'unloadingSite:id,name', 'uom:id,name', 'product:id,name'])
                ->where('plant_id', $plantId)
                ->latest()
                ->get(),
            'patrons'  => PatronsDropdown($plantId),
            'sites'    => SitesDropdown($plantId),
            'uoms'     => Productunit('Purchase'),
            'products' => ProductsDropdown($plantId),
        ]);
    }

    public function store(Request $request)
    {
        $this->authorizeModule('create');

        $validated = $request->validate([
            'patron_id'      => 'nullable|exists:mm_patrons,id',
            'loading_site'   => 'nullable|exists:mm_sites,id',
            'unloading_site' => 'nullable|exists:mm_sites,id',
            'uom_id'         => 'nullable|exists:mm_product_units,id',
            'payment_type'   => 'nullable|string|max:100',
            'product_id'     => 'nullable|exists:mm_products,id',
            'product_rate'   => 'nullable|numeric|min:0',
            'transport_rate' => 'nullable|numeric|min:0',
            'rate'           => 'required|numeric|min:0',
        ]);

        $validated['plant_id'] = session('active_plant_id', 1);

        PartyRate::create($validated);

        return redirect()->back()->with('success', 'Party rate configured.');
    }

    public function update(Request $request, PartyRate $partyRate)
    {
        $this->authorizeModule('edit');

        $validated = $request->validate([
            'patron_id'      => 'nullable|exists:mm_patrons,id',
            'loading_site'   => 'nullable|exists:mm_sites,id',
            'unloading_site' => 'nullable|exists:mm_sites,id',
            'uom_id'         => 'nullable|exists:mm_product_units,id',
            'payment_type'   => 'nullable|string|max:100',
            'product_id'     => 'nullable|exists:mm_products,id',
            'product_rate'   => 'nullable|numeric|min:0',
            'transport_rate' => 'nullable|numeric|min:0',
            'rate'           => 'required|numeric|min:0',
            'status'         => 'boolean'
        ]);

        $partyRate->update($validated);

        return redirect()->back()->with('success', 'Party rate updated.');
    }

    public function destroy(PartyRate $partyRate)
    {
        $this->authorizeModule('delete');
        $partyRate->delete();
        return redirect()->back()->with('success', 'Party rate deleted.');
    }
}
