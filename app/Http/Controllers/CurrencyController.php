<?php

namespace App\Http\Controllers;

use App\Models\Currency;
use Illuminate\Http\Request;
use Inertia\Inertia;
use App\Http\Controllers\Concerns\AuthorizesModule;

class CurrencyController extends Controller
{
    use AuthorizesModule;
    protected string $module = 'currencies';
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $this->authorizeModule('menu');
        return Inertia::render('Currencies/Index', [
            'currencies' => Currency::all()
        ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $this->authorizeModule('create');
        $validated = $request->validate([
            'currency_name' => 'required|string|max:100|unique:mm_currencies,currency_name',
            'currency_code' => 'required|string|max:10|unique:mm_currencies,currency_code',
        ]);

        $currency = Currency::create($validated);

        if ($request->wantsJson()) {
            return response()->json([
                'currency' => $currency,
                'message' => 'Currency created successfully.'
            ]);
        }

        return redirect()->route('currencies.index')->with('success', 'Currency created successfully.');
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Currency $currency)
    {
        $this->authorizeModule('edit');
        $validated = $request->validate([
            'currency_name' => 'required|string|max:100|unique:mm_currencies,currency_name,' . $currency->id,
            'currency_code' => 'required|string|max:10|unique:mm_currencies,currency_code,' . $currency->id,
        ]);

        $currency->update($validated);

        if ($request->wantsJson()) {
            return response()->json([
                'currency' => $currency,
                'message' => 'Currency updated successfully.'
            ]);
        }

        return redirect()->route('currencies.index')->with('success', 'Currency updated successfully.');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Currency $currency)
    {
        $this->authorizeModule('delete');
        $currency->delete();

        if (request()->wantsJson()) {
            return response()->json([
                'message' => 'Currency deleted successfully.'
            ]);
        }

        return redirect()->route('currencies.index')->with('success', 'Currency deleted successfully.');
    }
}
