<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\AuthorizesModule;
use App\Services\PrintDataFormatter;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class PrintController extends Controller
{
    use AuthorizesModule;
    
    // Module key mapping for authorization
    protected string $module = ''; 
    /**
     * Unified method to handle printing/downloading for any supported module.
     * 
     * Route: /print/{module}/{id}/{action}
     * Example: /print/purchase_orders/12/view
     * Example: /print/invoices/5/download
     */
    public function handle(Request $request, string $module, string $id, string $action = 'view')
    {
        // 1. Set module for authorization check
        $this->module = $module === 'delivery_challans' ? 'sales_orders' : $module;
        $this->authorizeModule('show');

        // 2. Resolve Model and Data with strict plant scoping
        $data = $this->resolveData($module, $id);
         
        if (!$data) {
            abort(404, "Module or Record not found, or you do not have permission to access it in this plant.");
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
        
        // Sanitize filename to avoid slashes which break download headers
        $safeDocNo = str_replace(['/', '\\'], '-', $data['doc_no']);
        $filename = Str::slug($data['doc_title'] . '_' . $safeDocNo) . '.pdf';
        
        return $pdf->download($filename);
    }

    /**
     * Maps module keys to their respective data formatting logic.
     */
    protected function resolveData(string $module, string $id): ?array
    {
        $activePlantId = session('active_plant_id');
        if (!$activePlantId) {
            abort(403, "No active plant selected.");
        }

        $realId = $id;
        // Optional: try decryption for backward compatibility with older links
        try {
            $realId = decrypt($id);
        } catch (\Exception $e) { }

        switch ($module) {
            case 'purchase_orders':
                $model = \App\Models\PurchaseOrder::where('id', $realId)
                    ->where('plant_id', $activePlantId)
                    ->first();
                return $model ? PrintDataFormatter::fromPurchaseOrder($model) : null;

            case 'invoices':
                $model = \App\Models\Invoice::where('id', $realId)
                    ->where('plant_id', $activePlantId)
                    ->first();
                return $model ? PrintDataFormatter::fromInvoice($model) : null;

            case 'billings':
                $model = \App\Models\Invoice::where('id', $realId)
                    ->where('plant_id', $activePlantId)
                    ->first();
                if ($model) {
                    $data = PrintDataFormatter::fromInvoice($model);
                    $data['doc_title'] = 'MANUAL BILL';
                   
                    return $data;
                }
                return null;
            case 'purchase_bills':
                $model = \App\Models\Invoice::where('id', $realId)
                    ->where('plant_id', $activePlantId)
                    ->first();
                if ($model) {
                    $data = PrintDataFormatter::fromInvoice($model);
                    $data['doc_title'] = 'PURCHASE BILL';
                    $data['settings'] = PrintDataFormatter::getCustomSettings($activePlantId, 'purchase_bills');
                    return $data;
                }
                return null;

            case 'quotations':
                $model = \App\Models\Quotation::where('id', $realId)
                    ->where('plant_id', $activePlantId)
                    ->first();
                return $model ? PrintDataFormatter::fromQuotation($model) : null;

            case 'delivery_challans':
                $model = \App\Models\Batch::where('id', $realId)
                    ->whereHas('workOrder', fn ($q) => $q->where('plant_id', $activePlantId))
                    ->first();
                return $model ? PrintDataFormatter::fromDeliveryChallan($model) : null;

            default:
                return null;
        }
    }
}