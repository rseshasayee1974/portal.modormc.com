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

        // Get settings for batching module
        $batchingSettings = CustomSetting::where('plant_id','=',$plantId)
            ->where('mm_module_name', 'batching')
            ->first();

        $plant = \App\Models\Plant::find($plantId);

        return Inertia::render('Settings/CustomSetting', [
            'batchingSettings' => $batchingSettings ? $batchingSettings->settings : [],
            'plantId' => $plantId,
            'plantName' => $plant ? $plant->name : 'Unknown Plant'
        ]);
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
            ->where('mm_module_name', $module)
            ->first();

        if ($customSetting) {
            $customSetting->update(['settings' => $settings]);
        } else {
            CustomSetting::create([
                'plant_id' => $plantId,
                'mm_module_name' => $module,
                'settings' => $settings,
                'mm_module_id' => 0 // Providing a default for the ID field
            ]);
        }

        return redirect()->back()->with('success', 'Custom settings updated successfully.');
    }
}
