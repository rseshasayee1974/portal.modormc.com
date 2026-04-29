<?php

namespace App\Http\Controllers;

use App\Models\SalesOrder;
use App\Models\Dispatch;
use App\Models\Quotation;
use Illuminate\Http\Request;
use App\Http\Controllers\Concerns\AuthorizesModule;
use Illuminate\Support\Facades\DB;

class SalesOrderController extends Controller
{
    use AuthorizesModule;
    protected string $module = 'sales_orders';

    public function store(Request $request)
    {
        $this->authorizeModule('create');
        
        $validated = $request->validate([
            'quotation_id' => 'required|exists:mm_quotations,id',
            'patron_id' => 'required|exists:mm_patrons,id',
            'site_id' => 'required|exists:mm_sites,id',
            'order_date' => 'required|date',
        ]);

        $plantId = session('active_plant_id') ?: 1;
        $validated['plant_id'] = $plantId;
        $validated['status'] = SalesOrder::STATUS_CONFIRMED;

        SalesOrder::create($validated);
        
        Quotation::where('id', $validated['quotation_id'])->update(['status' => Quotation::STATUS_ACCEPTED]);

        return redirect()->back()->with('success', 'Sales Order materialized from Quotation.');
    }
}
