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
       $allowedPlantIds = (new PurchaseOrderController())->allowedPlantIds();
        // dd($this->getDropdownData());
        return Inertia::render('Machines/Index', array_merge([
            'machines' => Machine::with(['documents', 'loans.emiPayments'])
                ->where('plant_id', session('active_plant_id'))
                ->latest()
                ->get(),
               'transportOwners' =>   toSelectOptions(VehiclesDropdown($allowedPlantIds), 'registration'),
        ], $this->getDropdownData(),
      ));
    }

    private function getDropdownData()
    {
        return [
            'vehicleTypes' => MachineType::where('deleted_at', null)
                ->where('plant_id', session('active_plant_id'))
                ->get(),
            'documentTypes' => ['insurance', 'fc', 'permit', 'road_tax', 'other'],
            'paymentStatuses' => ['pending', 'paid', 'overdue'],
            'transportOwners' => \App\Models\Patron::ofType(['Transporter'])
                ->where('plant_id', session('active_plant_id'))
                ->select('id', 'legal_name')
                ->get(),
        ];
    }

    public function store(StoreMachineRequest $request)
    {
        $this->authorizeModule('create');
        
        DB::transaction(function () use ($request) {
            $plant = Plant::findOrFail(session('active_plant_id'));
            
            $machine = Machine::create(array_merge(
                $request->safe()->except(['documents', 'loans']),
                [
                    'plant_id' => $plant->id
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
            $machine->update($request->safe()->except(['documents', 'loans']));
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
