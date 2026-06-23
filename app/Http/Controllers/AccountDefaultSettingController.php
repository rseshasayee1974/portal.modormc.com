<?php

namespace App\Http\Controllers;

use App\Models\AccountDefaultSetting;
use App\Models\Ledger;
use App\Models\Module;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Illuminate\Support\Facades\DB;

class AccountDefaultSettingController extends Controller
{
    public function index()
    {
        $plantId = session('active_plant_id');
        
        $modules = Module::where('is_active', true)->get();
        $ledgers = Ledger::where('plant_id', $plantId)->where('status', 1)->get();
        $settings = AccountDefaultSetting::where('plant_id', $plantId)->get();

        return Inertia::render('Settings/AccountDefaults', [
            'modules' => $modules,
            'ledgers' => $ledgers,
            'settings' => $settings,
        ]);
    }

   public function store(Request $request)
{
    $plantId = session('active_plant_id');

    $validated = $request->validate([
        'settings' => 'nullable|array',
  
        // 'settings.*.module_id' => 'required|exists:mm_modules,id',
        // 'settings.*.setting_key' => 'required|string',
        // 'settings.*.ledger_id' => 'nullable|exists:mm_ledgers,id',
    ]);

    DB::transaction(function () use ($plantId, $validated) {

        
        foreach (($validated['settings'] ?? []) as $item) {

            // Skip empty ledger selections
            if (empty($item['ledger_id'])) {
                continue;
            }

            $module = Module::findOrFail($item['module_id']);

            AccountDefaultSetting::updateOrCreate(
                [
                    'plant_id' => $plantId,
                    'module_id' => $item['module_id'],
                    'setting_key' => $item['setting_key'],
                ],
                [
                    'module_name' => $module->module_name,
                    'ledger_id' => $item['ledger_id'],
                    'is_active' => true,
                ]
            );
        }
        
    });

    return redirect()->back()->with(
        'success',
        'Accounting settings updated successfully.'
    );
}
}