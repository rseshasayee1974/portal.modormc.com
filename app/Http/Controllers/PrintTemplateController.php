<?php

namespace App\Http\Controllers;

use App\Models\PrintTemplate;
use App\Models\PrintTemplateSetting;
use Illuminate\Http\Request;
use Inertia\Inertia;
use App\Http\Controllers\Concerns\AuthorizesModule;
use Illuminate\Support\Facades\Auth;

class PrintTemplateController extends Controller
{
    use AuthorizesModule;
    protected string $module = 'template';
    
    public function index()
    {
        $this->authorizeModule('menu');
        
        $templates = PrintTemplate::all();
        $settings = PrintTemplateSetting::where('plant_id', session('active_plant_id') ?: null)
            ->get()
            ->keyBy(fn ($setting) => strtolower($setting->module_key));

        return Inertia::render('TemplateManager/Index', [
            'templates' => $templates,
            'settings' => $settings,
            'modules' => $this->getPrintableModules()
        ]);
    }

    public function assign(Request $request)
    {
        $this->authorizeModule('edit');
        $request->validate([
            'module_key'        => 'required|string',
            'print_template_id' => 'required|exists:mm_print_templates,id',
        ]);

        $plantId = session('active_plant_id') ?: null;
        $entityId = session('active_entity_id') ?: null;

        // Capture old assignment before overwriting
        $existing = PrintTemplateSetting::where('module_key', strtolower($request->module_key))
            ->where('plant_id', $plantId)
            ->first();

        $oldTemplateId   = $existing?->print_template_id;
        $oldTemplateName = $oldTemplateId
            ? PrintTemplate::find($oldTemplateId)?->name
            : null;

        $newTemplate = PrintTemplate::findOrFail($request->print_template_id);

        PrintTemplateSetting::updateOrCreate(
            [
                'module_key' => strtolower($request->module_key),
                'plant_id'   => $plantId,
            ],
            [
                'entity_id'         => $entityId,
                'print_template_id' => $request->print_template_id,
            ]
        );

        // Audit log was removed

        return redirect()->back()->with('success', 'Template assigned successfully.');
    }

    public function preview(PrintTemplate $template)
    {
        $this->authorizeModule('menu');
        return Inertia::render('TemplateManager/Preview', [
            'template' => $template,
            'dummyData' => \App\Services\PrintDataFormatter::dummy($template->category)
        ]);
    }

    /**
     * Render live Blade template HTML for customizer iframe preview.
     */
    public function renderLivePreview(Request $request, string $module)
    {
        $this->authorizeModule('menu');
        $plantId = session('active_plant_id') ?: 1;
        $data = \App\Services\PrintDataFormatter::dummy($module);

        if ($request->has('settings') && is_array($request->settings)) {
            $data['settings'] = array_replace_recursive($data['settings'], $request->settings);
            
            if (!empty($request->settings['pdf']['labels']['invoice_title'])) {
                $data['doc_title'] = $request->settings['pdf']['labels']['invoice_title'];
            }
        }

        $templateKey = $request->get('template_key') 
            ?: \App\Services\PrintDataFormatter::resolveTemplateKey($module, $plantId);

        $view = \App\Services\PrintDataFormatter::resolveView($templateKey);

        return response()->view($view, ['data' => $data, 'is_preview' => true]);
    }

    /**
     * Show customization UI for a module.
     */
    public function customize(string $module)
    {
        $this->authorizeModule('edit');
        $plantId = session('active_plant_id') ?: 1;
        $settings = \App\Services\PrintDataFormatter::getCustomSettings($plantId, $module);
        $assignedKey = \App\Services\PrintDataFormatter::resolveTemplateKey($module, $plantId);
        
        // Find the module config to get its name
        $moduleConfig = collect($this->getPrintableModules())->firstWhere('key', $module);
        $availableTemplates = $moduleConfig['templates'] ?? ['standard', 'elite', 'modern', 'compact', 'indian_gst'];

        $templateSettingsMap = [];
        foreach ($availableTemplates as $tKey) {
            $templateSettingsMap[$tKey] = \App\Services\PrintDataFormatter::getCustomSettings($plantId, $module, $tKey);
        }

        $dummyData = \App\Services\PrintDataFormatter::dummy($module);

        return Inertia::render('TemplateManager/Customize', [
            'moduleKey' => $module,
            'moduleName' => $moduleConfig['name'] ?? ucfirst($module),
            'initialSettings' => $settings,
            'templateSettingsMap' => $templateSettingsMap,
            'assignedTemplateKey' => $assignedKey,
            'availableTemplates' => $availableTemplates,
            'dummyData' => $dummyData,
        ]);
    }

    /**
     * Save custom settings.
     */
    public function saveCustomization(Request $request, string $module)
    {
        $this->authorizeModule('edit');
        $plantId = session('active_plant_id');
        $entityId = session('active_entity_id');

        // Save per-template settings map if supplied
        if ($request->has('template_settings_map') && is_array($request->template_settings_map)) {
            foreach ($request->template_settings_map as $tKey => $tSettings) {
                \App\Models\CustomSetting::updateOrCreate(
                    [
                        'plant_id'    => $plantId,
                        'module_name' => $module . '_' . strtolower($tKey),
                    ],
                    [
                        'settings'  => $tSettings,
                        'module_id' => 0,
                    ]
                );
            }
        }

        // Save base module custom settings
        if ($request->has('settings')) {
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
        }

        if ($request->has('template_key') && !empty($request->template_key)) {
            $tpl = PrintTemplate::where('key', $request->template_key)->first();
            if ($tpl) {
                PrintTemplateSetting::updateOrCreate(
                    [
                        'module_key' => strtolower($module),
                        'plant_id'   => $plantId,
                    ],
                    [
                        'entity_id'         => $entityId,
                        'print_template_id' => $tpl->id,
                    ]
                );
            }
        }

        // Audit log was removed

        return redirect()->back()->with('success', 'Customization saved successfully.');
    }

    private function getPrintableModules()
    {
        return [
            ['key' => 'invoices',           'name' => 'Invoices',           'templates' => ['standard', 'elite', 'modern', 'compact', 'indian_gst', 'standard_indigo', 'minimalist_lite', 'formal_gst']],
            ['key' => 'sales_orders',       'name' => 'Sales Orders',       'templates' => ['standard', 'elite', 'modern', 'compact', 'indian_gst']],
            ['key' => 'purchase_orders',    'name' => 'Purchase Orders',    'templates' => ['standard', 'elite', 'modern', 'spreadsheet', 'tallysheet', 'compact', 'indian_gst']],
            ['key' => 'purchase_bills',     'name' => 'Purchase Bills',     'templates' => ['standard', 'elite', 'modern', 'compact', 'indian_gst']],
            ['key' => 'quotations',         'name' => 'Quotations',         'templates' => ['standard', 'elite', 'modern', 'compact']],
            ['key' => 'customer_pos',       'name' => 'Customer POs',       'templates' => ['standard', 'elite', 'modern', 'compact']],
            ['key' => 'delivery_challans',  'name' => 'Delivery Challans',  'templates' => ['standard', 'elite', 'modern', 'compact', 'spreadsheet', 'delivery_challan_a4']],
            ['key' => 'credit_notes',       'name' => 'Credit Notes',       'templates' => ['standard', 'elite']],
            ['key' => 'statements',         'name' => 'Account Statements', 'templates' => ['tallysheet']],
            ['key' => 'gst_invoices',       'name' => 'GST Invoices',       'templates' => ['indian_gst', 'formal_gst']],
        ];
    }

}
