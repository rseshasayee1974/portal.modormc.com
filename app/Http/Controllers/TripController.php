<?php

namespace App\Http\Controllers;

use App\Models\Trip;
use App\Models\TripWeight;
use App\Models\TripFinancial;
use App\Models\TripStatus;
use App\Models\TripPayment;
use App\Models\PaymentMethod;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Illuminate\Support\Facades\DB;
use App\Http\Controllers\Concerns\AuthorizesModule;

class TripController extends Controller
{
    use AuthorizesModule;
    protected string $module = 'trips';

    public function index()
    {
        $this->authorizeModule('menu');
        $plantId = session('active_plant_id');

        return Inertia::render('Trips/Index', [
            'trips' => Trip::with([
                    'party:id,legal_name', 'vendor:id,legal_name', 'truck:id,registration',
                    'product:id,title', 'loadSite:id,name', 'unloadSite:id,name',
                    'driver:id,first_name,last_name', 'status'
                ])
                ->where('plant_id', $plantId)
                ->latest()
                ->get(),
            'options' => [
                'patrons'    => PatronsDropdown($plantId),
                'sites'      => SitesDropdown($plantId),
                'machines'   => MachinesDropdown($plantId),
                'products'   => ProductsDropdown($plantId),
                'personnels' => PersonnelDropdown($plantId),
                'taxes'      => TaxesDropdown($plantId, null, ['GST', 'IGST']),
                'transports' => PatronsDropdown($plantId, ['Transporter']),
                'methods'    => PaymentMethod::select('id', 'name')->get(),
            ]
        ]);
    }

    public function store(Request $request)
    {
        $this->authorizeModule('create');

        $validated = $request->validate([
            'trip_type'     => 'required|in:inbound,outbound',
            'party_id'      => 'required|exists:mm_patrons,id',
            'vendor_id'     => 'nullable|exists:mm_patrons,id',
            'truck_id'      => 'nullable|exists:mm_machines,id',
            'load_site_id'  => 'nullable|exists:mm_sites,id',
            'unload_site_id'=> 'nullable|exists:mm_sites,id',
            'product_id'    => 'nullable|exists:mm_products,id',
            'driver_id'     => 'nullable|exists:mm_personnels,id',
            'payment_mode'  => 'required|in:cash,credit',
            
            // Financial initial
            'product_units'  => 'nullable|numeric|min:0',
            'product_amount' => 'nullable|numeric|min:0',
            'product_tax_id' => 'nullable|exists:mm_taxes,id',
            
            // Weights initial
            'empty_weight_load' => 'nullable|numeric|min:0',
        ]);

        DB::transaction(function () use ($validated) {
            $plantId  = session('active_plant_id', 1);
            $entityId = session('entity_id', 1); // Tenant context

            $trip = Trip::create(array_merge($validated, [
                'plant_id'  => $plantId,
                'entity_id' => $entityId
            ]));

            // Init Weights
            TripWeight::create([
                'trip_id' => $trip->id,
                'empty_weight_load' => $validated['empty_weight_load'] ?? 0,
            ]);

            // Init Financials
            TripFinancial::create([
                'trip_id' => $trip->id,
                'product_units'  => $validated['product_units'] ?? 0,
                'product_amount' => $validated['product_amount'] ?? 0,
                'product_tax_id' => $validated['product_tax_id'] ?? null,
            ]);

            // Init Status
            TripStatus::create([
                'trip_id' => $trip->id,
                'trip_status' => 0, // Draft
            ]);
        });

        return redirect()->back()->with('success', 'Trip started.');
    }

    /**
     * Dashboard Summary for daily reconciliation.
     */
    public function dashboard()
    {
        $this->authorizeModule('menu');
        $plantId = session('active_plant_id');

        $dailyStats = TripPayment::whereHas('trip', fn($q) => $q->where('plant_id', $plantId))
            ->whereDate('created_at', now())
            ->with('paymentMethod')
            ->get()
            ->groupBy('payment_method_id')
            ->map(fn($group) => [
                'method' => $group->first()->paymentMethod->name,
                'total'  => $group->sum('amount')
            ]);
            
        $outstanding = Trip::where('plant_id', $plantId)
            ->whereHas('status', fn($q) => $q->where('is_closed', false))
            ->get()
            ->sum(fn($t) => $t->balance_amount);

        return Inertia::render('Trips/Dashboard', [
            'stats' => $dailyStats,
            'outstanding' => $outstanding,
        ]);
    }

    public function recordPayment(Request $request, Trip $trip)
    {
        $this->authorizeModule('edit');
        
        $validated = $request->validate([
            'payment_method_id' => 'required|exists:mm_payment_methods,id',
            'amount'            => 'required|numeric|min:0.01',
            'payment_type'      => 'required|in:full,partial',
            'reference'         => 'nullable|string',
        ]);

        TripPayment::create(array_merge($validated, ['trip_id' => $trip->id]));
        
        return redirect()->back()->with('success', 'Payment recorded.');
    }

    public function update(Request $request, Trip $trip)
    {
        $this->authorizeModule('edit');
        // Handle comprehensive updates for logistics, weights, financial adjustments
        // Delegates logic to models for re-calculation
        $trip->load(['weights', 'financials', 'status']);
        
        DB::transaction(function() use ($request, $trip) {
            $trip->update($request->only($trip->getFillable()));
            
            if ($request->has('weights')) {
                $trip->weights()->update($request->input('weights'));
            }
            if ($request->has('financials')) {
                $trip->financials()->update($request->input('financials'));
            }
            if ($request->has('status')) {
                $trip->status()->update($request->input('status'));
            }
        });

        return redirect()->back()->with('success', 'Trip details updated.');
    }
}
