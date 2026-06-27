<?php

namespace App\Http\Controllers;

use App\Models\PrintTemplate;
use App\Models\PrintTemplateSetting;
use App\Services\Audit\AuditLogger;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Illuminate\Support\Facades\Auth;

class PrintTemplateController extends Controller
{
    public function index()
    {
        // $this->authorize('viewAny', PrintTemplate::class); // Enable if policy exists
        
        $templates = PrintTemplate::all();
        $settings = PrintTemplateSetting::where('plant_id', session('active_plant_id'))
            ->get()
            ->keyBy('module_key');

        return Inertia::render('TemplateManager/Index', [
            'templates' => $templates,
            'settings' => $settings,
            'modules' => $this->getPrintableModules()
        ]);
    }

    public function assign(Request $request)
    {
        $request->validate([
            'module_key'        => 'required|string',
            'print_template_id' => 'required|exists:mm_print_templates,id',
        ]);

        $plantId = session('active_plant_id');

        // Capture old assignment before overwriting
        $existing = PrintTemplateSetting::where('module_key', $request->module_key)
            ->where('plant_id', $plantId)
            ->first();

        $oldTemplateId   = $existing?->print_template_id;
        $oldTemplateName = $oldTemplateId
            ? PrintTemplate::find($oldTemplateId)?->name
            : null;

        $newTemplate = PrintTemplate::findOrFail($request->print_template_id);

        PrintTemplateSetting::updateOrCreate(
            [
                'module_key' => $request->module_key,
                'plant_id'   => $plantId,
            ],
            [
                'print_template_id' => $request->print_template_id,
            ]
        );

        // Audit log
        app(AuditLogger::class)->log('ASSIGN', null, [
            'module_name'   => 'print_template',
            'entity_type'   => PrintTemplateSetting::class,
            'plant_id'      => $plantId,
            'description'   => sprintf(
                'Template assigned for module "%s": "%s" → "%s"',
                $request->module_key,
                $oldTemplateName ?? '(none)',
                $newTemplate->name
            ),
            'old_values'    => [
                'module_key'        => $request->module_key,
                'print_template_id' => $oldTemplateId,
                'template_name'     => $oldTemplateName,
            ],
            'new_values'    => [
                'module_key'        => $request->module_key,
                'print_template_id' => $newTemplate->id,
                'template_name'     => $newTemplate->name,
            ],
            'changed_fields' => ['print_template_id'],
        ]);

        return redirect()->back()->with('success', 'Template assigned successfully.');
    }

    public function preview(PrintTemplate $template)
    {
        return Inertia::render('TemplateManager/Preview', [
            'template' => $template,
            'dummyData' => \App\Services\PrintDataFormatter::dummy($template->category)
        ]);
    }

    /**
     * Show customization UI for a module.
     */
    public function customize(string $module)
    {
        $plantId = session('active_plant_id');
        $settings = \App\Services\PrintDataFormatter::getCustomSettings($plantId, $module);
        
        // Find the module config to get its name
        $moduleConfig = collect($this->getPrintableModules())->firstWhere('key', $module);

        return Inertia::render('TemplateManager/Customize', [
            'moduleKey' => $module,
            'moduleName' => $moduleConfig['name'] ?? ucfirst($module),
            'initialSettings' => $settings,
        ]);
    }

    /**
     * Save custom settings.
     */
    public function saveCustomization(Request $request, string $module)
    {
        $plantId = session('active_plant_id');

        // Capture previous settings for diff
        $existing = \App\Models\CustomSetting::where('plant_id', $plantId)
            ->where('module_name', $module)
            ->first();

        $oldSettings = $existing?->settings;

        \App\Models\CustomSetting::updateOrCreate(
            [
                'plant_id'    => $plantId,
                'module_name' => $module,
            ],
            [
                'settings'  => $request->settings,
                'module_id' => 0,
            ]
        );

        // Audit log
        app(AuditLogger::class)->log('UPDATE', null, [
            'module_name'    => 'print_template',
            'entity_type'    => \App\Models\CustomSetting::class,
            'plant_id'       => $plantId,
            'description'    => sprintf('Customization fields updated for module "%s"', $module),
            'old_values'     => [
                'module_name' => $module,
                'settings'    => $oldSettings,
            ],
            'new_values'     => [
                'module_name' => $module,
                'settings'    => $request->settings,
            ],
            'changed_fields' => ['settings'],
            'metadata'       => [
                'customize_url' => request()->url(),
            ],
        ]);

        return redirect()->back()->with('success', 'Customization saved successfully.');
    }

    private function getPrintableModules()
    {
        return [
            ['key' => 'invoices',           'name' => 'Invoices',           'templates' => ['standard', 'elite', 'modern', 'compact', 'indian_gst', 'standard_indigo', 'minimalist_lite', 'formal_gst']],
            ['key' => 'purchase_orders',    'name' => 'Purchase Orders',    'templates' => ['standard', 'elite', 'modern', 'spreadsheet', 'tallysheet', 'compact', 'indian_gst']],
            ['key' => 'purchase_bills',     'name' => 'Purchase Bills',     'templates' => ['standard', 'elite', 'modern', 'compact', 'indian_gst']],
            ['key' => 'quotations',         'name' => 'Quotations',         'templates' => ['standard', 'elite', 'modern', 'compact']],
            ['key' => 'delivery_challans',  'name' => 'Delivery Challans',  'templates' => ['standard', 'elite', 'modern', 'compact', 'spreadsheet', 'delivery_challan_a4']],
            ['key' => 'credit_notes',       'name' => 'Credit Notes',       'templates' => ['standard', 'elite']],
            ['key' => 'statements',         'name' => 'Account Statements', 'templates' => ['tallysheet']],
            ['key' => 'gst_invoices',       'name' => 'GST Invoices',       'templates' => ['indian_gst', 'formal_gst']],
        ];
    }

}
