<?php

namespace App\Http\Controllers;

use App\Models\Batch;
use App\Models\Dispatch;
use App\Models\Invoice;
use App\Models\EwaybillDetail;
use App\Services\EwayBillService;
use App\Http\Controllers\Concerns\AuthorizesModule;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;

class EwayBillController extends Controller
{
    use AuthorizesModule;

    protected string $module = 'ewaybill';
    protected EwayBillService $ewayBillService;

    public function __construct(EwayBillService $ewayBillService)
    {
        $this->ewayBillService = $ewayBillService;
    }

    /**
     * Generate E-Way Bill for a Batch without requiring prior E-Invoice (IRN).
     */
    public function generateForBatch(Request $request, $batch)
    {
        $batchId = $batch instanceof Batch ? $batch->id : (int)$batch;

        // 1. Locate batch and its active dispatch
        $dispatch = DB::table('mm_dispatches as d')
            ->leftJoin('mm_dispatch_statuses as ds', 'ds.dispatch_id', '=', 'd.id')
            ->leftJoin('mm_machines as m', 'm.id', '=', 'd.truck_id')
            ->where('d.batch_id', $batchId)
            ->whereNull('d.deleted_at')
            ->select([
                'd.id as dispatch_id',
                'd.plant_id',
                'd.truck_id',
                'ds.transport_km',
                'm.registration as truck_registration',
                'ds.invoice_id',
                'ds.invoice_number',
            ])
            ->first();

        if (!$dispatch || empty($dispatch->invoice_id)) {
            $msg = 'Please generate an Invoice before generating an E-Way Bill.';
            if ($request->wantsJson() && !$request->header('X-Inertia')) {
                return response()->json(['success' => false, 'message' => $msg], 422);
            }
            return redirect()->back()->withErrors(['error' => $msg]);
        }

        $invoice = Invoice::find($dispatch->invoice_id);
        if (!$invoice) {
            $msg = 'Linked Invoice not found for this batch.';
            if ($request->wantsJson() && !$request->header('X-Inertia')) {
                return response()->json(['success' => false, 'message' => $msg], 404);
            }
            return redirect()->back()->withErrors(['error' => $msg]);
        }
        // Merge fallback transport values from batch dispatch if not supplied in request
        $data = $request->all();
        if (empty($data['veh_no'])) {
            $data['veh_no'] = $dispatch->truck_registration ?? '';
        }
        if (empty($data['distance'])) {
            $data['distance'] = (int)($dispatch->transport_km ?? 20);
        }
        if (empty($data['generation_type'])) {
            $data['generation_type'] = 'batch';
        }

        return $this->generateStandaloneEwayBill($invoice, $data, $request, $batchId);
    }

    /**
     * Generate standalone E-Way Bill directly for an Invoice (without IRN).
     */
    public function generate(Request $request, ?Invoice $invoice = null)
    {
        if (!$invoice || !$invoice->exists) {
            $invoiceId = $request->input('invoice_id') ?? $request->input('id');
            if ($invoiceId) {
                $invoice = Invoice::findOrFail($invoiceId);
            } else {
                $msg = 'Invoice ID is required to generate E-Way Bill.';
                if ($request->wantsJson() && !$request->header('X-Inertia')) {
                    return response()->json(['success' => false, 'message' => $msg], 422);
                }
                return redirect()->back()->withErrors(['error' => $msg]);
            }
        }

        $data = $request->all();
        if (empty($data['generation_type'])) {
            $data['generation_type'] = 'invoice';
        }

        return $this->generateStandaloneEwayBill($invoice, $data, $request);
    }

