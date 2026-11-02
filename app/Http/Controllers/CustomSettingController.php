<?php

namespace App\Http\Controllers;

use App\Models\CustomSetting;
use Illuminate\Http\Request;
use Inertia\Inertia;
use App\Http\Controllers\Concerns\AuthorizesModule;

class CustomSettingController extends Controller
{
    use AuthorizesModule;
    protected string $module = 'custom_setting';
    public function index()
    {
        $this->authorizeModule('menu');
        
        $plantId = session('active_plant_id');
        if (!$plantId) {
            return redirect()->back()->with('error', 'Please select a plant first.');
        }

        // Get all custom settings for this plant
        $customSettings = CustomSetting::where('plant_id', $plantId)->get();
        $batchingSettings = $customSettings->firstWhere('module_name', 'batching');

        $numberingSettings = $batchingSettings?->settings ?? [];
        foreach (['invoice', 'billing'] as $numberModule) {
            $keys = array_map(fn ($part) => $numberModule . '_number_' . $part, ['format', 'padding', 'start']);
            $moduleSettings = $customSettings->firstWhere('module_name', $numberModule)?->settings ?? [];
            $numberingSettings = array_replace($numberingSettings, array_intersect_key($moduleSettings, array_flip($keys)));
        }
        $plant = \App\Models\Plant::find($plantId);

        return Inertia::render('Settings/CustomSetting', [
            'batchingSettings' => $numberingSettings,
            'batchSheetLogoUrl' => !empty($batchingSettings?->settings['batch_sheet_logo_path'])
                ? \Illuminate\Support\Facades\Storage::disk('public')->url($batchingSettings->settings['batch_sheet_logo_path']) : null,
            'batchSheetDrivers' => array_values(array_map(fn ($driver) => [
                'value' => $driver->getDriverCode(), 'label' => $driver->getDriverName(),
            ], app(\App\Services\BatchSheet\Drivers\PlantDriverRegistry::class)->getDrivers())),
            'customSettings' => $customSettings->toArray(),
            'plantId' => $plantId,
            'plantName' => $plant ? $plant->name : 'Unknown Plant'
        ]);
    }

    public function store(Request $request)
    {
        $this->authorizeModule('create');
        $plantId = session('active_plant_id');
        if (!$plantId) {
            return redirect()->back()->with('error', 'Plant session expired.');
        }

        $validated = $request->validate([
            'module' => 'required|string|max:100',
        ]);

        $module = $validated['module'];

        // Create with default settings if not exists
        $customSetting = CustomSetting::firstOrCreate(
            ['plant_id' => $plantId, 'module_name' => $module],
            ['module_id' => 0, 'settings' => []]
        );

        return redirect()->back()->with('success', 'Module settings initialized successfully.');
    }

