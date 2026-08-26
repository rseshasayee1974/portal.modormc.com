<?php

namespace App\Http\Controllers;

use App\Models\Invoice;
use App\Services\EInvoiceService;
use App\Http\Controllers\Concerns\AuthorizesModule;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Illuminate\Support\Facades\Log;

class EInvoiceController extends Controller
{
    use AuthorizesModule;

    protected string $module = 'e-invoices';
    protected EInvoiceService $eInvoiceService;

    public function __construct(EInvoiceService $eInvoiceService)
    {
        $this->eInvoiceService = $eInvoiceService;
    }

    /**
     * Generate E-Invoice (IRN) for an invoice.
     */
    public function generate(Request $request, Invoice $invoice)
    {
        return $this->einvoiceGenerate($request, $invoice);
    }

    /**
     * Core handler to generate E-Invoice.
     */
    public function einvoiceGenerate(Request $request, Invoice $invoice)
    {
        $this->authorizeModule('edit');

        // Resolve invoice model from route binding or request body
        if (!$invoice->exists) {
            $invoiceId = $request->input('invoice_id') ?? $request->input('id') ?? $request->input('form.id');
            if ($invoiceId) {
                $invoice = Invoice::findOrFail($invoiceId);
            }
        }

        try {
            $transportDetails = $request->all();

            $result = $this->eInvoiceService->generate($invoice, $transportDetails);

            if ($request->wantsJson() && !$request->header('X-Inertia')) {
                return response()->json([
                    'success' => true,
                    'message' => 'E-Invoice generated successfully!',
                    'data'    => $result,
                ]);
            }

            return redirect()->back()->with('success', 'E-Invoice (IRN) generated successfully. IRN: ' . ($result['irn'] ?? ''));

        } catch (ValidationException $ve) {
            if ($request->wantsJson() && !$request->header('X-Inertia')) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation error',
                    'errors'  => $ve->errors(),
                ], 422);
            }
            return redirect()->back()->withErrors($ve->errors());
        } catch (\Exception $e) {
            Log::error('E-Invoice generation error: ' . $e->getMessage(), ['trace' => $e->getTraceAsString()]);

            if ($request->wantsJson() && !$request->header('X-Inertia')) {
                return response()->json([
                    'success' => false,
                    'message' => $e->getMessage(),
                ], 422);
            }
            return redirect()->back()->withErrors(['error' => $e->getMessage()]);
        }
    }

    /**
     * Generate E-Way Bill route alias.
     */
    public function generateEWayBill(Request $request, Invoice $invoice)
    {
        return $this->ewaybillGenerateByIrn($request, $invoice);
    }

    /**
     * Generate E-Way Bill using existing IRN.
     */
    public function ewaybillGenerateByIrn(Request $request, Invoice $invoice)
    {
        $this->authorizeModule('edit');

        if (!$invoice->exists) {
            $invoiceId = $request->input('invoice_id') ?? $request->input('id');
            if ($invoiceId) {
                $invoice = Invoice::findOrFail($invoiceId);
            }
        }

        try {
            $result = $this->eInvoiceService->generateEwayBillByIrn($invoice, $request->all());

            if ($request->wantsJson() && !$request->header('X-Inertia')) {
                return response()->json([
                    'success' => true,
                    'message' => 'E-Way Bill generated successfully!',
                    'data'    => $result,
                ]);
            }

            return redirect()->back()->with('success', 'E-Way Bill generated successfully. EWB No: ' . ($result['eway_bill_no'] ?? ''));

        } catch (ValidationException $ve) {
            if ($request->wantsJson() && !$request->header('X-Inertia')) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation error',
                    'errors'  => $ve->errors(),
                ], 422);
            }
            return redirect()->back()->withErrors($ve->errors());
        } catch (\Exception $e) {
            Log::error('E-Way Bill generation by IRN error: ' . $e->getMessage());

            if ($request->wantsJson() && !$request->header('X-Inertia')) {
                return response()->json([
                    'success' => false,
                    'message' => $e->getMessage(),
                ], 422);
            }
            return redirect()->back()->withErrors(['error' => $e->getMessage()]);
        }
    }

    /**
     * Cancel an E-Invoice (IRN) within 24 hours of generation.
     */
    public function cancel(Request $request, Invoice $invoice)
    {
        $this->authorizeModule('edit');

        if (!$invoice->exists) {
            $invoiceId = $request->input('invoice_id') ?? $request->input('id');
            if ($invoiceId) {
                $invoice = Invoice::findOrFail($invoiceId);
            }
        }

        try {
            $cancelReason = (string)$request->input('cancel_reason', '1');
            $cancelRemarks = (string)$request->input('cancel_remarks', 'Order cancelled');

            $result = $this->eInvoiceService->cancelIrn($invoice, $cancelReason, $cancelRemarks);

            if ($request->wantsJson() && !$request->header('X-Inertia')) {
                return response()->json([
                    'success' => true,
                    'message' => 'E-Invoice (IRN) cancelled successfully!',
                    'data'    => $result,
                ]);
            }

            return redirect()->back()->with('success', 'E-Invoice (IRN) cancelled successfully.');

        } catch (\Exception $e) {
            Log::error('E-Invoice cancel error: ' . $e->getMessage());

            if ($request->wantsJson() && !$request->header('X-Inertia')) {
                return response()->json([
                    'success' => false,
                    'message' => $e->getMessage(),
                ], 422);
            }
            return redirect()->back()->withErrors(['error' => $e->getMessage()]);
        }
    }

    /**
     * Set up demo compliance prerequisites for an invoice (valid sandbox GSTINs, addresses, and HSN codes).
     */
    public function setupDemoCompliance(Request $request, Invoice $invoice)
    {
        $this->authorizeModule('edit');

        if (!$invoice->exists) {
            $invoiceId = $request->input('invoice_id') ?? $request->input('id');
            if ($invoiceId) {
                $invoice = Invoice::findOrFail($invoiceId);
            }
        }

        $plant = $invoice->plant;
        if ($plant) {
            $plant->update([
                'gstin'              => '29AARFB4347G000',
                'einvoice_client_id' => 'Bluefox',
                'einvoice_secret'    => 'Bluefox@123',
            ]);

            $plantAddr = $plant->addresses()->first();
            if ($plantAddr) {
                $plantAddr->update([
                    'state_code' => '29',
                    'zipcode'    => '560100',
                    'city'       => 'Bengaluru',
                ]);
            }
        }

        $partner = $invoice->partner;
        if ($partner) {
            if (empty($partner->gstin) || strlen($partner->gstin) !== 15) {
                $partner->update([
                    'gstin' => '29AABCU9603R1ZM',
                ]);
            }
            $partnerAddr = $partner->addresses()->first() ?: $partner->contacts()->first()?->addresses()->first();
            if ($partnerAddr) {
                $partnerAddr->update([
                    'state_code' => '29',
                    'zipcode'    => '560100',
                    'city'       => 'Bengaluru',
                ]);
            }
        }

        foreach ($invoice->items as $item) {
            $hsn = preg_replace('/[^0-9]/', '', (string)($item->hsn_code ?? ''));
            if (strlen($hsn) < 4 || strlen($hsn) > 8) {
                $item->update(['hsn_code' => '38245015']);
            }
        }

        if ($request->wantsJson() && !$request->header('X-Inertia')) {
            return response()->json([
                'success' => true,
                'message' => 'Demo compliance prerequisites configured successfully!',
            ]);
        }

        return redirect()->back()->with('success', 'Demo compliance data configured successfully.');
    }

    /**
     * Render the Compliance Testing Center view.
     */
    public function testPage(Request $request)
    {
        $this->authorizeModule('view');

        $invoices = Invoice::with(['partner', 'plant'])
            ->latest('id')
            ->take(20)
            ->get()
            ->map(fn($inv) => [
                'id'       => $inv->id,
                'number'   => $inv->full_number,
                'customer' => $inv->partner?->name ?? 'Unknown Customer',
                'amount'   => (float)$inv->total_amount,
                'status'   => $inv->einvoice_status ? 'IRN ' . $inv->einvoice_status : 'Pending',
            ]);

        $plants = \App\Models\Plant::all(['id', 'name', 'gstin', 'einvoice_client_id']);

        $config = config('services.perione');
        $defaultCredentials = [
            'base_url'           => $config['sandbox_base_url'] ?? 'https://staging.perione.in',
            'client_id'          => $config['sandbox_client_id'] ?? 'PEINVSb3aadf99327e3ca03792510397d3136b',
            'client_secret'      => $config['sandbox_client_secret'] ?? 'PEINVS21f24a6a2291dd214d0d81bf23ae8ec7',
            'email'              => $config['sandbox_email'] ?? 'sayee@onemodo.com',
            'gstin'              => '29AARFB4347G000',
            'username'           => 'Bluefox',
            'password'           => 'Bluefox@123',
            'eway_client_id'     => $config['sandbox_client_id'] ?? 'PEINVSb3aadf99327e3ca03792510397d3136b',
            'eway_client_secret' => $config['sandbox_client_secret'] ?? 'PEINVS21f24a6a2291dd214d0d81bf23ae8ec7',
            'eway_gstin'         => '29AARFB4347G000',
            'eway_email'         => 'sayee@onemodo.com',
            'eway_ip'            => '192.168.1.98',
        ];

        return \Inertia\Inertia::render('Compliance/Test', [
            'invoices'           => $invoices,
            'plants'             => $plants,
            'defaultCredentials' => $defaultCredentials,
        ]);
    }

    /**
     * Execute test action from Compliance Testing Center.
     */
    public function testAction(Request $request)
    {
        $this->authorizeModule('edit');

        $action = $request->input('action');
        $invoiceId = $request->input('invoice_id');
        $invoice = Invoice::with(['plant', 'partner', 'items'])->findOrFail($invoiceId);

        $trace = [];

        try {
            if ($action === 'einvoice_generate') {
                $trace[] = ['step' => '1. Authenticate with Gateway', 'timestamp' => now()->toTimeString(), 'data' => ['status' => 'Session authenticated']];
                $result = $this->eInvoiceService->generate($invoice, ['generate_eway' => true, 'veh_no' => 'KA01AB1234', 'distance' => 100]);
                $trace[] = ['step' => '2. IRN Generated', 'timestamp' => now()->toTimeString(), 'data' => $result];

                return response()->json([
                    'success' => true,
                    'message' => 'E-Invoice IRN generated successfully! IRN: ' . $result['irn'],
                    'trace'   => $trace,
                ]);
            } elseif ($action === 'einvoice_cancel') {
                $result = $this->eInvoiceService->cancelIrn($invoice, '1', 'Cancelled by test suite');
                $trace[] = ['step' => '1. Cancelled IRN', 'timestamp' => now()->toTimeString(), 'data' => $result];

                return response()->json([
                    'success' => true,
                    'message' => 'E-Invoice IRN cancelled successfully!',
                    'trace'   => $trace,
                ]);
            } elseif ($action === 'ewaybill_generate') {
                $result = $this->eInvoiceService->generateEwayBillByIrn($invoice, ['veh_no' => 'KA01AB1234', 'distance' => 100]);
                $trace[] = ['step' => '1. E-Way Bill Generated', 'timestamp' => now()->toTimeString(), 'data' => $result];

                return response()->json([
                    'success' => true,
                    'message' => 'E-Way Bill generated successfully! EWB No: ' . $result['eway_bill_no'],
                    'trace'   => $trace,
                ]);
            }

            throw new \Exception("Unsupported test action [{$action}].");
        } catch (\Exception $e) {
            $trace[] = ['step' => 'Action Failed', 'timestamp' => now()->toTimeString(), 'data' => ['error' => $e->getMessage()]];

            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
                'trace'   => $trace,
            ], 422);
        }
    }
}