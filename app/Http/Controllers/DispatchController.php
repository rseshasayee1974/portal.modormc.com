<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\AuthorizesModule;
use App\Http\Requests\DispatchStoreRequest;
use App\Models\Batch;
use App\Models\Dispatch;
use App\Models\WorkOrder;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Illuminate\Http\Request;

class DispatchController extends Controller
{
    use AuthorizesModule;

    protected string $module = 'work_orders';

    public function index()
    {
        $this->authorizeModule('menu');
        $activePlantId = session('active_plant_id');

        $dispatches = Dispatch::with([
            'workOrder',
            'batch',
            'truck:id,registration',
            'driver:id,legal_name',
            'weights',
            'financials',
            'status'
        ])
        ->whereHas('workOrder', fn ($q) => $q->where('plant_id', $activePlantId))
        ->latest()
        ->get();

        return Inertia::render('Dispatches/Index', [
            'dispatches' => $dispatches,
            'activePlantId' => $activePlantId
        ]);
    }

    public function store(DispatchStoreRequest $request)
    {
        $this->authorizeModule('create');
        $validated = $request->validated();

        return DB::transaction(function () use ($validated) {
            // 1. Create Main Dispatch
            $dispatchData = collect($validated)->except(['weights', 'financials', 'status'])->toArray();
            $dispatch = Dispatch::create($dispatchData);

            // 2. Create Weights
            if (!empty($validated['weights'])) {
                $dispatch->weights()->create($validated['weights']);
            } else {
                $dispatch->weights()->create([]);
            }

            // 3. Create Financials (Calculations should happen here or via observers)
            if (!empty($validated['financials'])) {
                $financials = $validated['financials'];
                // Basic Calculation Logic (Can be expanded)
                $financials['load_amount'] = ($financials['load_units'] ?? 0) * ($financials['load_rate'] ?? 0);
                $financials['unload_amount'] = ($financials['unload_units'] ?? 0) * ($financials['unload_rate'] ?? 0);
                $financials['transport_amount'] = ($financials['transport_units'] ?? 0) * ($financials['transport_rate'] ?? 0);
                
                $dispatch->financials()->create($financials);
            } else {
                $dispatch->financials()->create([]);
            }

            // 4. Create Status
            if (!empty($validated['status'])) {
                $dispatch->status()->create($validated['status']);
            } else {
                $dispatch->status()->create(['dispatch_status' => 'Draft']);
            }

            return redirect()->back()->with('success', 'Dispatch process initialized successfully.');
        });
    }

    public function update(DispatchStoreRequest $request, Dispatch $dispatch)
    {
        $this->authorizeModule('edit');
        $validated = $request->validated();

        return DB::transaction(function () use ($validated, $dispatch) {
            // 1. Update Main Dispatch
            $dispatchData = collect($validated)->except(['weights', 'financials', 'status'])->toArray();
            $dispatch->update($dispatchData);

            // 2. Update Weights
            if (!empty($validated['weights'])) {
                $dispatch->weights()->updateOrCreate(['dispatch_id' => $dispatch->id], $validated['weights']);
            }

            // 3. Update Financials
            if (!empty($validated['financials'])) {
                $financials = $validated['financials'];
                $financials['load_amount'] = ($financials['load_units'] ?? 0) * ($financials['load_rate'] ?? 0);
                $financials['unload_amount'] = ($financials['unload_units'] ?? 0) * ($financials['unload_rate'] ?? 0);
                $financials['transport_amount'] = ($financials['transport_units'] ?? 0) * ($financials['transport_rate'] ?? 0);

                $dispatch->financials()->updateOrCreate(['dispatch_id' => $dispatch->id], $financials);
            }

            // 4. Update Status
            if (!empty($validated['status'])) {
                $dispatch->status()->updateOrCreate(['dispatch_id' => $dispatch->id], $validated['status']);
            }

            return redirect()->back()->with('success', 'Dispatch updated successfully.');
        });
    }

    public function destroy(Dispatch $dispatch)
    {
        $this->authorizeModule('delete');
        $dispatch->delete(); // Soft deletes will handle related models if cascading or explicitly handled
        return redirect()->back()->with('success', 'Dispatch deleted successfully.');
    }
}
