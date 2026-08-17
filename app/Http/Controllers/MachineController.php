<?php

namespace App\Http\Controllers;

use App\Models\Machine;
use App\Models\MachineDocument;
use App\Models\MachineLoan;
use App\Models\MachineEmiPayment;
use App\Http\Requests\StoreMachineRequest;
use App\Http\Requests\UpdateMachineRequest;
use Illuminate\Http\Request;
use Inertia\Inertia;
use App\Http\Controllers\Concerns\AuthorizesModule;
use App\Models\MachineType;
use App\Models\Plant;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use App\Http\Controllers\PurchaseOrderController;

class MachineController extends Controller
{
    use AuthorizesModule;
    protected string $module = 'machines';

    public function index()
    {
        $this->authorizeModule('menu');

        return Inertia::render('Machines/Index', array_merge([
            'machines' => Machine::with(['documents', 'loans.emiPayments'])
                ->where('plant_id', session('active_plant_id'))
                ->latest()
                ->get(),
        ], $this->getDropdownData()));
    }

    private function getDropdownData()
    {
        return [
            'vehicleTypes' => MachineType::where('deleted_at', null)
                ->where('plant_id', session('active_plant_id'))
                ->get(),
            'documentTypes' => ['insurance', 'fc', 'permit', 'road_tax', 'other'],
            'paymentStatuses' => ['pending', 'paid', 'overdue'],
            'transportOwners' => PatronsDropdown(['Transporter'])->toArray(),
        ];
    }

    public function store(StoreMachineRequest $request)
    {
        $this->authorizeModule('create');
        
        DB::transaction(function () use ($request) {
            $plant = Plant::findOrFail(session('active_plant_id'));
            $entityId = session('active_entity_id') ?? $plant->entity_id;
            
            $machine = Machine::create(array_merge(
                $request->safe()->except(['documents', 'loans']),
                [
                    'plant_id' => $plant->id,
                    'entity_id' => $entityId,
                ]
            ));

            $machine->syncFleetRelations($request->validated());
        });

        return redirect()->back()->with('success', 'Machine created successfully.');
    }

    public function update(UpdateMachineRequest $request, Machine $machine)
    {
        $this->authorizeModule('edit');
        
        DB::transaction(function () use ($request, $machine) {
            $entityId = session('active_entity_id') ?? Plant::find(session('active_plant_id'))?->entity_id ?? $machine->entity_id;

            $data = $request->safe()->except(['documents', 'loans']);
            if (empty($machine->entity_id) && $entityId) {
                $data['entity_id'] = $entityId;
            }

            $machine->update($data);
            $machine->syncFleetRelations($request->validated());
        });

        return redirect()->back()->with('success', 'Machine updated successfully.');
    }

    public function destroy(Machine $machine)
    {
        $this->authorizeModule('delete');
        $machine->delete();

        return redirect()->back()->with('success', 'Machine deleted successfully.');
    }
}
