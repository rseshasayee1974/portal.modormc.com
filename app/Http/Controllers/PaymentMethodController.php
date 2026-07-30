<?php

namespace App\Http\Controllers;

use App\Models\PaymentMethod;
use Illuminate\Http\Request;
use Inertia\Inertia;
use App\Http\Controllers\Concerns\AuthorizesModule;

class PaymentMethodController extends Controller
{
    use AuthorizesModule;
    protected string $module = 'payment_method';

    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $this->authorizeModule('menu');
        return Inertia::render('PaymentMethods/Index', [
            'paymentMethods' => PaymentMethod::orderBy('name', 'asc')->get()
        ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $this->authorizeModule('create');
        $validated = $request->validate([
            'name' => 'required|string|max:100|unique:mm_payment_methods,name',
            'description' => 'nullable|string|max:255',
            'is_active' => 'required|boolean',
        ]);

        $paymentMethod = PaymentMethod::create($validated);

        if ($request->wantsJson()) {
            return response()->json([
                'paymentMethod' => $paymentMethod,
                'message' => 'Payment Method created successfully.'
            ]);
        }

        return redirect()->route('paymentmethods.index')->with('success', 'Payment Method created successfully.');
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, PaymentMethod $paymentmethod)
    {
        $this->authorizeModule('edit');
        $validated = $request->validate([
            'name' => 'required|string|max:100|unique:mm_payment_methods,name,' . $paymentmethod->id,
            'description' => 'nullable|string|max:255',
            'is_active' => 'required|boolean',
        ]);

        $paymentmethod->update($validated);

        if ($request->wantsJson()) {
            return response()->json([
                'paymentMethod' => $paymentmethod,
                'message' => 'Payment Method updated successfully.'
            ]);
        }
        return redirect()->route('paymentmethods.index')->with('success', 'Payment Method updated successfully.');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(PaymentMethod $paymentmethod)
    {
        $this->authorizeModule('delete');
        $paymentmethod->delete();

        if (request()->wantsJson()) {
            return response()->json([
                'message' => 'Payment Method deleted successfully.'
            ]);
        }

        return redirect()->route('paymentmethods.index')->with('success', 'Payment Method deleted successfully.');
    }
}
