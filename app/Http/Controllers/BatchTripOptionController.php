<?php
/*
Author: ragul-onemodo
Created: 2026-11-03 16:00:33 Asia/Calcutta (UTC+05:30)
*/

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\AuthorizesModule;
use App\Models\{Designation, Machine, Personnel, Plant};
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class BatchTripOptionController extends Controller
{
    use AuthorizesModule;

    protected string $module = 'batches';

    public function store(Request $request)
    {
        $this->authorizeModule('create');
        $data = $request->validate([
            'kind' => ['required', 'in:truck,driver'],
            'name' => ['required', 'string', 'max:100'],
        ]);
        abort_unless(session('active_plant_id'), 422, 'Select an active plant first.');

        $option = DB::transaction(function () use ($data) {
            // Serialize quick additions to prevent duplicate registrations and employee codes.
            $plant = Plant::whereKey(session('active_plant_id'))->lockForUpdate()->firstOrFail();
            $name = preg_replace('/\s+/u', ' ', trim($data['name']));
            if ($name === '') {
                throw ValidationException::withMessages(['name' => 'Enter a name or truck number.']);
            }
            if ($data['kind'] === 'truck') {
                $registration = strtoupper(preg_replace('/[\s-]+/', '', $name));
                if (!preg_match('/^([A-Z]{2})([0-9]{2})([A-Z]{2})([0-9]{4})$/', $registration, $parts)) {
                    throw ValidationException::withMessages(['name' => 'Enter a truck number in TN 00 AA 0000 format (spaces are optional).']);
                }
                $formattedRegistration = implode(' ', array_slice($parts, 1));
                $truck = Machine::withoutGlobalScopes()->whereNull('deleted_at')
                    ->where('plant_id', $plant->id)->get()
                    ->filter(fn ($machine) => strtoupper(preg_replace('/[\s-]+/', '', $machine->registration)) === $registration)
                    ->sortByDesc(fn ($machine) => $machine->registration === $formattedRegistration)->first();
                if ($truck && (!$truck->is_active
                    || !in_array($truck->vehicle_type, ['Truck', 'Transit Mixer'], true))) {
                    throw ValidationException::withMessages(['name' => 'This registration already exists outside the active truck list. Update it in Machines first.']);
                }
                $truck ??= Machine::create(['plant_id' => $plant->id, 'entity_id' => $plant->entity_id,
                    'registration' => $formattedRegistration, 'vehicle_type' => 'Transit Mixer', 'is_active' => true]);
                if ($truck->registration !== $formattedRegistration) {
                    $truck->update(['registration' => $formattedRegistration]);
                }
                return ['id' => $truck->id, 'registration' => $truck->registration];
            }

            $designation = Designation::where('plant_id', $plant->id)->whereRaw('LOWER(name) = ?', ['driver'])->first()
                ?? Designation::create(['plant_id' => $plant->id, 'name' => 'Driver', 'code' => 'DRIVER']);
            $driver = Personnel::where('plant_id', $plant->id)->where('designation_id', $designation->id)
                ->where('status', 'active')->get()->first(fn ($person) =>
                    Str::lower(trim($person->first_name . ' ' . $person->last_name)) === Str::lower($name));
            $driver ??= Personnel::create(['plant_id' => $plant->id, 'entity_id' => $plant->entity_id,
                'first_name' => $name, 'designation_id' => $designation->id,
                'employment_type' => 'temporary', 'status' => 'active']);
            return ['id' => $driver->id, 'value' => $driver->id, 'label' => trim($driver->first_name . ' ' . $driver->last_name),
                'first_name' => $driver->first_name, 'last_name' => $driver->last_name, 'transporter_id' => null];
        });

        return response()->json(['option' => $option]);
    }
}
