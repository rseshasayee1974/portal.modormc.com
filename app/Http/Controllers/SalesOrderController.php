<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\AuthorizesModule;
use App\Http\Requests\StoreSalesOrderRequest;
use App\Http\Requests\UpdateSalesOrderRequest;
use App\Models\Plant;
use App\Models\SalesOrder;
use App\Services\SalesOrders\SalesOrderIndexDataFactory;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

class SalesOrderController extends Controller
{
    use AuthorizesModule;

    protected string $module = 'sales_orders';

    public function __construct(private readonly SalesOrderIndexDataFactory $indexDataFactory)
    {
    }

    public function index()
    {
        $this->authorizeModule('menu');
        $activePlantId = session('active_plant_id');

        return Inertia::render(
            'SalesOrders/Index',
            
            $this->indexDataFactory->build($activePlantId !== null ? (int) $activePlantId : null)
        );
    }

    public function nextReference(Request $request)
    {
        $this->authorizeModule('create');
        $plantId = (int) session('active_plant_id');
        abort_unless($plantId, 404);
        $data = $request->validate([
            'customer_id' => ['nullable', 'integer', Rule::exists('mm_patrons', 'id')->where('plant_id', $plantId)->whereNull('deleted_at')],
        ]);

        $details = SalesOrder::generateOrderNo($plantId, $data['customer_id'] ?? null);

        return response()->json([
            'reference' => $details['full_number'],
            'next_number' => explode('/', $details['next_number'])[0],
        ]);
    }

    public function resendEmail(SalesOrder $salesorder)
    {
        $this->authorizeModule('edit');
        $this->ensurePlantScope($salesorder);
        $result = app(\App\Services\SalesOrders\SalesOrderEmails::class)->resend($salesorder);
        return response()->json($result + ['message' => sprintf(
            '%d recipient(s) sent, %d failed, %d pending. Previously sent recipients are not emailed again.',
            $result['sent'], $result['failed'], $result['pending']
        )]);
    }

    public function store(StoreSalesOrderRequest $request)
    {
        \App\Support\DocumentNumberAccess::validate($request, 'SALES_ORDER', 'order_no');
        $this->authorizeModule('create');
        $payload = $request->validated();

        if (!empty($payload['customer_po_id'])) {
            $po = \App\Models\CustomerPO::find($payload['customer_po_id']);
            if ($po) {
                $payload['sales_executive_id'] = $payload['sales_executive_id'] ?? $po->sales_executive_id;
                $payload['customer_id'] = $payload['customer_id'] ?? $po->patron_id;
                $payload['site_id'] = $payload['site_id'] ?? $po->site_id;
                $payload['is_tax_inclusive'] = $payload['is_tax_inclusive'] ?? $po->is_tax_inclusive;
            }
        }

        $tableColumns = Schema::getColumnListing('mm_sales_orders');
        $hasPlantIdColumn = in_array('plant_id', $tableColumns, true);
        $hasTotalQtyColumn = in_array('total_qty', $tableColumns, true);
        $hasLegacyQtyColumn = in_array('quantity', $tableColumns, true);

        if ($hasPlantIdColumn && empty($payload['plant_id'])) {
            $payload['plant_id'] = session('active_plant_id');
        }

        if ($hasLegacyQtyColumn && !$hasTotalQtyColumn && isset($payload['total_qty'])) {
            $payload['quantity'] = $payload['total_qty'];
        }

        if (array_key_exists('pump_rate', $payload) && ($payload['pump_rate'] === null || $payload['pump_rate'] === '')) {
            $payload['pump_rate'] = 0.00;
        } elseif (!isset($payload['pump_rate'])) {
            $payload['pump_rate'] = 0.00;
        }

        $payload = array_intersect_key($payload, array_flip($tableColumns));

        DB::transaction(function () use ($payload) {
            if (empty($payload['order_no'])) {
                $plantId = (int) ($payload['plant_id'] ?? session('active_plant_id'));
                Plant::whereKey($plantId)->lockForUpdate()->firstOrFail();
                $details = SalesOrder::generateOrderNo($plantId, $payload['customer_id'] ?? null, $payload['prefix'] ?? 'SO');
                $payload['prefix'] = $details['prefix'];
                $payload['order_no'] = $details['next_number'];
            }

            SalesOrder::create($payload);
        });

        return redirect()->back()->with('success', 'Sales Order created successfully.');
    }

