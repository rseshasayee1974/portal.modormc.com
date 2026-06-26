<?php

namespace App\Http\Controllers;

use App\Models\MaintenanceRequest;
use App\Models\MaintenanceLine;
use App\Models\Machine;
use App\Models\User;
use App\Models\Tax;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use App\Http\Controllers\Concerns\AuthorizesModule;

class MaintenanceRequestController extends Controller
{
    use AuthorizesModule;
    protected string $module = 'maintenance_requests';

    public function index()
    {
        $this->authorizeModule('menu');

        $requests = MaintenanceRequest::with([
            'machine', 
            'vendor', 
            'responsible', 
            'shippingTax',
            'lines.product', 
            'lines.uom', 
            'lines.tax', 
            'lines.partner'
        ])
        ->where('plant_id', session('active_plant_id'))
        ->latest()
        ->get();

        return Inertia::render('MaintenanceRequests/Index', array_merge([
            'requests' => $requests
        ], $this->getDropdownData()));
    }

    private function getDropdownData()
    {
        return [
            'machines' => MachinesDropdown()->toArray(),
            'vendors' => PatronsDropdown(['Vendor', 'Transporter'])->toArray(),
            'responsibleUsers' => User::select('id', 'username')->orderBy('username')->get()->toArray(),
            'taxes' => TaxesDropdown('purchase')->toArray(),
            'products' => ProductsDropdown('purchase')->toArray(),
            'units' => Productunit()->toArray(),
        ];
    }

    public function store(Request $request)
    {
        $this->authorizeModule('create');

        $validated = $request->validate([
            'name' => 'required|string|max:250',
            'description' => 'required|string',
            'machine_id' => 'required|exists:mm_machines,id',
            'max_idle_days' => 'nullable|string|max:5',
            'inventory_req_lines' => 'required|string|max:250',
            'maintanence_type' => 'required|integer',
            'service_km' => 'required|numeric',
            'priority' => 'required|integer',
            'responsible_id' => 'required|exists:users,id',
            'repair_location' => 'required|string|max:100',
            'repair_vendor_id' => 'required|exists:mm_patrons,id',
            'bill_no' => 'nullable|string|max:150',
            'order_no' => 'nullable|string|max:150',
            'discount_amount' => 'required|numeric',
            'shipping_charges' => 'required|numeric',
            'shipping_tax_id' => 'nullable|exists:mm_taxes,id',
            'adjustment' => 'required|numeric',
            'rounding_value' => 'required|numeric',
            'filename' => 'nullable|string|max:250',
            'status' => 'required|integer',
            'bill_status' => 'required|integer',
            'dead_line' => 'required|date',
            'start_date' => 'required|date',
            'end_date' => 'required|date',
            
            // Lines validation
            'lines' => 'required|array|min:1',
            'lines.*.name' => 'required|string|max:250',
            'lines.*.product_quantity' => 'required|string|max:255',
            'lines.*.date_planned' => 'required|date',
            'lines.*.product_uom' => 'required|exists:mm_product_units,id',
            'lines.*.product_id' => 'required|exists:mm_products,id',
            'lines.*.description' => 'nullable|string',
            'lines.*.price_unit' => 'required|numeric',
            'lines.*.price_subtotal' => 'required|numeric',
            'lines.*.price_total' => 'required|numeric',
            'lines.*.tax_id' => 'nullable|exists:mm_taxes,id',
            'lines.*.price_tax' => 'required|numeric',
            'lines.*.status' => 'required|integer',
            'lines.*.priority' => 'required|integer',
            'lines.*.invoiced_quantity' => 'required|string|max:255',
            'lines.*.received_quantity' => 'required|string|max:255',
            'lines.*.received_price' => 'nullable|numeric',
            'lines.*.partner_id' => 'required|exists:mm_patrons,id',
        ]);

        DB::transaction(function () use ($validated) {
            $plantId = session('active_plant_id');

            $requestData = collect($validated)->except('lines')->toArray();
            $requestData['plant_id'] = $plantId;

            $maintenanceRequest = MaintenanceRequest::create($requestData);

            foreach ($validated['lines'] as $line) {
                $line['order_id'] = $maintenanceRequest->id;
                $line['plant_id'] = $plantId;
                MaintenanceLine::create($line);
            }
        });

        return redirect()->back()->with('success', 'Maintenance Request registered successfully.');
    }

    public function update(Request $request, MaintenanceRequest $maintenanceRequest)
    {
        $this->authorizeModule('edit');

        $validated = $request->validate([
            'name' => 'required|string|max:250',
            'description' => 'required|string',
            'machine_id' => 'required|exists:mm_machines,id',
            'max_idle_days' => 'nullable|string|max:5',
            'inventory_req_lines' => 'required|string|max:250',
            'maintanence_type' => 'required|integer',
            'service_km' => 'required|numeric',
            'priority' => 'required|integer',
            'responsible_id' => 'required|exists:users,id',
            'repair_location' => 'required|string|max:100',
            'repair_vendor_id' => 'required|exists:mm_patrons,id',
            'bill_no' => 'nullable|string|max:150',
            'order_no' => 'nullable|string|max:150',
            'discount_amount' => 'required|numeric',
            'shipping_charges' => 'required|numeric',
            'shipping_tax_id' => 'nullable|exists:mm_taxes,id',
            'adjustment' => 'required|numeric',
            'rounding_value' => 'required|numeric',
            'filename' => 'nullable|string|max:250',
            'status' => 'required|integer',
            'bill_status' => 'required|integer',
            'dead_line' => 'required|date',
            'start_date' => 'required|date',
            'end_date' => 'required|date',
            
            // Lines validation
            'lines' => 'required|array|min:1',
            'lines.*.name' => 'required|string|max:250',
            'lines.*.product_quantity' => 'required|string|max:255',
            'lines.*.date_planned' => 'required|date',
            'lines.*.product_uom' => 'required|exists:mm_product_units,id',
            'lines.*.product_id' => 'required|exists:mm_products,id',
            'lines.*.description' => 'nullable|string',
            'lines.*.price_unit' => 'required|numeric',
            'lines.*.price_subtotal' => 'required|numeric',
            'lines.*.price_total' => 'required|numeric',
            'lines.*.tax_id' => 'nullable|exists:mm_taxes,id',
            'lines.*.price_tax' => 'required|numeric',
            'lines.*.status' => 'required|integer',
            'lines.*.priority' => 'required|integer',
            'lines.*.invoiced_quantity' => 'required|string|max:255',
            'lines.*.received_quantity' => 'required|string|max:255',
            'lines.*.received_price' => 'nullable|numeric',
            'lines.*.partner_id' => 'required|exists:mm_patrons,id',
        ]);

        DB::transaction(function () use ($maintenanceRequest, $validated) {
            $plantId = session('active_plant_id');

            $requestData = collect($validated)->except('lines')->toArray();
            $maintenanceRequest->update($requestData);

            // Re-sync lines
            $maintenanceRequest->lines()->delete();

            foreach ($validated['lines'] as $line) {
                $line['order_id'] = $maintenanceRequest->id;
                $line['plant_id'] = $plantId;
                MaintenanceLine::create($line);
            }
        });

        return redirect()->back()->with('success', 'Maintenance Request updated successfully.');
    }

    public function destroy(MaintenanceRequest $maintenanceRequest)
    {
        $this->authorizeModule('delete');
        $maintenanceRequest->delete();

        return redirect()->back()->with('success', 'Maintenance Request deleted successfully.');
    }
}
