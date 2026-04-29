<?php

namespace App\Http\Controllers;

use App\Models\PrintTemplate;
use App\Models\PrintTemplateSetting;
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
            ->keyBy('mm_module_key');

        return Inertia::render('TemplateManager/Index', [
            'templates' => $templates,
            'settings' => $settings,
            'modules' => $this->getPrintableModules()
        ]);
    }

    public function assign(Request $request)
    {
        $request->validate([
            'module_key' => 'required|string',
            'print_template_id' => 'required|exists:mm_print_templates,id',
        ]);

        PrintTemplateSetting::updateOrCreate(
            [
                'module_key' => $request->module_key,
                'plant_id' => session('active_plant_id'),
            ],
            [
                'print_template_id' => $request->print_template_id,
            ]
        );

        return redirect()->back()->with('success', 'Template assigned successfully.');
    }

    public function preview(PrintTemplate $template)
    {
        return Inertia::render('TemplateManager/Preview', [
            'template' => $template,
            'dummyData' => \App\Services\PrintDataFormatter::dummy($template->category)
        ]);
    }

    private function getPrintableModules()
    {
        return [
            ['key' => 'invoices',           'name' => 'Invoices',           'templates' => ['standard', 'elite', 'modern', 'compact', 'indian_gst']],
            ['key' => 'purchase_orders',    'name' => 'Purchase Orders',    'templates' => ['standard', 'elite', 'modern', 'spreadsheet', 'tallysheet', 'compact', 'indian_gst']],
            ['key' => 'quotations',         'name' => 'Quotations',         'templates' => ['standard', 'elite', 'modern', 'compact']],
            ['key' => 'delivery_notes',     'name' => 'Delivery Notes',     'templates' => ['standard', 'spreadsheet', 'compact']],
            ['key' => 'credit_notes',       'name' => 'Credit Notes',       'templates' => ['standard', 'elite']],
            ['key' => 'statements',         'name' => 'Account Statements', 'templates' => ['tallysheet']],
            ['key' => 'gst_invoices',       'name' => 'GST Invoices',       'templates' => ['indian_gst']],
        ];
    }

}
