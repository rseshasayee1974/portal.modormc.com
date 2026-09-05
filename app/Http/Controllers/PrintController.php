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
        $realId = $id;
        try { $realId = decrypt($id); } catch (\Exception $e) { }

        if ($module === 'ewaybills' || $module === 'ewaybill') {
            return app(\App\Http\Controllers\EwayBillController::class)->print($request, $realId);
        }

        // 1. Set module for authorization check
        $this->module = $module === 'delivery_challans' ? 'sales_orders' : $module;
        // Authorization is governed by plant scoping and role-based invoice duplication rules in resolveData()

        // 2. Resolve Model and Data with strict plant scoping
        $data = $this->resolveData($module, $id);
         
        if (!$data) {
            abort(404, "Module or Record not found, or you do not have permission to access it in this plant.");
        }
        // Handle first-time invoice printing vs duplicate invoice printing
        if ($module === 'invoices') {
            $invoice = \App\Models\Invoice::find($realId);
            if ($invoice) {
                $user = auth()->user();
                $isAdmin = $user && method_exists($user, 'hasRole') && (
                    $user->hasRole('Saas Owner') || 
                    $user->hasRole('Platform Admin') || 
                    $user->hasRole('Super Admin') || 
                    $user->hasRole('Admin') || 
                    $user->hasRole('Super Administrator') ||
                    $user->hasRole('Administrator') 
                );
                $forceParam = $request->query('force');

                if ($isAdmin) {
                    // Administrators can choose to print Original or Duplicate at any time
                    if ($forceParam === 'duplicate') {
                        $data['is_duplicate'] = 1;
                    } else {
                        $data['is_duplicate'] = 0;
                    }
                } else {
                    // For non-admin users:
                    // Only the FIRST print yields the Original invoice ($invoice->is_duplicate == 0).
                    // All subsequent prints are strictly Duplicate.
                    if ((int)$invoice->is_duplicate === 0) {
                        $data['is_duplicate'] = 0;
                    } else {
                        $data['is_duplicate'] = 1;
                    }
                }

                if ((int)$invoice->is_duplicate === 0) {
                    $invoice->update(['is_duplicate' => 1]);
                }
            }
        }

        // Log the print action for ALL modules
        $user = auth()->user();
        $subType = null;
        $reference = $data['doc_no'] ?? null;
        
        if ($module === 'invoices' && isset($invoice)) {
            $subType = $invoice->invoice_type ?? null;
        }

        \Illuminate\Support\Facades\DB::table('mm_document_print_logs')->insert([
            'document_type' => $module,
            'document_sub_type' => $subType,
            'document_id' => $realId ?? 0,
            'document_reference' => $reference,
            'user_id' => $user ? $user->id : 0,
            'user_name' => $user ? ($user->name ?? $user->email ?? $user->username ?? 'System') : 'System',
            'action' => $action,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // 3. Resolve Template (Either forced by request or from DB settings)
        // Request can override template for testing: ?template=elite
        $templateKey = $request->get('template') ?: PrintDataFormatter::resolveTemplateKey($module, session('active_plant_id'));
        $view = PrintDataFormatter::resolveView($templateKey);

        // 3. Render
        if ($action === 'view') {
            return view($view, ['data' => $data, 'invoice' => $data['invoice'] ?? null]);
        }

        // 4. Download PDF
        $pdf = Pdf::loadView($view, ['data' => $data, 'is_pdf' => true])->setPaper('a4', 'portrait');
        
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
        $activePlantId = session('active_plant_id') ?: 1;

        if (in_array(strtolower($id), ['dummy', 'sample', '0', 'preview'])) {
            $data = PrintDataFormatter::dummy($module);
            $data['settings'] = PrintDataFormatter::getCustomSettings($activePlantId, $module);
            return $data;
        }

        $realId = $id;
        // Optional: try decryption for backward compatibility with older links
        try {
            $realId = decrypt($id);
        } catch (\Exception $e) { }

        $data = null;
        switch ($module) {
            case 'purchase_orders':
                $model = \App\Models\PurchaseOrder::where('id', $realId)
                    ->where('plant_id', $activePlantId)
                    ->first();
                $data = $model ? PrintDataFormatter::fromPurchaseOrder($model) : null;
                break;

            case 'invoices':
                $model = \App\Models\Invoice::where('id', $realId)
                    ->where('plant_id', $activePlantId)
                    ->first();
                if ($model) {
                    $data = PrintDataFormatter::fromInvoice($model);
                    $moduleKey = $model->invoice_type === 'bill' ? 'purchase_bills' : 'invoices';
                    $defaultSettings = PrintDataFormatter::getDefaultSettings($moduleKey);
                    $data['doc_title'] = $defaultSettings['pdf']['labels']['invoice_title'];
                }
                break;

            case 'billings':
                $model = \App\Models\Invoice::where('id', $realId)
                    ->where('plant_id', $activePlantId)
                    ->first();
                if ($model) {
                    $data = PrintDataFormatter::fromInvoice($model);
                    $data['doc_title'] = 'MANUAL BILL';
                }
                break;

            case 'purchase_bills':
                $model = \App\Models\Invoice::where('id', $realId)
                    ->where('plant_id', $activePlantId)
                    ->first();
                if ($model) {
                    $data = PrintDataFormatter::fromInvoice($model);
                    $data['doc_title'] = 'PURCHASE BILL';
                    $data['settings'] = PrintDataFormatter::getCustomSettings($activePlantId, 'purchase_bills');
                }
                break;

            case 'quotations':
                $model = \App\Models\Quotation::where('id', $realId)
                    ->where('plant_id', $activePlantId)
                    ->first();
                $data = $model ? PrintDataFormatter::fromQuotation($model) : null;
                break;

            case 'customer_pos':
                $model = \App\Models\CustomerPO::where('id', $realId)
                    ->where('plant_id', $activePlantId)
                    ->first();
                $data = $model ? PrintDataFormatter::fromCustomerPO($model) : null;
                break;

            case 'sales_orders':
                $model = \App\Models\SalesOrder::where('id', $realId)
                    ->where('plant_id', $activePlantId)
                    ->first();
                $data = $model ? PrintDataFormatter::fromSalesOrder($model) : null;
                break;

            case 'delivery_challans':
                $model = \App\Models\Batch::where('id', $realId)
                    ->whereHas('workOrder', fn ($q) => $q->where('plant_id', $activePlantId))
                    ->first();
                $data = $model ? PrintDataFormatter::fromDeliveryChallan($model) : null;
                break;

            default:
                $data = null;
        }

        if (!$data) {
            $data = PrintDataFormatter::dummy($module);
            $data['settings'] = PrintDataFormatter::getCustomSettings($activePlantId, $module);
        }

        return $data;
    }
}