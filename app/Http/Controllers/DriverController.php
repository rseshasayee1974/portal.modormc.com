<?php

namespace App\Http\Controllers;

use App\Models\Driver;
use App\Models\Personnel;
use Illuminate\Http\Request;
use Inertia\Inertia;
use App\Http\Controllers\Concerns\AuthorizesModule;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class DriverController extends Controller
{
    use AuthorizesModule;
    protected string $module = 'driver';

    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $this->authorizeModule('menu');

        $activePlantId = session('active_plant_id');

        $drivers = Driver::with(['personnel.contacts'])
            ->where('plant_id', $activePlantId)
            ->latest()
            ->get();

        // Load personnel who are not yet promoted/linked as drivers
        $availablePersonnel = Personnel::where('plant_id', $activePlantId)
            ->whereNull('deleted_at')
            ->whereNotIn('id', Driver::where('plant_id', $activePlantId)->pluck('personnel_id'))
            ->get(['id', 'first_name', 'last_name']);

        return Inertia::render('Drivers/Index', [
            'drivers' => $drivers,
            'availablePersonnel' => $availablePersonnel,
            'licenseTypes' => ['HMV', 'LMV', 'Heavy Duty', 'Commercial'],
            'statuses' => ['active', 'inactive', 'suspended'],
            'genders' => ['Male', 'Female', 'Other']
        ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $this->authorizeModule('create');

        $validated = $request->validate([
            'is_promoting' => 'required|boolean',
            'personnel_id' => 'nullable|required_if:is_promoting,true|exists:mm_personnels,id',
            
            // Personnel fields (if creating new)
            'first_name' => 'nullable|required_if:is_promoting,false|string|max:100',
            'last_name' => 'nullable|string|max:100',
            'gender' => 'nullable|string',
            'date_of_birth' => 'nullable|date',
            'joining_date' => 'nullable|date',
            
            // Driver specific fields
            'license_number' => 'required|string|max:100',
            'license_expiry_date' => 'nullable|date',
            'license_type' => 'nullable|string|max:100',
            'status' => 'required|string|in:active,inactive,suspended',
        ]);

        DB::beginTransaction();
        try {
            $activePlantId = session('active_plant_id');
            $activeEntityId = session('active_entity_id');
            $userId = auth()->id();

            if ($validated['is_promoting']) {
                $personnelId = $validated['personnel_id'];
                // Update employee type to Driver
                $personnel = Personnel::findOrFail($personnelId);
                $personnel->update([
                    'employee_type' => 'Driver',
                    'updated_by' => $userId
                ]);
            } else {
                // Formulate dates
                $dob = !empty($validated['date_of_birth']) ? date('Y-m-d', strtotime($validated['date_of_birth'])) : null;
                $joining = !empty($validated['joining_date']) ? date('Y-m-d', strtotime($validated['joining_date'])) : null;

                // Create Personnel
                $personnel = Personnel::create([
                    'plant_id' => $activePlantId,
                    'entity_id' => $activeEntityId,
                    'first_name' => $validated['first_name'],
                    'last_name' => $validated['last_name'],
                    'employee_type' => 'Driver',
                    'gender' => $validated['gender'],
                    'date_of_birth' => $dob,
                    'joining_date' => $joining,
                    'status' => 'active',
                    'created_by' => $userId
                ]);
                $personnelId = $personnel->id;
            }

            // Formulate expiry date
            $expiry = !empty($validated['license_expiry_date']) ? date('Y-m-d', strtotime($validated['license_expiry_date'])) : null;

            // Create Driver details
            Driver::create([
                'entity_id' => $activeEntityId,
                'plant_id' => $activePlantId,
                'personnel_id' => $personnelId,
                'license_number' => $validated['license_number'],
                'license_expiry_date' => $expiry,
                'license_type' => $validated['license_type'],
                'status' => $validated['status'],
                'created_by' => $userId
            ]);

            DB::commit();
            return redirect()->back()->with('success', 'Driver record created successfully.');
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Driver store error: ' . $e->getMessage());
            return redirect()->back()->withErrors(['error' => 'Failed to save driver: ' . $e->getMessage()]);
        }
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Driver $driver)
    {
        $this->authorizeModule('edit');

        $validated = $request->validate([
            'first_name' => 'required|string|max:100',
            'last_name' => 'nullable|string|max:100',
            'license_number' => 'required|string|max:100',
            'license_expiry_date' => 'nullable|date',
            'license_type' => 'nullable|string|max:100',
            'status' => 'required|string|in:active,inactive,suspended',
        ]);

        DB::beginTransaction();
        try {
            $userId = auth()->id();

            // Update Personnel details
            $personnel = $driver->personnel;
            if ($personnel) {
                $personnel->update([
                    'first_name' => $validated['first_name'],
                    'last_name' => $validated['last_name'],
                    'updated_by' => $userId
                ]);
            }

            // Update Driver details
            $expiry = !empty($validated['license_expiry_date']) ? date('Y-m-d', strtotime($validated['license_expiry_date'])) : null;
            $driver->update([
                'license_number' => $validated['license_number'],
                'license_expiry_date' => $expiry,
                'license_type' => $validated['license_type'],
                'status' => $validated['status'],
                'updated_by' => $userId
            ]);

            DB::commit();
            return redirect()->back()->with('success', 'Driver record updated successfully.');
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Driver update error: ' . $e->getMessage());
            return redirect()->back()->withErrors(['error' => 'Failed to update driver: ' . $e->getMessage()]);
        }
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Driver $driver)
    {
        $this->authorizeModule('delete');

        DB::beginTransaction();
        try {
            $userId = auth()->id();

            // Soft delete Personnel if linked
            $personnel = $driver->personnel;
            if ($personnel) {
                $personnel->update(['deleted_by' => $userId]);
                $personnel->delete();
            }

            // Soft delete Driver details
            $driver->update(['deleted_by' => $userId]);
            $driver->delete();

            DB::commit();
            return redirect()->back()->with('success', 'Driver record deleted successfully.');
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Driver destroy error: ' . $e->getMessage());
            return redirect()->back()->withErrors(['error' => 'Failed to delete driver.']);
        }
    }
}
