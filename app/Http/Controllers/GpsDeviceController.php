<?php

namespace App\Http\Controllers;

use App\Models\GpsDevice;
use App\Models\Machine;
use Illuminate\Http\Request;
use Inertia\Inertia;
use App\Http\Controllers\Concerns\AuthorizesModule;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class GpsDeviceController extends Controller
{
    use AuthorizesModule;
    protected string $module = 'gps_device';

    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $this->authorizeModule('menu');

        $activePlantId = session('active_plant_id');

        $devices = GpsDevice::with(['machine', 'latestPosition'])
            ->where('plant_id', $activePlantId)
            ->latest()
            ->get();

        // Find machines in this plant that do not have a GPS device assigned (except during edits)
        $assignedMachineIds = GpsDevice::where('plant_id', $activePlantId)
            ->whereNotNull('machine_id')
            ->pluck('machine_id')
            ->toArray();

        $availableMachines = Machine::where('plant_id', $activePlantId)
            ->whereNotIn('id', $assignedMachineIds)
            ->get(['id', 'registration', 'vehicle_model']);

        return Inertia::render('GpsDevices/Index', [
            'devices' => $devices,
            'availableMachines' => $availableMachines,
            'deviceModels' => ['TK103', 'Coban GPS103', 'Teltonika FMB920', 'GT06', 'SinoTrack ST-901', 'Other'],
            'statuses' => [
                ['label' => 'Active', 'value' => 1],
                ['label' => 'Inactive', 'value' => 0]
            ]
        ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $this->authorizeModule('create');

        $validated = $request->validate([
            'imei' => 'required|string|max:50|unique:mm_gps_devices,imei,NULL,id,deleted_at,NULL',
            'device_model' => 'required|string|max:100',
            'sim_number' => 'nullable|string|max:50',
            'phone_number' => 'nullable|string|max:50',
            'machine_id' => 'nullable|exists:mm_machines,id',
            'is_active' => 'required|boolean',
            'notes' => 'nullable|string',
        ]);

        DB::beginTransaction();
        try {
            $activePlantId = session('active_plant_id');
            $userId = auth()->id();

            // Verify if machine is already assigned in this plant
            if ($validated['machine_id']) {
                $alreadyAssigned = GpsDevice::where('plant_id', $activePlantId)
                    ->where('machine_id', $validated['machine_id'])
                    ->exists();

                if ($alreadyAssigned) {
                    return redirect()->back()->withErrors([
                        'machine_id' => 'This vehicle already has a GPS device assigned.'
                    ])->withInput();
                }
            }

            GpsDevice::create([
                'plant_id' => $activePlantId,
                'machine_id' => $validated['machine_id'],
                'imei' => $validated['imei'],
                'device_model' => $validated['device_model'],
                'sim_number' => $validated['sim_number'],
                'phone_number' => $validated['phone_number'],
                'is_active' => $validated['is_active'],
                'notes' => $validated['notes'],
                'created_by' => $userId
            ]);

            DB::commit();
            return redirect()->back()->with('success', 'GPS device registered successfully.');
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('GPS Device store error: ' . $e->getMessage());
            return redirect()->back()->withErrors(['error' => 'Failed to save GPS device: ' . $e->getMessage()]);
        }
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, GpsDevice $gpsDevice)
    {
        $this->authorizeModule('edit');

        $validated = $request->validate([
            'imei' => 'required|string|max:50|unique:mm_gps_devices,imei,' . $gpsDevice->id . ',id,deleted_at,NULL',
            'device_model' => 'required|string|max:100',
            'sim_number' => 'nullable|string|max:50',
            'phone_number' => 'nullable|string|max:50',
            'machine_id' => 'nullable|exists:mm_machines,id',
            'is_active' => 'required|boolean',
            'notes' => 'nullable|string',
        ]);

        DB::beginTransaction();
        try {
            $activePlantId = session('active_plant_id');
            $userId = auth()->id();

            // Verify if machine is already assigned to a DIFFERENT device
            if ($validated['machine_id']) {
                $alreadyAssigned = GpsDevice::where('plant_id', $activePlantId)
                    ->where('machine_id', $validated['machine_id'])
                    ->where('id', '!=', $gpsDevice->id)
                    ->exists();

                if ($alreadyAssigned) {
                    return redirect()->back()->withErrors([
                        'machine_id' => 'This vehicle already has a GPS device assigned.'
                    ])->withInput();
                }
            }

            $gpsDevice->update([
                'machine_id' => $validated['machine_id'],
                'imei' => $validated['imei'],
                'device_model' => $validated['device_model'],
                'sim_number' => $validated['sim_number'],
                'phone_number' => $validated['phone_number'],
                'is_active' => $validated['is_active'],
                'notes' => $validated['notes'],
                'updated_by' => $userId
            ]);

            DB::commit();
            return redirect()->back()->with('success', 'GPS device updated successfully.');
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('GPS Device update error: ' . $e->getMessage());
            return redirect()->back()->withErrors(['error' => 'Failed to update GPS device: ' . $e->getMessage()]);
        }
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(GpsDevice $gpsDevice)
    {
        $this->authorizeModule('delete');

        DB::beginTransaction();
        try {
            $userId = auth()->id();

            $gpsDevice->update(['deleted_by' => $userId]);
            $gpsDevice->delete();

            DB::commit();
            return redirect()->back()->with('success', 'GPS device record deleted successfully.');
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('GPS Device destroy error: ' . $e->getMessage());
            return redirect()->back()->withErrors(['error' => 'Failed to delete GPS device.']);
        }
    }
}