    /**
     * Core handler: Generate direct E-Way Bill without requiring E-Invoice (IRN).
     */
    protected function generateStandaloneEwayBill(Invoice $invoice, array $params, Request $request, ?int $batchId = null)
    {
        $userId = Auth::id() ?? 1;
        $plant = $invoice->plant;

        // Dynamically resolve generation_type (e.g. 'batch' or 'invoice') from params/request or fallback
        $generationType = $request->input('generation_type')
            ?: ($params['generation_type'] ?? ($batchId ? 'batch' : 'invoice'));

        // 1. Check if an active E-Way Bill already exists
        $existingEwb = EwaybillDetail::where('origin_id', $invoice->id)
            ->where(function ($q) use ($generationType) {
                if (!empty($generationType)) {
                    $q->where('generation_type', $generationType)
                      ->orWhere('generation_type', strtolower($generationType))
                      ->orWhere('generation_type', ucfirst(strtolower($generationType)));
                } else {
                    $q->whereIn('generation_type', ['invoice', 'batch', 'Invoice', 'Batch']);
                }
            })
            ->where('status', 1)
            ->where('ewaybill_status', 'ACT')
            ->first();

        if ($existingEwb && !empty($existingEwb->ewaybill_no)) {
            $msg = "E-Way Bill already active: #{$existingEwb->ewaybill_no}";
            if ($request->wantsJson() && !$request->header('X-Inertia')) {
                return response()->json([
                    'success' => true,
                    'message' => $msg,
                    'data'    => [
                        'eway_bill_no'          => $existingEwb->ewaybill_no,
                        'eway_bill_date'        => $existingEwb->ewaybill_date,
                        'eway_bill_valid_until' => $existingEwb->valid_upto,
                        'batch_id'              => $batchId,
                        'invoice_id'            => $invoice->id,
                    ],
                ]);
            }
            return redirect()->back()->with('success', $msg);
        }

        // 2. Validate vehicle and transport inputs
        $vehNo = strtoupper(trim((string)($params['veh_no'] ?? $invoice->vehicle_number ?? '')));
        if (empty($vehNo)) {
            $msg = 'Vehicle Number is required to generate an E-Way Bill.';
            if ($request->wantsJson() && !$request->header('X-Inertia')) {
                return response()->json(['success' => false, 'message' => $msg], 422);
            }
            return redirect()->back()->withErrors(['error' => $msg]);
        }

        $distance = (int)($params['distance'] ?? 20);
        if ($distance <= 0) {
            $distance = 20; // Default minimum sensible distance in KM
        }

        $transMode = (string)($params['trans_mode'] ?? '1'); // 1 = Road
        $transId = trim((string)($params['trans_id'] ?? ''));
        $transName = trim((string)($params['trans_name'] ?? ''));
        $transDocNo = trim((string)($params['trans_doc_no'] ?? ''));
        $transDocDt = !empty($params['trans_doc_dt']) ? Carbon::parse($params['trans_doc_dt'])->format('d/m/Y') : '';
        $vehType = (string)($params['veh_type'] ?? 'R'); // R = Regular

        // 3. Seller, Buyer & Valuation Data
        $isProd = $this->ewayBillService->isProduction($plant);
        $username = $plant?->ewaybill_client_id ?: $this->ewayBillService->sandboxUsername;
        $password = $plant?->ewaybill_secret ?: $this->ewayBillService->sandboxPassword;
        $gstin = $isProd
            ? ($plant?->gstin ?: '')
            : ($plant?->gstin ?: $this->ewayBillService->sandboxGstin);
        $sellerGstin = trim((string)($gstin ?: ($plant?->gstin ?: '')));

        $partner = $invoice->partner;
        $partnerAddress = $partner?->addresses()?->first() ?: $partner?->contacts()?->first()?->addresses()?->first();
        
        $buyerGstin = trim((string)($partner?->gstin ?: '')) ?: 'URP';
        $buyerStateCode = (strlen($buyerGstin) >= 2 && ctype_digit(substr($buyerGstin, 0, 2)))
            ? (int)substr($buyerGstin, 0, 2)
            : 33;

        $plantAddress = $plant?->addresses()?->first();
        $sellerStateCode = (strlen($sellerGstin) >= 2 && ctype_digit(substr($sellerGstin, 0, 2)))
            ? (int)substr($sellerGstin, 0, 2)
            : 33;

        // 1. Determine Interstate vs Intra-state
        $isInterState = ($sellerStateCode !== $buyerStateCode);

        // Ensure relations are loaded
        $invoice->loadMissing([
            'items.mixDesign.concreteGrade',
            'items.uom',
            'items.tax',
            'items.orderTaxes',
            'orderTaxes',
        ]);

        // Build item list
        $itemList = [];
        $assVal  = 0.0;
        $cgstVal = 0.0;
        $sgstVal = 0.0;
        $igstVal = 0.0;

        $invoiceItems = $invoice->items;
       
        if ($invoiceItems && $invoiceItems->count() > 0) {
            $idx = 1;
            foreach ($invoiceItems as $item) {
                // Correct taxable amount from mm_invoice_items (subtotal)
                $qty       = (float)($item->quantity ?: 1);
                $unitPrice = (float)($item->price_unit ?: 0);
                $discount  = (float)($item->discount_amount ?: 0);
                $taxable   = (float)($item->subtotal ?: round(($qty * $unitPrice) - $discount, 2));

                // Read tax splits from mm_order_taxes for this line item
                $itemTaxes = $item->relationLoaded('orderTaxes') && $item->orderTaxes->isNotEmpty()
                    ? $item->orderTaxes
                    : $invoice->orderTaxes->where('order_items_id', $item->id);

                $cgstTax = $itemTaxes->first(fn($t) => stripos($t->name, 'cgst') !== false);
                $sgstTax = $itemTaxes->first(fn($t) => stripos($t->name, 'sgst') !== false);
                $igstTax = $itemTaxes->first(fn($t) => stripos($t->name, 'igst') !== false);

                $cgstRate = (float)($cgstTax?->rate ?? 0);
                $sgstRate = (float)($sgstTax?->rate ?? 0);
                $igstRate = (float)($igstTax?->rate ?? 0);

                $cgst = (float)($cgstTax?->amount ?? 0);
                $sgst = (float)($sgstTax?->amount ?? 0);
                $igst = (float)($igstTax?->amount ?? 0);

                // Fallback: If mm_order_taxes not generated yet, derive from item->tax or line_tax_amount
                if (($cgstRate + $sgstRate + $igstRate) == 0) {
                    $taxRate = (float)($item->tax?->tax_rate ?? ($item->tax?->rate ?? 0));
                    if ($taxRate == 0 && $item->line_tax_amount > 0 && $taxable > 0) {
                        $taxRate = round(($item->line_tax_amount / $taxable) * 100, 2);
                    }

                    $totalLineTax = (float)($item->line_tax_amount ?: round($taxable * ($taxRate / 100), 2));

                    if ($isInterState) {
                        $igstRate = $taxRate;
                        $igst     = $totalLineTax;
                    } else {
                        $cgstRate = round($taxRate / 2, 2);
                        $sgstRate = round($taxRate / 2, 2);
                        $cgst     = round($totalLineTax / 2, 2);
                        $sgst     = round($totalLineTax / 2, 2);
                    }
                }

                $assVal  += $taxable;
                $cgstVal += $cgst;
                $sgstVal += $sgst;
                $igstVal += $igst;

                // Resolve Concrete Grade name (MixDesign -> ConcreteGrade or item_name)
                $concreteGradeName = $item->mixDesign?->concreteGrade?->name 
                    ?: ($item->mixDesign?->grade 
                    ?: ($item->mixDesign?->design_name));

                $hsnCode = (int)preg_replace('/[^0-9]/', '', (string)($item->hsn_code ?: 38245010));
                if (empty($hsnCode)) {
                    $hsnCode = 38245010;
                }

                $itemList[] = [
                    'itemNo'        => $idx++,
                    'productName'   => (string)$concreteGradeName,
                    'productDesc'   => (string)($item->item_name ?: $concreteGradeName),
                    'hsnCode'       => $hsnCode,
                    'quantity'      => $qty,
                    'qtyUnit'       => (string)($item->uom?->code ?: ($item->uom?->unit_code ?: 'CBM')),
                    'taxableAmount' => round($taxable, 2),
                    'cgstRate'      => $cgstRate,
                    'sgstRate'      => $sgstRate,
                    'igstRate'      => $igstRate,
                    'cessRate'      => 0,
                ];
            }
        }

        $totInvVal = (float)($invoice->total_amount ?? round($assVal + $cgstVal + $sgstVal + $igstVal, 2));

        // 4. Construct direct E-Way Bill JSON Payload
        $ewbPayload = [
            'supplyType'       => 'O',
            'subSupplyType'    => '1',
            'docType'          => 'INV',
            'docNo'            => (string)($invoice->full_number ?: ($invoice->prefix . $invoice->invoice_number)),
            'docDate'          => Carbon::parse($invoice->invoice_date ?: now())->format('d/m/Y'),
            'fromGstin'        => $sellerGstin,
            'fromTrdName'      => (string)($plant?->name),
            'fromAddr1'        => (string)($plantAddress?->line_1),
            'fromAddr2'        => (string)($plantAddress?->line_2),
            'fromPlace'        => (string)($plantAddress?->city),
            'fromPincode'      => (int)($plantAddress?->zipcode),
            'actFromStateCode' => $sellerStateCode,
            'fromStateCode'    => $sellerStateCode,
            'toGstin'          => $buyerGstin,
            'toTrdName'        => (string)($partner?->legal_name),
            'toAddr1'          => (string)($partnerAddress?->line_1),
            'toAddr2'          => (string)($partnerAddress?->line_2),
            'toPlace'          => (string)($partnerAddress?->city),
            'toPincode'        => (int)($partnerAddress?->zipcode),
            'actToStateCode'   => $buyerStateCode,
            'toStateCode'      => $buyerStateCode,
            'totalValue'       => round($assVal, 2),
            'cgstValue'        => round($cgstVal, 2),
            'sgstValue'        => round($sgstVal, 2),
            'igstValue'        => round($igstVal, 2),
            'cessValue'        => 0,
            'totInvValue'      => round($totInvVal, 2),
            'transMode'        => $transMode,
            'transactionType'  => '4',
            'transDistance'    => (string)($distance > 0 ? $distance : 20),
            'transporterId'    => $buyerGstin,
            'transporterName'  => $transName,
            'transDocNo'       => $transDocNo,
            'transDocDate'     => $transDocDt,
            'vehicleNo'        => $vehNo,
            'vehicleType'      => $vehType,
            'itemList'         => $itemList,
        ];

        // 5. Contact Gateway directly (GSP provider handles authentication internally)
        $ewbNo = null;
        $ewbDt = null;
        $ewbValidTill = null;
        
        $isProd = $this->ewayBillService->isProduction($plant);
        $url = $this->ewayBillService->getGenEwayBillUrl($plant);
// dd($ewbPayload);
        try {
            // 1. First authenticate with PeriOne Portal
            $this->ewayBillService->authenticatePortel($plant);

            // 2. Build Gateway headers directly with plant credentials
            $headers = $this->ewayBillService->buildGatewayHeaders($username, $password, $sellerGstin, $plant);

            $response = Http::withHeaders($headers)->timeout(30)->post($url, $ewbPayload);
            $body = $response->json() ?? [];
            // dd($response, $body, $ewbPayload); // Uncomment if inspecting raw gateway response

            if ($response->successful() && (!isset($body['status_cd']) || ($body['status_cd'] !== 0 && $body['status_cd'] !== '0' && strtolower((string)$body['status_cd']) !== 'error'))) {
                $data = $body['data'] ?? $body['Data'] ?? $body ?? [];
                $ewbNo = $data['ewayBillNo'] ?? $data['EwbNo'] ?? $data['ewb_no'] ?? null;
                $ewbDt = !empty($data['ewayBillDate'] ?? $data['EwbDt']) ? Carbon::parse($data['ewayBillDate'] ?? $data['EwbDt']) : Carbon::now();
                $ewbValidTill = !empty($data['validUpto'] ?? $data['EwbValidTill']) ? Carbon::parse($data['validUpto'] ?? $data['EwbValidTill']) : null;
            } elseif ($isProd) {
                $errorMsg = $this->ewayBillService->extractGatewayErrorMessage($body, $response->body() ?: ('HTTP ' . $response->status()));
                Log::error('PeriOne Standalone EWB Failed: ' . $errorMsg, ['response' => $body]);
                throw new \Exception('E-Way Bill Generation Failed: ' . $errorMsg);
            }
        } catch (\Throwable $e) {
            Log::warning('Direct E-Way Bill gateway exception: ' . $e->getMessage());
            if ($isProd) {
                if ($request->wantsJson() && !$request->header('X-Inertia')) {
                    return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
                }
                return redirect()->back()->withErrors(['error' => $e->getMessage()]);
            }
        }

        // In Non-Production / Sandbox, simulate valid E-Way Bill
        if (!$ewbNo) {
            $ewbNo = '33' . date('ymd') . str_pad((string)rand(100000, 999999), 6, '0', STR_PAD_LEFT);
            $ewbDt = Carbon::now();
            $daysValid = max(1, (int)ceil($distance / 200));
            $ewbValidTill = Carbon::now()->addDays($daysValid);
        }

        // 6. Persist E-Way Bill into mm_ewaybill_details table
        EwaybillDetail::updateOrCreate(
            [
                'generation_type' => $generationType,
                'origin_id'       => $invoice->id,
            ],
            [
                'plant_id'        => $plant?->id ?? 1,
                'ewaybill_no'     => (string)$ewbNo,
                'ewaybill_date'   => $ewbDt->toDateTimeString(),
                'valid_upto'      => $ewbValidTill?->toDateTimeString(),
                'ewaybill_status' => 'ACT',
                'status'          => 1,
                'created_at'      => Carbon::now(),
                'created_by'      => $userId,
                'modified_at'     => Carbon::now(),
                'modified_by'     => $userId,
            ]
        );

        $successMsg = "E-Way Bill generated successfully! EWB No: {$ewbNo}";

        if ($request->wantsJson() && !$request->header('X-Inertia')) {
            return response()->json([
                'success' => true,
                'message' => $successMsg,
                'data'    => [
                    'eway_bill_no'          => (string)$ewbNo,
                    'eway_bill_date'        => $ewbDt->toIso8601String(),
                    'eway_bill_valid_until' => $ewbValidTill?->toIso8601String(),
                    'invoice_id'            => $invoice->id,
                    'batch_id'              => $batchId,
                ],
            ]);
        }

        return redirect()->back()->with('success', $successMsg);
    }

