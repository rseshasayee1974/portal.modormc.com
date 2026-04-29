<?php

namespace App\Http\Controllers;

use App\Models\InvoiceStatus;
use Illuminate\Http\Request;
use Inertia\Inertia;
use App\Http\Controllers\Concerns\AuthorizesModule;

class InvoiceStatusController extends Controller
{
    use AuthorizesModule;
    protected string $module = 'invoice_statuses';
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $this->authorizeModule('menu');
        return Inertia::render('InvoiceStatuses/Index', [
            'invoiceStatuses' => InvoiceStatus::all()
        ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $this->authorizeModule('create');
        $validated = $request->validate([
            'status_name' => 'required|string|max:100|unique:mm_invoice_statuses,status_name',
        ]);

        $invoiceStatus = InvoiceStatus::create($validated);

        if ($request->wantsJson()) {
            return response()->json([
                'invoiceStatus' => $invoiceStatus,
                'message' => 'Invoice Status created successfully.'
            ]);
        }

        return redirect()->route('invoicestatuses.index')->with('success', 'Invoice Status created successfully.');
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, InvoiceStatus $invoiceStatus)
    {
        $this->authorizeModule('edit');
        $validated = $request->validate([
            'status_name' => 'required|string|max:100|unique:mm_invoice_statuses,status_name,' . $invoiceStatus->id,
        ]);

        $invoiceStatus->update($validated);

        if ($request->wantsJson()) {
            return response()->json([
                'invoiceStatus' => $invoiceStatus,
                'message' => 'Invoice Status updated successfully.'
            ]);
        }

        return redirect()->route('invoicestatuses.index')->with('success', 'Invoice Status updated successfully.');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(InvoiceStatus $invoiceStatus)
    {
        $this->authorizeModule('delete');
        $invoiceStatus->delete();

        if (request()->wantsJson()) {
            return response()->json([
                'message' => 'Invoice Status deleted successfully.'
            ]);
        }

        return redirect()->route('invoicestatuses.index')->with('success', 'Invoice Status deleted successfully.');
    }
}
