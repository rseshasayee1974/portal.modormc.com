<?php

namespace App\Http\Controllers;

use App\Models\PaymentStatus;
use Illuminate\Http\Request;
use Inertia\Inertia;
use App\Http\Controllers\Concerns\AuthorizesModule;

class PaymentStatusController extends Controller
{
    use AuthorizesModule;
    protected string $module = 'payment_statuses';

    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $this->authorizeModule('menu');
        return Inertia::render('PaymentStatuses/Index', [
            'paymentStatuses' => PaymentStatus::all()
        ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $this->authorizeModule('create');
        $validated = $request->validate([
            'status_name' => 'required|string|max:50|unique:mm_payment_statuses,status_name',
        ]);

        $paymentStatus = PaymentStatus::create($validated);

        if ($request->wantsJson()) {
            return response()->json([
                'paymentStatus' => $paymentStatus,
                'message' => 'Payment Status created successfully.'
            ]);
        }

        return redirect()->back()->with('success', 'Payment Status created successfully.');
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, PaymentStatus $paymentStatus)
    {
        $this->authorizeModule('edit');
        $validated = $request->validate([
            'status_name' => 'required|string|max:50|unique:mm_payment_statuses,status_name,' . $paymentStatus->id,
        ]);

        $paymentStatus->update($validated);

        if ($request->wantsJson()) {
            return response()->json([
                'paymentStatus' => $paymentStatus,
                'message' => 'Payment Status updated successfully.'
            ]);
        }

        return redirect()->back()->with('success', 'Payment Status updated successfully.');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(PaymentStatus $paymentStatus)
    {
        $this->authorizeModule('delete');
        $paymentStatus->delete();

        if (request()->wantsJson()) {
            return response()->json([
                'message' => 'Payment Status deleted successfully.'
            ]);
        }

        return redirect()->back()->with('success', 'Payment Status deleted successfully.');
    }
}