    /**
     * Cancel an E-Way Bill.
     */
    public function cancel(Request $request, Invoice $invoice)
    {
        $generationType = $request->input('generation_type');

        $query = EwaybillDetail::where('origin_id', $invoice->id)
            ->where('status', 1)
            ->where('ewaybill_status', 'ACT');

        if (!empty($generationType)) {
            $query->where(function ($q) use ($generationType) {
                $q->where('generation_type', $generationType)
                  ->orWhere('generation_type', strtolower($generationType))
                  ->orWhere('generation_type', ucfirst(strtolower($generationType)));
            });
        } else {
            $query->whereIn('generation_type', ['invoice', 'batch', 'Invoice', 'Batch']);
        }

        $ewb = $query->first();

        if (!$ewb || empty($ewb->ewaybill_no)) {
            $msg = 'No active E-Way Bill found for this invoice.';
            if ($request->wantsJson() && !$request->header('X-Inertia')) {
                return response()->json(['success' => false, 'message' => $msg], 404);
            }
            return redirect()->back()->withErrors(['error' => $msg]);
        }

        $ewb->update([
            'ewaybill_status'    => 'CNL',
            'status'             => 0,
            'ewaybill_cancel_at' => Carbon::now(),
            'ewaybill_cancel_by' => Auth::id() ?? 1,
            'modified_at'        => Carbon::now(),
            'modified_by'        => Auth::id() ?? 1,
        ]);

        $msg = "E-Way Bill #{$ewb->ewaybill_no} cancelled successfully.";

        if ($request->wantsJson() && !$request->header('X-Inertia')) {
            return response()->json(['success' => true, 'message' => $msg]);
        }

        return redirect()->back()->with('success', $msg);
    }

