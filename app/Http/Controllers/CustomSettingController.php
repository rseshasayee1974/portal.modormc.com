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
        $settings = $request->input('settings', []);

        $customSetting = CustomSetting::where('plant_id', $plantId)
            ->where('module_name', $module)
            ->first();

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
