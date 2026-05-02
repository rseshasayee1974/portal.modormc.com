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
            // 1. Prepare Flattened Dispatch Data
            $dispatchData = collect($validated)->except(['status'])->toArray();
            $dispatchData['plant_id'] = session('active_plant_id');
            
            if (!empty($validated['financials'])) {
                $financials = $validated['financials'];
                // Basic Calculation Logic
                $financials['load_untax_amount'] = ($financials['load_units'] ?? 0) * ($financials['load_rate'] ?? 0);
                // Total amount will be calculated in UI or here? 
                // We'll merge them all into the main record
                $dispatchData = array_merge($dispatchData, $financials);
            }

            // 2. Create Main Dispatch
            $dispatch = Dispatch::create($dispatchData);

            // 3. Create Status
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
            // 1. Prepare Flattened Dispatch Data
            $dispatchData = collect($validated)->except(['status'])->toArray();
            
            if (!empty($validated['financials'])) {
                $financials = $validated['financials'];
                $financials['load_untax_amount'] = ($financials['load_units'] ?? 0) * ($financials['load_rate'] ?? 0);
                $dispatchData = array_merge($dispatchData, $financials);
            }

            // 2. Update Main Dispatch
            $dispatch->update($dispatchData);

            // 3. Update Status
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
