<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\AuthorizesModule;
use App\Jobs\QueueBulkDocumentExportJob;
use App\Models\{Ledger, Patron};
use App\Services\{BulkInvoicePdfService, PlantContextService};
use App\Services\Reports\BulkDocumentQuery;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

class BulkDocumentReportController extends Controller
{
    use AuthorizesModule;
    protected string $module = 'report';

    public function index()
    {
        app(\App\Services\Reports\ReportPermissions::class)->authorize('bulk_documents');
        return redirect()->route('reports.index', ['module' => 'accounting', 'type' => 'bulk_documents']);
    }

    public function documents(Request $request, BulkDocumentQuery $query, BulkInvoicePdfService $pdf)
    {
        $request->merge([
        'type'     => $request->type ? strtolower($request->type) : $request->type,
        'subtype'  => $request->subtype ? strtolower($request->subtype) : $request->subtype,
        'tax_type' => $request->tax_type ? strtolower($request->tax_type) : $request->tax_type,
    ]);
        $export = $request->isMethod('post');
        app(\App\Services\Reports\ReportPermissions::class)->authorize('bulk_documents', $export ? 'export' : 'view');
        $plant = app(PlantContextService::class)->requirePlantId();
        $filters = $request->validate([
            'start_date' => 'required|date_format:Y-m-d', 'end_date' => 'required|date_format:Y-m-d|after_or_equal:start_date',
            'type' => ['nullable', Rule::in(['invoice', 'bill'])],
            'subtype' => ['nullable', Rule::in(['manual_invoice', 'dispatch_invoice', 'manual_bill', 'vendor_bill'])],
            'patron_id' => 'nullable|integer', 'ledger_id' => 'nullable|integer',
            'tax_type' => ['nullable', Rule::in(['gst', 'igst', 'no_tax'])],
            'reference' => 'nullable|string|max:100',
            'invoice_ids' => 'nullable|array|max:1000',
            'invoice_ids.*' => 'required|integer|min:1|distinct',
        ]);
        $optionsOnly = $request->routeIs('reports.bulk-documents.options');
        if ($optionsOnly) unset($filters['invoice_ids']);
        $matches = $query->build($plant, $filters);
        if ($optionsOnly) {
            return response()->json(['options' => $matches
                ->get(['mm_invoices.id', 'invoice_number', 'prefix', 'invoice_date'])
                ->map(fn ($invoice) => [
                    'id' => $invoice->id,
                    'number' => $invoice->full_number,
                    'date' => $invoice->invoice_date?->format('Y-m-d'),
                ])])->header('Cache-Control', 'private, no-store');
        }
        $count = (clone $matches)->count();
        if ($export) {
            abort_if($count === 0 || $count > 100, 422, 'Choose a date range and filters matching between 1 and 100 documents.');
        }
        $documents = $matches->with(['partner' => fn($q) => $q->withoutGlobalScope('active_operational_status')])->limit(100)->get();
        if (!$export) {
            return response()->json(['count' => $count, 'documents' => $documents->map(fn($d) => [
                'id' => $d->id, 'number' => $d->full_number, 'date' => $d->invoice_date?->format('Y-m-d'),
                'type' => strtolower($d->invoice_type) === 'sales' ? 'Invoice' : 'Bill',
                'subtype' => $d->invoice_label, 'patron' => $d->partner?->legal_name,
                'tax' => $d->tax_amount, 'total' => $d->total_amount,
            ])]);
        }
        $documents->load([
            'plant.entity.bankAccounts',
            'plant.addresses.state',
            'partner.addresses.state',
            'items.tax',
            'items.uom',
            'orderTaxes',
            'einvoiceRelation',
        ]);
        $html = $documents->map(fn($invoice) => view('pdfs.invoices.tax_invoice', [
            'invoice' => $invoice,
            'copy_type' => 'ORIGINAL',
        ])->render())->all();
        return response($pdf->render($html), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="Invoices_Bills_'.now()->format('Ymd_His').'.pdf"',
            'Cache-Control' => 'private, no-store',
        ]);
    }

    /**
     * Trigger asynchronous background ZIP export (supports 1 to 10,000+ documents).
     */
    public function exportZip(Request $request, BulkDocumentQuery $query)
    {
        $request->merge([
            'type'     => $request->type ? strtolower($request->type) : $request->type,
            'subtype'  => $request->subtype ? strtolower($request->subtype) : $request->subtype,
            'tax_type' => $request->tax_type ? strtolower($request->tax_type) : $request->tax_type,
        ]);
        
        app(\App\Services\Reports\ReportPermissions::class)->authorize('bulk_documents', 'export');
        $plant = app(PlantContextService::class)->requirePlantId();
        $filters = $request->validate([
            'start_date' => 'required|date_format:Y-m-d',
            'end_date'   => 'required|date_format:Y-m-d|after_or_equal:start_date',
            'type'       => ['nullable', Rule::in(['invoice', 'bill'])],
            'subtype'    => ['nullable', Rule::in(['manual_invoice', 'dispatch_invoice', 'manual_bill', 'vendor_bill'])],
            'patron_id'  => 'nullable|integer',
            'ledger_id'  => 'nullable|integer',
            'tax_type'   => ['nullable', Rule::in(['gst', 'igst', 'no_tax'])],
            'reference'  => 'nullable|string|max:100',
            'invoice_ids' => 'nullable|array|max:1000',
            'invoice_ids.*' => 'required|integer|min:1|distinct',
        ]);

        $matches = $query->build($plant, $filters);
        $count = (clone $matches)->count();

        abort_if($count === 0, 422, 'No documents matched the selected filters for ZIP export.');

        $statusKey = 'bulk_doc_export_' . Str::uuid();
        app(\App\Services\Reports\ReportPermissions::class)->rememberExport($statusKey, 'bulk_documents', []);

        // Fail visibly if the export service cannot be constructed.
        app(\App\Services\BulkDocumentZipExportService::class);

        Cache::put($statusKey, [
            'status'    => 'queued',
            'progress'  => 0,
            'processed' => 0,
            'total'     => $count,
            'message'   => "Export queued for {$count} documents...",
        ], now()->addHours(2));

        if (config('queue.default') !== 'sync') {
            QueueBulkDocumentExportJob::dispatch($plant, $filters, $statusKey);
        } else {
            // Detached CLI background execution for sync / local environments
            // Keep large multiselections out of the Windows command-line length limit.
            Cache::put($statusKey . ':filters', $filters, now()->addHours(2));
            $filtersEncoded = 'cached';
            $artisanPath = base_path('artisan');
            $phpBinary = PHP_BINARY;
            if (PHP_SAPI !== 'cli' && PHP_SAPI !== 'cli-server') {
                $phpBinary = PHP_BINDIR . DIRECTORY_SEPARATOR . (PHP_OS_FAMILY === 'Windows' ? 'php.exe' : 'php');
            }
            $logPath = str_replace('/', '\\', storage_path('logs/bulk-document-export.log'));

            if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') {
                $cmd = "cmd /c \"\"{$phpBinary}\" \"{$artisanPath}\" reports:export-bulk-documents {$statusKey} {$plant} {$filtersEncoded} >> \"{$logPath}\" 2>&1\"";
                try {
                    $wsh = new \COM("WScript.Shell");
                    $wsh->Run($cmd, 0, false);
                } catch (\Exception $e) {
                    pclose(popen("start \"\" /B " . $cmd, "r"));
                }
            } else {
                exec(escapeshellarg($phpBinary) . ' ' . escapeshellarg($artisanPath)
                    . " reports:export-bulk-documents {$statusKey} {$plant} {$filtersEncoded} >> "
                    . escapeshellarg($logPath) . ' 2>&1 &');
            }
        }

        return response()->json([
            'status'     => true,
            'queued'     => true,
            'status_key' => $statusKey,
            'total'      => $count,
            'message'    => "Bulk export of {$count} documents has been initiated in background.",
        ]);
    }
}