    public function update(Request $request)
    {
        $this->authorizeModule('edit');
        $plantId = session('active_plant_id');
        if (!$plantId) {
            return redirect()->back()->with('error', 'Plant session expired.');
        }

        $module = $request->input('module', 'batching');
        $request->validate([
            'settings' => ['required', 'array'],
            'settings.batch_series_enabled' => ['sometimes', 'boolean'],
            'settings.default_batch_series' => ['sometimes', 'in:B,P'],
            'settings.invoice_number_format' => ['sometimes', 'in:0,1,ledger,prefix_number_fy'],
            'settings.billing_number_format' => ['sometimes', 'in:0,1,ledger,prefix_number_fy'],
            'settings.invoice_number_padding' => ['sometimes', 'integer', 'min:0', 'max:10'],
            'settings.billing_number_padding' => ['sometimes', 'integer', 'min:0', 'max:10'],
            'settings.invoice_number_start' => ['sometimes', 'integer', 'min:1', 'max:9999999999'],
            'settings.billing_number_start' => ['sometimes', 'integer', 'min:1', 'max:9999999999'],
            'settings.batch_creation_source' => ['sometimes', 'in:sales_order,batch_schedule,both'],
            'settings.batch_sheet_driver' => ['sometimes', 'nullable', 'string', \Illuminate\Validation\Rule::in(
                array_keys(app(\App\Services\BatchSheet\Drivers\PlantDriverRegistry::class)->getDrivers())
            )],
            'batch_sheet_logo' => ['nullable', 'file', 'image', 'mimes:png,jpg,jpeg', 'max:2048'],
            'remove_batch_sheet_logo' => ['sometimes', 'boolean'],
        ]);
        $settings = $request->input('settings', []);
        foreach (['invoice', 'billing'] as $numberModule) {
            $key = $numberModule . '_number_format';
            if (array_key_exists($key, $settings)) {
                $settings[$key] = in_array($settings[$key], [1, '1', 'prefix_number_fy'], true) ? 1 : 0;
            }
        }
        // Multipart uploads encode numbers as strings; keep toggle settings numeric.
        foreach (['manual_weight', 'weight_mandatory', 'with_inventory', 'camera', 'sheet_upload',
            'hide_batch_form', 'target_to_actual', 'auto_carry_pump', 'print_delivery_ingredients',
            'quotation_price_list', 'batch_series_enabled'] as $key) {
            if (array_key_exists($key, $settings)) {
                $settings[$key] = $request->boolean('settings.' . $key) ? 1 : 0;
            }
        }

        $customSetting = CustomSetting::where('plant_id', $plantId)
            ->where('module_name', $module)
            ->first();

        if ($module === 'batching') {
            foreach (['batch_series_enabled', 'default_batch_series'] as $key) {
                if (!array_key_exists($key, $settings) && array_key_exists($key, $customSetting?->settings ?? [])) {
                    $settings[$key] = $customSetting->settings[$key];
                }
            }
        }

        if (in_array($module, ['invoice', 'billing'], true)) {
            $settings = array_replace($customSetting?->settings ?? [], $settings);
        }

        if ($module === 'batching') {
            foreach (['invoice', 'billing'] as $numberModule) {
                $existingNumberSettings = array_replace($customSetting?->settings ?? [], CustomSetting::getForModule($plantId, $numberModule));
                foreach (['format', 'padding', 'start'] as $part) {
                    $key = $numberModule . '_number_' . $part;
                    if (!array_key_exists($key, $settings) && array_key_exists($key, $existingNumberSettings)) {
                        $settings[$key] = $existingNumberSettings[$key];
                    }
                }
            }
        }

        // Older settings clients may omit the driver; preserve the plant assignment.
        if ($module === 'batching' && !array_key_exists('batch_sheet_driver', $settings)
            && isset($customSetting?->settings['batch_sheet_driver'])) {
            $settings['batch_sheet_driver'] = $customSetting->settings['batch_sheet_driver'];
        }

        // File paths belong to the server; ordinary settings saves preserve the current logo.
        unset($settings['batch_sheet_logo_path']);
        $logoPath = $customSetting?->settings['batch_sheet_logo_path'] ?? null;
        if ($module === 'batching') {
            if ($request->hasFile('batch_sheet_logo')) {
                $logoPath = $request->file('batch_sheet_logo')->store("batch-sheet-logos/{$plantId}", 'public');
            } elseif ($request->boolean('remove_batch_sheet_logo')) {
                $logoPath = null;
            }
        }
        if ($logoPath) $settings['batch_sheet_logo_path'] = $logoPath;

        \Illuminate\Support\Facades\DB::transaction(function () use ($customSetting, $settings, $module, $plantId) {
            if ($customSetting) {
                $oldSettings = $customSetting->settings;
                $customSetting->update(['settings' => $settings]);
            } else {
                $customSetting = CustomSetting::create([
                    'plant_id' => $plantId,
                    'module_name' => $module,
                    'settings' => $settings,
                    'module_id' => 0 // Providing a default for the ID field
                ]);
            }

            if ($module === 'batching') {
                foreach (['invoice', 'billing'] as $numberModule) {
                    $keys = array_map(fn ($part) => $numberModule . '_number_' . $part, ['format', 'padding', 'start']);
                    $numberSettings = array_intersect_key($settings, array_flip($keys));
                    if (!$numberSettings) continue;
                    $current = CustomSetting::firstOrNew(['plant_id' => $plantId, 'module_name' => $numberModule]);
                    $current->module_id = $current->module_id ?? 0;
                    $current->settings = array_replace($current->settings ?? [], $numberSettings);
                    $current->save();
                }
            }

        });

        return redirect()->back()->with('success', 'Custom settings updated successfully.');
    }

    public function destroy(CustomSetting $customsetting)
    {
        $this->authorizeModule('delete');
        $plantId = session('active_plant_id');
        if ((int)$customsetting->plant_id !== (int)$plantId) {
            abort(403, 'Unauthorized.');
        }

        $oldSettings = $customsetting->settings;
        $module = $customsetting->module_name;
        $customsetting->delete();

        return redirect()->back()->with('success', 'Module settings deleted successfully.');
    }
}
