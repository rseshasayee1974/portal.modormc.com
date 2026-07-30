<?php

namespace App\Http\Controllers;

use App\Models\AccountDefaultSetting;
use App\Models\Ledger;
use App\Models\Module;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Illuminate\Support\Facades\DB;
use App\Http\Controllers\Concerns\AuthorizesModule;

class AccountDefaultSettingController extends Controller
{
    use AuthorizesModule;
    protected string $module = 'setting';
    public function index()
    {
        $this->authorizeModule('menu');
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
        $this->authorizeModule('create');
        $plantId = session('active_plant_id');
    abort_unless($plantId, 403, 'No active plant selected');

        $validated = $request->validate([
            'settings' => 'nullable|array',
            'settings.*.module_id' => 'required|exists:mm_module,id',
            'settings.*.setting_key' => 'required|string|max:100',
            'settings.*.ledger_id' => 'nullable|exists:mm_ledgers,id',

        ]);

$settings = $validated['settings'] ?? [];
        // preloading modules once 
        $modules = Module::whereIn('id',collect($settings)->pluck('module_id'))
        ->get()
        ->keyBy('id');
        DB::transaction(function () use ($plantId   , $settings , $modules) {
            foreach ($settings  as $item) {
                // $moduleId = $item['module_id'];
                // $settingKey = $item['setting_key'];
                // $ledgerId = $item['ledger_id'] ?? null;
                $module = $modules[$item['module_id']];

                // Find existing record, including soft-deleted ones
                // $setting = AccountDefaultSetting::withTrashed()
                //     ->where([
                //         'plant_id' => $plantId,
                //         'module_id' => $moduleId,
                //         'setting_key' => $settingKey,
                //     ])
                //     ->first();

                // if ($setting) {
                //     if ($setting->trashed()) {
                //         $setting->restore();
                //     }
                //     $setting->update([
                //         'module_name' => $module->module_name,
                //         'ledger_id' => $ledgerId,
                //         'is_active' => true,
                //     ]);
                // } else {
                    AccountDefaultSetting::updateOrCreate(
                        [
                            'plant_id' => $plantId,
                            'module_id' => $item['module_id'],
                            'setting_key' => $item['setting_key'],
                        ],
                        [
                            'module_name' => $module->module_name,
                            'ledger_id' => $item['ledger_id'] ?? null,
                            'is_active' => true,
                            'deleted_at' => null,
                        ]
                    );
                // }
            }
             // optional: deactivate settings not sent this time
        // AccountDefaultSetting::where('plant_id', $plantId)
        //     ->whereNotIn('id', $touchedIds)
        //     ->update(['is_active' => false]);s
        });

        return redirect()->back()->with(
            'success',
            'Accounting settings updated successfully.'
        );
    }
}