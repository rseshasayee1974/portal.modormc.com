<?php

namespace App\Http\Controllers;

use App\Models\CustomSetting;
use Illuminate\Http\Request;
use Inertia\Inertia;

class CustomSettingController extends Controller
{
    public function index()
    {
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
            'customSettings' => $customSettings->toArray(),
            'plantId' => $plantId,
            'plantName' => $plant ? $plant->name : 'Unknown Plant'
        ]);
    }

    public function store(Request $request)
    {
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

        app(\App\Services\Audit\AuditLogger::class)->log('CREATE', $customSetting, [
            'description' => "Initialized custom settings for module [{$module}]",
            'new_values' => ['settings' => []],
        ]);

        return redirect()->back()->with('success', 'Module settings initialized successfully.');
    }

    public function update(Request $request)
    {
        $plantId = session('active_plant_id');
        if (!$plantId) {
            return redirect()->back()->with('error', 'Plant session expired.');
        }

        $module = $request->input('module', 'batching');
        $settings = $request->input('settings', []);

        $customSetting = CustomSetting::where('plant_id', $plantId)
            ->where('module_name', $module)
            ->first();

        if ($customSetting) {
            $oldSettings = $customSetting->settings;
            $customSetting->update(['settings' => $settings]);

            app(\App\Services\Audit\AuditLogger::class)->log('UPDATE', $customSetting, [
                'description' => "Updated custom settings for module [{$module}]",
                'old_values' => ['settings' => $oldSettings],
                'new_values' => ['settings' => $settings],
                'changed_fields' => ['settings'],
            ]);
        } else {
            $customSetting = CustomSetting::create([
                'plant_id' => $plantId,
                'module_name' => $module,
                'settings' => $settings,
                'module_id' => 0 // Providing a default for the ID field
            ]);

            app(\App\Services\Audit\AuditLogger::class)->log('CREATE', $customSetting, [
                'description' => "Created custom settings for module [{$module}]",
                'new_values' => ['settings' => $settings],
                'changed_fields' => ['settings'],
            ]);
        }

        return redirect()->back()->with('success', 'Custom settings updated successfully.');
    }

    public function destroy(CustomSetting $customsetting)
    {
        $plantId = session('active_plant_id');
        if ((int)$customsetting->plant_id !== (int)$plantId) {
            abort(403, 'Unauthorized.');
        }

        $oldSettings = $customsetting->settings;
        $module = $customsetting->module_name;
        $customsetting->delete();

        app(\App\Services\Audit\AuditLogger::class)->log('DELETE', $customsetting, [
            'description' => "Deleted custom settings for module [{$module}]",
            'old_values' => ['settings' => $oldSettings],
        ]);

        return redirect()->back()->with('success', 'Module settings deleted successfully.');
    }
}