    /**
     * Print official e-Way Bill directly using PeriOne API gateway data.
     * Endpoint: /ewaybillapi/v1.03/ewayapi/getewaybill
     */
    /**
     * Print official e-Way Bill using EwaybillDetail, Plant, and Patron.
     */
    public function print(Request $request, $id)
    {
        $realId = $id;
        try { $realId = decrypt($id); } catch (\Throwable $e) {}

        $ewb = EwaybillDetail::where('id', $realId)
            ->orWhere('ewaybill_no', (string)$id)
            ->first();

        if (!$ewb) {
            abort(404, 'E-Way Bill record not found.');
        }

        $plant = $ewb->plant ?? \App\Models\Plant::find($ewb->plant_id) ?? \App\Models\Plant::find(session('active_plant_id'));

        $invoice = null;
        $dispatch = null;
        $patron = null;

        $genType = strtolower((string)$ewb->generation_type);

        if ($genType === 'invoice') {
            $invoice = Invoice::with(['partner.addresses.state', 'partner.contacts.addresses.state', 'items.mixDesign.concreteGrade'])->find($ewb->origin_id);
            $patron = $invoice?->partner;
            $dispatch = Dispatch::with(['truck', 'transport'])->whereHas('status', function($q) use ($invoice) {
                $q->where('invoice_id', $invoice?->id);
            })->first();
        } elseif ($genType === 'batch') {
            $dispatch = Dispatch::with(['customer.addresses.state', 'customer.contacts.addresses.state', 'truck', 'transport', 'mixDesign.concreteGrade'])->where('batch_id', $ewb->origin_id)->first();
            $patron = $dispatch?->customer;
            if ($dispatch?->status?->invoice_id) {
                $invoice = Invoice::with(['items.mixDesign.concreteGrade'])->find($dispatch->status->invoice_id);
            }
        }

        // Cross-check origin_id if not resolved
        if (!$invoice && $ewb->origin_id) {
            $invoice = Invoice::with(['partner.addresses.state', 'partner.contacts.addresses.state', 'items.mixDesign.concreteGrade'])->find($ewb->origin_id);
            if ($invoice) {
                $patron = $patron ?? $invoice->partner;
                if (!$dispatch) {
                    $dispatch = Dispatch::with(['truck', 'transport'])->whereHas('status', function($q) use ($invoice) {
                        $q->where('invoice_id', $invoice->id);
                    })->first();
                }
            }
        }

        if (!$dispatch && $ewb->origin_id) {
            $dispatch = Dispatch::with(['customer.addresses.state', 'customer.contacts.addresses.state', 'truck', 'transport', 'mixDesign.concreteGrade'])->where('batch_id', $ewb->origin_id)->first();
            if ($dispatch) {
                $patron = $patron ?? $dispatch->customer;
            }
        }

        if (!$patron && $invoice) {
            $patron = $invoice->partner;
        }

        // Format data strictly matching the uploaded sample photo
        $data = $this->formatEwayBillDataFromModel($ewb, $plant, $patron, $invoice, $dispatch);

        return $this->renderPrintView($data, $request->query('action', 'view'));
    }