    public function show(SalesOrder $salesorder)
    {
        $this->authorizeModule('menu');
        $this->ensurePlantScope($salesorder);
        
        $salesorder->load([
            'customer', 
            'site', 
            'mixDesign.items.product', 
            'mixDesign.items.uom', 
            'mixDesign.concrete_grade',
            'customerPO.patron',
            'customerPO',
            'customerPO.site',
            'customerPO.quotation.items.mixDesign'
        ]);

        if ($salesorder->customer_po_id && $salesorder->customerPO) {
            $po = $salesorder->customerPO;
            
            $salesorder->customer_id = $salesorder->customer_id ?? $po->patron_id;
            $salesorder->site_id = $salesorder->site_id ?? $po->site_id;
            $salesorder->concrete_pump = $salesorder->concrete_pump ?? $po->concrete_pump;
            $salesorder->sales_executive_id = $salesorder->sales_executive_id ?? $po->sales_executive_id;
            $salesorder->is_tax_inclusive = $salesorder->is_tax_inclusive ?? $po->is_tax_inclusive;
            
            if ($po->quotation && $po->quotation->items && $po->quotation->items->isNotEmpty()) {
                $firstItem = $po->quotation->items->first();
                $salesorder->mix_design_id = $salesorder->mix_design_id ?? $firstItem->mix_design_id;
                $salesorder->total_qty = $salesorder->total_qty ?? $firstItem->quantity;
            }
        }

        return response()->json($salesorder);
    }

    public function update(UpdateSalesOrderRequest $request, SalesOrder $salesorder)
    {
        \App\Support\DocumentNumberAccess::validate($request, 'SALES_ORDER', 'order_no', $salesorder);
        $this->authorizeModule('edit');
        $this->ensurePlantScope($salesorder);
        
        $payload = $request->validated();
        
        $tableColumns = Schema::getColumnListing('mm_sales_orders');
        $hasPlantIdColumn = in_array('plant_id', $tableColumns, true);
        $hasTotalQtyColumn = in_array('total_qty', $tableColumns, true);
        $hasLegacyQtyColumn = in_array('quantity', $tableColumns, true);

        if ($hasPlantIdColumn) {
            $payload['plant_id'] = $salesorder->plant_id;
        }

        if ($hasLegacyQtyColumn && !$hasTotalQtyColumn && isset($payload['total_qty'])) {
            $payload['quantity'] = $payload['total_qty'];
        }

        if (array_key_exists('pump_rate', $payload) && ($payload['pump_rate'] === null || $payload['pump_rate'] === '')) {
            $payload['pump_rate'] = 0.00;
        }


        $payload = collect($payload)->only($tableColumns)->toArray();

        DB::transaction(function () use ($payload, $salesorder) {
            $salesorder = SalesOrder::lockForUpdate()->findOrFail($salesorder->id);
            if ($salesorder->batches()->withTrashed()->exists() || $salesorder->dispatches()->exists()) {
                abort_unless(auth()->user()->isContextAdministrator(), 403, 'Only an administrator can edit this order after batching.');
            }
            $salesorder->update($payload);
            $salesorder->refreshProduction();
        });

        return redirect()->back()->with('success', 'Sales Order updated successfully.');
    }

    public function destroy(SalesOrder $salesorder)
    {
        $this->authorizeModule('delete');
        $this->ensurePlantScope($salesorder);

        DB::transaction(function () use ($salesorder) {
            $salesorder = SalesOrder::lockForUpdate()->findOrFail($salesorder->id);
            if ($salesorder->batches()->withTrashed()->exists() || $salesorder->dispatches()->exists()) {
                throw \Illuminate\Validation\ValidationException::withMessages([
                    'sales_order' => 'This sales order cannot be deleted after a batch or dispatch has been created.',
                ]);
            }
            $salesorder->delete();
        });

        return redirect()->back()->with('success', 'Sales Order deleted successfully.');
    }

    private function ensurePlantScope(SalesOrder $salesOrder): void
    {
        if (Schema::hasColumn('mm_sales_orders', 'plant_id') && (int) $salesOrder->plant_id !== (int) session('active_plant_id')) {
            abort(403, 'You can only manage sales orders from the active plant.');
        }
    }
}
