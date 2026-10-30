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

        $plant = \App\Models\Plant::find($plantId);

        return Inertia::render('Settings/CustomSetting', [
            'batchingSettings' => $batchingSettings ? $batchingSettings->settings : [],
            'batchSheetLogoUrl' => !empty($batchingSettings?->settings['batch_sheet_logo_path'])
                ? \Illuminate\Support\Facades\Storage::disk('public')->url($batchingSettings->settings['batch_sheet_logo_path']) : null,
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
            'settings.batch_creation_source' => ['sometimes', 'in:sales_order,batch_schedule,both'],
            'batch_sheet_logo' => ['nullable', 'file', 'image', 'mimes:png,jpg,jpeg', 'max:2048'],
            'remove_batch_sheet_logo' => ['sometimes', 'boolean'],
        ]);
        $settings = $request->input('settings', []);
        // Multipart uploads encode numbers as strings; keep toggle settings numeric.
        foreach (['manual_weight', 'weight_mandatory', 'with_inventory', 'camera', 'sheet_upload',
            'hide_batch_form', 'target_to_actual', 'auto_carry_pump', 'print_delivery_ingredients',
            'quotation_price_list'] as $key) {
            if (array_key_exists($key, $settings)) {
                $settings[$key] = $request->boolean('settings.' . $key) ? 1 : 0;
            }
        }

        $customSetting = CustomSetting::where('plant_id', $plantId)
            ->where('module_name', $module)
            ->first();

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