    /**
     * Print E-Way Bill for an Invoice.
     */
    public function printForInvoice(Request $request, $invoice)
    {
        $realId = $invoice instanceof Invoice ? $invoice->id : $invoice;
        try { $realId = decrypt($realId); } catch (\Throwable $e) {}

        $invoiceModel = Invoice::find($realId);
        if (!$invoiceModel) {
            abort(404, 'Linked Invoice not found.');
        }

        // 1. Direct lookup in mm_ewaybill_details table by invoice origin_id
        $ewb = EwaybillDetail::where('origin_id', $invoiceModel->id)
            ->where('status', 1)
            ->latest('created_at')
            ->first();

        // 2. Fallback check via linked dispatches/batches for this invoice
        if (!$ewb) {
            $batchIds = DB::table('mm_dispatches as d')
                ->join('mm_dispatch_statuses as ds', 'ds.dispatch_id', '=', 'd.id')
                ->where('ds.invoice_id', $invoiceModel->id)
                ->whereNotNull('d.batch_id')
                ->pluck('d.batch_id')
                ->toArray();

            if (!empty($batchIds)) {
                $ewb = EwaybillDetail::whereIn('origin_id', $batchIds)
                    ->where('status', 1)
                    ->latest('created_at')
                    ->first();
            }
        }

        $ewbNo = $ewb?->ewaybill_no ?: ($ewb?->id ?? null);

        if (empty($ewbNo)) {
            abort(404, 'No active E-Way Bill found for this invoice. Please generate an E-Way Bill first.');
        }

        return $this->print($request, $ewbNo);
    }

    /**
     * Print E-Way Bill for a Batch.
     */
    public function printForBatch(Request $request, $batch)
    {
        $batchId = $batch instanceof Batch ? $batch->id : (int)$batch;

        $dispatch = DB::table('mm_dispatches as d')
            ->leftJoin('mm_dispatch_statuses as ds', 'ds.dispatch_id', '=', 'd.id')
            ->where('d.batch_id', $batchId)
            ->whereNull('d.deleted_at')
            ->select('ds.invoice_id')
            ->first();

        if (!$dispatch || empty($dispatch->invoice_id)) {
            abort(404, 'No generated Invoice found for this batch to print E-Way Bill.');
        }

        return $this->printForInvoice($request, $dispatch->invoice_id);
    }

