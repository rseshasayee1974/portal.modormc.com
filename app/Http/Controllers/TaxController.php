<?php

namespace App\Http\Controllers;

use App\Models\Tax;
use App\Models\Entity;
use App\Models\Ledger;
use Illuminate\Http\Request;
use Inertia\Inertia;
use App\Http\Controllers\Concerns\AuthorizesModule;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class TaxController extends Controller
{
    use AuthorizesModule;
    protected string $module = 'taxes';

    public function index()
    {
        $this->authorizeModule('menu');
        
        return Inertia::render('Taxes/Index', [
            'taxes'      => Tax::with(['parent', 'plant'])->where('plant_id', session('active_plant_id'))->latest()->get(),
            'parentTaxes'=> Tax::whereNull('parent_id')->select('id', 'tax_name')->where('plant_id', session('active_plant_id'))->latest()->get(),
           
            'ledgers'    => Ledger::select('id', 'title')->where('plant_id', session('active_plant_id'))->latest()->get(),
            'taxGroups' => ['GST', 'CGST', 'SGST', 'IGST', 'TDS', 'TCS', 'CESS', 'OTHER'],
            'taxTypes' => ['sales', 'purchase', 'other sales', 'other purchase', 'others'],
        ]);
    }

    public function store(Request $request)
    {
        $this->authorizeModule('create');
        
        $validated = $request->validate([
            'tax_name' => [
                'required', 
                'string', 
                'max:100', 
                Rule::unique('mm_taxes')->where(fn($q) => $q->where('plant_id', session('active_plant_id')))->whereNull('deleted_at')
            ],
            'tax_type' => 'required|in:sales,purchase,other sales,other purchase,others',
            'tax_group' => 'required|in:GST,CGST,SGST,IGST,TDS,TCS,CESS,OTHER',
            'tax_rate' => 'required|numeric|min:0',
            'parent_id' => 'nullable|exists:mm_taxes,id',
            'account_id' => 'nullable|exists:mm_ledgers,id',
            'status' => 'required|integer',
        ]);

        $plantId = session('active_plant_id');
        $plant = \App\Models\Plant::findOrFail($plantId);

        $validated['plant_id']    = $plantId;
        $validated['entity_id']   = $plant->entity_id;

        Tax::create($validated);

        return redirect()->back()->with('success', 'Tax created successfully.');
    }

    public function update(Request $request, Tax $tax)
    {
        $this->authorizeModule('edit');
        
        $validated = $request->validate([
            'tax_name' => [
                'required', 
                'string', 
                'max:100', 
                Rule::unique('mm_taxes')
                    ->ignore($tax->id)
                    ->where(fn($q) => $q->where('plant_id', session('active_plant_id')))
                    ->whereNull('deleted_at')
            ],
            'tax_type' => 'required|in:sales,purchase,other sales,other purchase,others',
            'tax_group' => 'required|in:GST,CGST,SGST,IGST,TDS,TCS,CESS,OTHER',
            'tax_rate' => 'required|numeric|min:0',
            'parent_id' => 'nullable|exists:mm_taxes,id',
            'account_id' => 'nullable|exists:mm_ledgers,id',
            'status' => 'required|integer',
        ]);

        $tax->update($validated);

        return redirect()->back()->with('success', 'Tax updated successfully.');
    }

    public function destroy(Tax $tax)
    {
        $this->authorizeModule('delete');
        $tax->delete();

        return redirect()->back()->with('success', 'Tax deleted successfully.');
    }
}
