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
        ]);

        DB::transaction(function () use ($plantId, $validated) {
            foreach (($validated['settings'] ?? []) as $item) {
                $moduleId = $item['module_id'];
                $settingKey = $item['setting_key'];
                $ledgerId = $item['ledger_id'] ?? null;

                $module = Module::findOrFail($moduleId);

                // Find existing record, including soft-deleted ones
                $setting = AccountDefaultSetting::withTrashed()
                    ->where([
                        'plant_id' => $plantId,
                        'module_id' => $moduleId,
                        'setting_key' => $settingKey,
                    ])
                    ->first();

                if ($setting) {
                    if ($setting->trashed()) {
                        $setting->restore();
                    }
                    $setting->update([
                        'module_name' => $module->module_name,
                        'ledger_id' => $ledgerId,
                        'is_active' => true,
                    ]);
                } else {
                    AccountDefaultSetting::create([
                        'plant_id' => $plantId,
                        'module_id' => $moduleId,
                        'setting_key' => $settingKey,
                        'module_name' => $module->module_name,
                        'ledger_id' => $ledgerId,
                        'is_active' => true,
                    ]);
                }
            }
        });

        return redirect()->back()->with(
            'success',
            'Accounting settings updated successfully.'
        );
    }
}