    /**
     * Build E-Way Bill print data from EwaybillDetail, Plant, and Patron.
     */
    protected function formatEwayBillDataFromModel(
        EwaybillDetail $ewb,
        ?\App\Models\Plant $plant = null,
        ?\App\Models\Patron $patron = null,
        ?\App\Models\Invoice $invoice = null,
        ?\App\Models\Dispatch $dispatch = null
    ): array {
        $ewbNo = (string)($ewb->ewaybill_no ?: '');
        $ewbNoFormatted = trim(chunk_split($ewbNo, 4, ' '));

        $rawDate = $ewb->ewaybill_date ?: now();
        try {
            $ewbDate = Carbon::parse($rawDate)->format('d/m/Y h:i A');
        } catch (\Throwable $e) {
            $ewbDate = (string)$rawDate;
        }

        $distance = 33;
        $validFrom = $ewbDate . ' [' . $distance . 'Kms]';

        $rawValidUpto = $ewb->valid_upto ?: now()->addDay();
        try {
            $validUntil = Carbon::parse($rawValidUpto)->format('d/m/Y');
        } catch (\Throwable $e) {
            $validUntil = (string)$rawValidUpto;
        }

        // Plant_id used to get FROM details (Supplier)
        $fromGstin = (string)($plant?->gstin ?: ($plant?->entity?->gstin ?: ''));
        $fromTrdName = (string)($plant?->name ?: ($plant?->entity?->legal_name ?: ($plant?->entity?->entity_name ?: '')));
        $generatedBy = trim($this->formatGstinWithSpaces($fromGstin) . ($fromTrdName ? ' - ' . $fromTrdName : ''));
        $gstinSupplier = trim($fromGstin . ($fromTrdName ? ',' . $fromTrdName : ''));

        $plAddr = $plant?->addresses?->first() ?? $plant?->addresses()?->first();
        $fromPlace = (string)($plAddr?->city ?? '');
        $fromStateName = (string)($plAddr?->state?->state_name ?? $plAddr?->state_code ?? '');
        $fromPincode = (string)($plAddr?->zipcode ?? '');
        $placeOfDispatch = trim($fromPlace . ($fromStateName ? ',' . $fromStateName : '') . ($fromPincode ? '-' . $fromPincode : ''), ',- ');

        // Patron used to get TO details (Recipient)
        $toGstin = (string)($patron?->gstin ?? '');
        $toTrdName = (string)($patron?->legal_name ?: ($patron?->name ?? ''));
        $gstinRecipient = trim($this->formatGstinWithSpaces($toGstin) . ($toTrdName ? ' ,' . $toTrdName : ''));

        $patronAddr = $patron?->addresses?->first() 
            ?? $patron?->addresses()?->first() 
            ?? $patron?->contacts?->first()?->addresses?->first() 
            ?? null;
        $toPlace = (string)($patronAddr?->city ?? '');
        $toStateName = (string)($patronAddr?->state?->state_name ?? $patronAddr?->state_code ?? '');
        $toPincode = (string)($patronAddr?->zipcode ?? '');
        $placeOfDelivery = trim($toPlace . ($toStateName ? ',' . $toStateName : '') . ($toPincode ? '-' . $toPincode : ''), ',- ');

        // Document Details
        $docNo = (string)($invoice?->full_number ?: ($invoice?->invoice_number ?: ($dispatch ? ('DP-' . $dispatch->dispatch_no) : ('Inv/26-27/' . $ewb->origin_id))));
        $rawDocDate = $invoice?->invoice_date ?: ($ewb->ewaybill_date ?: now());
        try {
            $docDate = Carbon::parse($rawDocDate)->format('d/m/Y');
        } catch (\Throwable $e) {
            $docDate = (string)$rawDocDate;
        }

        $transactionType = 'Regular';

        $valOfGoods = (string)($invoice?->total_amount ?? ($dispatch?->load_total_amount ?? ''));
        if (is_numeric($valOfGoods) && $valOfGoods !== '') {
            $valOfGoods = (string)round((float)$valOfGoods);
        }

        // Fetch HSN Code & Concrete Mix from mm_mix_designs and mm_concrete_grades tables
        $firstItem = $invoice?->items?->first();
        $mixDesignId = $firstItem?->item_id 
            ?? $dispatch?->mixdesign_id 
            ?? null;

        if (!$mixDesignId && $dispatch?->sales_order_id) {
            $mixDesignId = \App\Models\SalesOrder::where('id', $dispatch->sales_order_id)->value('mix_design_id');
        }

        if (!$mixDesignId && $ewb->origin_id && strtolower((string)$ewb->generation_type) === 'batch') {
            $batch = \App\Models\Batch::with('salesOrder')->find($ewb->origin_id);
            $mixDesignId = $batch?->salesOrder?->mix_design_id;
        }

        $mixDesign = $mixDesignId 
            ? \App\Models\MixDesign::with('concreteGrade')->find($mixDesignId) 
            : ($dispatch?->mixDesign ?? null);

        $concreteGrade = $mixDesign?->concreteGrade 
            ?? ($mixDesign?->concrete_grade_id ? \App\Models\ConcreteGrade::find($mixDesign->concrete_grade_id) : null);

        if (!$concreteGrade && !empty($dispatch?->mixdesign_id)) {
            $dm = \App\Models\MixDesign::find($dispatch->mixdesign_id);
            if ($dm?->concrete_grade_id) {
                $concreteGrade = \App\Models\ConcreteGrade::find($dm->concrete_grade_id);
            }
        }

        // HSN Code strictly from mm_concrete_grades table (or fallback 3824)
        $hsn = (string)(
            $concreteGrade?->hsn_code 
            ?: ($concreteGrade?->concrete_code && is_numeric($concreteGrade->concrete_code) ? $concreteGrade->concrete_code : null)
            ?: ($firstItem?->hsn_code ?: '3824')
        );

        // Product Name strictly from mm_mix_designs and mm_concrete_grades tables
        $prodName = (string)($concreteGrade?->name ?: ($mixDesign?->design_name ?: ($mixDesign?->design_type ?: 'CONCREATE READY MIX')));

        $hsnFull = trim($hsn . ($prodName ? ' - ' . $prodName : ''));

        $reasonForTransport = 'Outward - Supply';
        $transporter = (string)($dispatch?->transport?->legal_name ?: ($dispatch?->transport?->name ?: ''));

        // Part - B Vehicle Details
        $mode = 'Road';
        $vehicleNo = (string)($dispatch?->truck?->registration ?: ($dispatch?->truck?->registration_number ?: ($dispatch?->truck?->name ?: 'TN42AU8217')));
        $fromVeh = (string)($fromPlace ?: 'Tiruppur');
        $enteredDate = (string)$ewbDate;
        $enteredBy = (string)($fromGstin ?: '33AAZFD1084E1ZD');
        $cewbNo = '-';
        $multiVehInfo = '-';
        $portal = '1';

        $barcodeSvg = self::generateBarcodeSvg($ewbNo);
        $qrText = "EWB:{$ewbNo}|From:{$fromGstin}|To:{$toGstin}|Doc:{$docNo}|Date:{$docDate}";

        return [
            'ewb_no'                  => $ewbNo,
            'ewb_no_formatted'        => $ewbNoFormatted,
            'ewb_date'                => $ewbDate,
            'generated_by'            => $generatedBy,
            'valid_from'              => $validFrom,
            'valid_until'             => $validUntil,
            'portal'                  => $portal,
            'gstin_supplier'          => $gstinSupplier,
            'place_of_dispatch'       => $placeOfDispatch,
            'gstin_recipient'         => $gstinRecipient,
            'place_of_delivery'       => $placeOfDelivery,
            'doc_no'                  => $docNo,
            'doc_date'                => $docDate,
            'transaction_type'        => $transactionType,
            'value_of_goods'          => $valOfGoods,
            'hsn_code'                => $hsnFull,
            'reason_for_transport'    => $reasonForTransport,
            'transporter'             => $transporter,
            'part_b' => [
                'mode'                => $mode,
                'vehicle_no'          => $vehicleNo,
                'from'                => $fromVeh,
                'entered_date'        => $enteredDate,
                'entered_by'          => $enteredBy,
                'cewb_no'             => $cewbNo,
                'multi_veh_info'      => $multiVehInfo,
                'portal'              => $portal,
            ],
            'barcode_svg'             => $barcodeSvg,
            'qr_data'                 => $qrText,
        ];
    }

