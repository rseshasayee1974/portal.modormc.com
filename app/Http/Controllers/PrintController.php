<?php

namespace App\Http\Controllers;

use App\Services\PrintDataFormatter;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class PrintController extends Controller
{
    /**
     * Unified method to handle printing/downloading for any supported module.
     * 
     * Route: /print/{module}/{id}/{action}
     * Example: /print/purchase_orders/12/view
     * Example: /print/invoices/5/download
     */
    public function handle(Request $request, string $module, string $id, string $action = 'view')
    {
        // 1. Resolve Model and Data
        $data = $this->resolveData($module, $id);
        
        if (!$data) {
            abort(404, "Module or Record not found.");
        }

        // 2. Resolve Template (Either forced by request or from DB settings)
        // Request can override template for testing: ?template=elite
        $templateKey = $request->get('template') ?: PrintDataFormatter::resolveTemplateKey($module, session('active_plant_id'));
        $view = PrintDataFormatter::resolveView($templateKey);

        // 3. Render
        if ($action === 'view') {
            return view($view, ['data' => $data]);
        }

        // 4. Download PDF
        $pdf = Pdf::loadView($view, ['data' => $data]);
        $filename = Str::slug($data['doc_title'] . '_' . $data['doc_no']) . '.pdf';
        
        return $pdf->download($filename);
    }

    /**
     * Maps module keys to their respective data formatting logic.
     */
    protected function resolveData(string $module, string $id): ?array
    {
        try {
            $decryptedId = decrypt($id);
            $realId = $decryptedId;
        } catch (\Exception $e) {
            $realId = $id;
        }

        switch ($module) {
            case 'purchase_orders':
                $model = \App\Models\PurchaseOrder::find($realId);
                return $model ? PrintDataFormatter::fromPurchaseOrder($model) : null;

            case 'invoices':
                $model = \App\Models\Invoice::find($realId);
                return $model ? PrintDataFormatter::fromInvoice($model) : null;

            case 'quotations':
                $model = \App\Models\Quotation::find($realId);
                return $model ? PrintDataFormatter::fromQuotation($model) : null;

            // Add more modules here (delivery_notes, credit_notes, etc.)
            default:
                return null;
        }
    }
}