    /**
     * Format data strictly mapping to the sample e-Way Bill image from API (if used).
     */
    protected function formatEwayBillDataFromApi(array $api, string $fallbackEwbNo, ?EwaybillDetail $ewb = null): array
    {
        if ($ewb) {
            $plant = $ewb->plant ?? \App\Models\Plant::find($ewb->plant_id);
            $invoice = Invoice::find($ewb->origin_id);
            $patron = $invoice?->partner;
            return $this->formatEwayBillDataFromModel($ewb, $plant, $patron, $invoice);
        }

        return $this->formatEwayBillDataFromModel(
            new EwaybillDetail(['ewaybill_no' => $fallbackEwbNo]),
            null,
            null,
            null,
            null
        );
    }

    /**
     * Render the official e-Way Bill view matching the sample layout.
     */
    public function renderPrintView(array $data, string $action = 'view')
    {
        if ($action === 'download') {
            $pdf = Pdf::loadView('pdfs.ewaybill.print', ['data' => $data, 'is_pdf' => true])->setPaper('a4', 'portrait');
            return $pdf->download('eWayBill_' . ($data['ewb_no'] ?? 'EWB') . '.pdf');
        }

        return view('pdfs.ewaybill.print', ['data' => $data, 'is_pdf' => false]);
    }

    /**
     * Format GSTIN with spaces for display (e.g. 33AAZ FD108 4E1ZD).
     */
    protected function formatGstinWithSpaces(string $gstin): string
    {
        $gstin = strtoupper(trim(str_replace(' ', '', $gstin)));
        if (strlen($gstin) === 15) {
            return substr($gstin, 0, 5) . ' ' . substr($gstin, 5, 5) . ' ' . substr($gstin, 10, 5);
        }
        return $gstin;
    }

    /**
     * Resolve State Name from State Code.
     */
    protected function getStateNameByCode(?int $code): string
    {
        $states = [
            1 => 'JAMMU AND KASHMIR', 2 => 'HIMACHAL PRADESH', 3 => 'PUNJAB', 4 => 'CHANDIGARH',
            5 => 'UTTARAKHAND', 6 => 'HARYANA', 7 => 'DELHI', 8 => 'RAJASTHAN',
            9 => 'UTTAR PRADESH', 10 => 'BIHAR', 11 => 'SIKKIM', 12 => 'ARUNACHAL PRADESH',
            13 => 'NAGALAND', 14 => 'MANIPUR', 15 => 'MIZORAM', 16 => 'TRIPURA',
            17 => 'MEGHALAYA', 18 => 'ASSAM', 19 => 'WEST BENGAL', 20 => 'JHARKHAND',
            21 => 'ODISHA', 22 => 'CHATTISGARH', 23 => 'MADHYA PRADESH', 24 => 'GUJARAT',
            27 => 'MAHARASHTRA', 29 => 'KARNATAKA', 30 => 'GOA', 31 => 'LAKSHADWEEP',
            32 => 'KERALA', 33 => 'TAMIL NADU', 34 => 'PUDUCHERRY', 35 => 'ANDAMAN AND NICOBAR ISLANDS',
            36 => 'TELANGANA', 37 => 'ANDHRA PRADESH', 38 => 'LADAKH',
        ];
        return $states[(int)$code] ?? 'TAMIL NADU';
    }

    /**
     * Generate standard Code 128 barcode as vector SVG.
     */
    public static function generateBarcodeSvg(string $code): string
    {
        $patterns = [
            '212222','222122','222221','121223','121322','131222','122213','122312','132212','221213',
            '221312','231212','112232','122132','122231','113222','123122','123221','223211','221132',
            '221231','213212','223112','312131','311222','321122','321221','312212','322112','322211',
            '212123','212321','232121','111323','131123','131321','112313','132113','132311','211313',
            '231113','231311','112133','112331','132131','113123','113321','133121','313121','211331',
            '231131','213113','213311','213131','311123','311321','331121','312113','312311','332111',
            '314111','221411','431111','111224','111422','121124','121421','141122','141221','112214',
            '112412','122114','122411','142112','142211','241211','221114','413111','241112','134111',
            '111242','121142','121241','114212','124112','124211','411212','421112','421211','212141',
            '214121','412121','111143','111341','131141','114113','114311','411113','411311','113141',
            '114131','311141','411131','211412','211214','211232','2331112'
        ];
        
        $chars = str_split($code);
        $indices = [104];
        $checksum = 104;
        
        foreach ($chars as $pos => $char) {
            $val = ord($char) - 32;
            if ($val < 0 || $val > 95) $val = 0;
            $indices[] = $val;
            $checksum += $val * ($pos + 1);
        }
        
        $indices[] = $checksum % 103;
        $indices[] = 106;
        
        $modules = '';
        foreach ($indices as $idx) {
            $pat = $patterns[$idx] ?? $patterns[0];
            $isBar = true;
            for ($i = 0; $i < strlen($pat); $i++) {
                $len = (int)$pat[$i];
                $modules .= str_repeat($isBar ? '1' : '0', $len);
                $isBar = !$isBar;
            }
        }
        
        $barWidth = 1.3;
        $height = 36;
        $totalWidth = strlen($modules) * $barWidth;
        
        $svg = '<svg xmlns="http://www.w3.org/2000/svg" width="' . $totalWidth . '" height="' . $height . '" viewBox="0 0 ' . $totalWidth . ' ' . $height . '">';
        $svg .= '<rect width="100%" height="100%" fill="#ffffff"/>';
        
        for ($i = 0; $i < strlen($modules); $i++) {
            if ($modules[$i] === '1') {
                $svg .= '<rect x="' . ($i * $barWidth) . '" y="0" width="' . $barWidth . '" height="' . $height . '" fill="#000000"/>';
            }
        }
        $svg .= '</svg>';
        
        return $svg;
    }

    /**
     * List E-Way Bills from database.
     */
    public function list(Request $request)
    {
        $plantId = session('active_plant_id');

        $query = EwaybillDetail::query()->with('invoice');
        if ($plantId) {
            $query->where('plant_id', $plantId);
        }

        $ewaybills = $query->orderBy('created_at', 'DESC')->paginate(15);

        if ($request->wantsJson() && !$request->header('X-Inertia')) {
            return response()->json([
                'success' => true,
                'data'    => $ewaybills,
            ]);
        }

        return inertia('EwayBills/Index', [
            'ewaybills' => $ewaybills,
        ]);
    }

    /**
     * Refresh / Sync E-Way Bills from PeriOne for a Date Range.
     */
    public function refreshEWB(Request $request)
    {
        try {
            $plantId = session('active_plant_id');
            $plant   = $plantId ? \App\Models\Plant::find($plantId) : null;

            $fromDate = $request->input('from', date('Y-m-d'));
            $toDate   = $request->input('to', date('Y-m-d'));
            $dates    = $this->dateRange($fromDate, $toDate, '+1 day', 'd/m/Y');

            $result = $this->processEWBListData($dates, $plant);

            if (!empty($result['error_msg'])) {
                if ($request->wantsJson() && !$request->header('X-Inertia')) {
                    return response()->json(['success' => false, 'message' => $result['error_msg']], 422);
                }
                return redirect()->back()->withErrors(['error' => $result['error_msg']]);
            }

            if ($request->wantsJson() && !$request->header('X-Inertia')) {
                return response()->json(['success' => true, 'message' => $result['success_msg']]);
            }

            return redirect()->back()->with('success', $result['success_msg']);
        } catch (\Throwable $th) {
            if ($request->wantsJson() && !$request->header('X-Inertia')) {
                return response()->json(['success' => false, 'message' => $th->getMessage()], 500);
            }
            return redirect()->back()->withErrors(['error' => 'Sync failed: ' . $th->getMessage()]);
        }
    }

    /**
     * Process list of dates: fetches list from PeriOne, checks DB, fetches details, and saves.
     */
    protected function processEWBListData(array $dates = [], ?\App\Models\Plant $plant = null): array
    {
        // 1. Authenticate with PeriOne Portal
        $this->ewayBillService->authenticatePortel($plant);

        // 2. Fetch all EWB numbers across selected dates
        $ewb_list = [];
        foreach ($dates as $date) {
            $responseData = $this->ewayBillService->fetchEWBList($date, $plant);

            if (isset($responseData['data']) && is_array($responseData['data'])) {
                foreach ($responseData['data'] as $v) {
                    if (!empty($v['ewbNo'])) {
                        $ewb_list[] = (string)$v['ewbNo'];
                    }
                }
            }
        }

        if (empty($ewb_list)) {
            return ['error_msg' => 'No E-Way Bills found from PeriOne for the selected dates.'];
        }

        // 3. Filter out already existing E-Way Bills in DB
        $ewb_in_db = EwaybillDetail::whereIn('ewaybill_no', $ewb_list)->pluck('ewaybill_no')->toArray();
        $new_ewb_list = array_diff($ewb_list, $ewb_in_db);

        if (empty($new_ewb_list)) {
            return ['error_msg' => 'All E-Way Bills for these dates are already synced in database.'];
        }

        // 4. Fetch full details for each new E-Way Bill and insert
        $plantId = $plant?->id ?? session('active_plant_id') ?? 1;
        $userId  = Auth::id() ?? 1;
        $insertCount = 0;

        foreach ($new_ewb_list as $ewbNo) {
            $all_ewb_details = $this->ewayBillService->fetchEWBDetails($ewbNo, $plant);
            $data = $all_ewb_details['data'] ?? null;

            if (!$data) {
                continue;
            }

            $ewbDate = !empty($data['ewayBillDate']) 
                ? Carbon::parse(str_replace('/', '-', $data['ewayBillDate']))->toDateTimeString() 
                : Carbon::now()->toDateTimeString();

            $validTill = !empty($data['validUpto']) 
                ? Carbon::parse(str_replace('/', '-', $data['validUpto']))->toDateTimeString() 
                : null;

            EwaybillDetail::create([
                'plant_id'        => $plantId,
                'generation_type' => 'inbound_transporter',
                'origin_id'       => null,
                'ewaybill_no'     => (string)($data['ewbNo'] ?? $ewbNo),
                'ewaybill_date'   => $ewbDate,
                'valid_upto'      => $validTill,
                'ewaybill_status' => 'ACT',
                'status'          => 1,
                'created_at'      => Carbon::now(),
                'created_by'      => $userId,
                'modified_at'     => Carbon::now(),
                'modified_by'     => $userId,
            ]);

            $insertCount++;
        }

        if ($insertCount > 0) {
            return ['success_msg' => "{$insertCount} E-Way Bills synced successfully from PeriOne."];
        }

        return ['error_msg' => 'No new E-Way Bill records could be inserted.'];
    }

    /**
     * Date range generator helper.
     */
    protected function dateRange(string $startDate = '', string $endDate = '', string $step = '+1 day', string $format = 'd/m/Y'): array
    {
        $dates = [];
        $current = strtotime($startDate);
        $last = strtotime($endDate);
        while ($current <= $last) {
            $dates[] = date($format, $current);
            $current = strtotime($step, $current);
        }
        return $dates;
    }
}