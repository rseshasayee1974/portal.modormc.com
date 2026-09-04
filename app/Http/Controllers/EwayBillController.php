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

        return $this->generateStandaloneEwayBill($invoice, $request->all(), $request);
    }

    /**
     * Core handler: Generate direct E-Way Bill without requiring E-Invoice (IRN).
     */
    protected function generateStandaloneEwayBill(Invoice $invoice, array $params, Request $request, ?int $batchId = null)
    {
        $userId = Auth::id() ?? 1;
        $plant = $invoice->plant;

        // 1. Check if an active E-Way Bill already exists
        $existingEwb = EwaybillDetail::where('origin_id', $invoice->id)
            ->where('generation_type', 'invoice')
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
                    ?: ($item->mixDesign?->design_name 
                    ?: ($item->item_name ?: 'Ready Mix Concrete')));

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
            'fromTrdName'      => (string)($plant?->name ?: ($entity?->name ?? 'Modo RMC')),
            'fromAddr1'        => (string)($plantAddress?->line_1 ?: ($plant?->name ?? 'Plant Address')),
            'fromAddr2'        => (string)($plantAddress?->line_2 ?: ''),
            'fromPlace'        => (string)($plantAddress?->city ?: 'Chennai'),
            'fromPincode'      => (int)($plantAddress?->zipcode ?: 600001),
            'actFromStateCode' => $sellerStateCode,
            'fromStateCode'    => $sellerStateCode,
            'toGstin'          => $buyerGstin,
            'toTrdName'        => (string)($partner?->legal_name ?: ($partner?->name ?? 'Customer')),
            'toAddr1'          => (string)($partnerAddress?->line_1 ?: ($invoice->site?->site_address_1 ?: 'Delivery Site')),
            'toAddr2'          => (string)($partnerAddress?->line_2 ?: ''),
            'toPlace'          => (string)($partnerAddress?->city ?: ($invoice->site?->name ?: 'Chennai')),
            'toPincode'        => (int)($partnerAddress?->zipcode ?: 600001),
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
            'transporterId'    => '29AARFB4347G000',
            'transporterName'  => $transName,
            'transDocNo'       => $transDocNo,
            'transDocDate'     => $transDocDt,
            'vehicleNo'        => $vehNo ?: 'TN92AC1234',
            'vehicleType'      => $vehType,
            'itemList'         => $itemList,
        ];

        // 5. Ensure valid authentication token exists in mm_ewaybill_auth (Mandatory prerequisite)
        try {
            $auth = $this->ewayBillService->authenticate($plant, $userId);
           
        } catch (\Throwable $e) {
            Log::error('E-Way Bill authentication failed in mm_ewaybill_auth: ' . $e->getMessage());
            $errorMsg = 'E-Way Bill authentication failed: ' . $e->getMessage() . '. Valid authentication token is required in mm_ewaybill_auth before generating an E-Way Bill.';
            if ($request->wantsJson() && !$request->header('X-Inertia')) {
                return response()->json(['success' => false, 'message' => $errorMsg], 422);
            }
            return redirect()->back()->withErrors(['error' => $errorMsg]);
        }

       

        // 6. Contact Gateway or simulate only after mm_ewaybill_auth is verified
        $ewbNo = null;
        $ewbDt = null;
        $ewbValidTill = null;

        $isProd = $this->ewayBillService->isProduction($plant);
        $url = $this->ewayBillService->getGenEwayBillUrl($plant);
dd($ewbPayload);
        // try {
            // Authenticate with PeriOne E-Way Bill Gateway
            $headers = $this->ewayBillService->buildGatewayHeaders($auth, $username, $password, $sellerGstin, $plant);

            $response = Http::withHeaders($headers)->timeout(30)->post($url, $ewbPayload);
            dd( $response->json());
           
            $body = $response->json() ?? [];

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
        // } catch (\Throwable $e) {
        //     Log::warning('Direct E-Way Bill gateway exception: ' . $e->getMessage());
        //     if ($isProd) {
        //         if ($request->wantsJson() && !$request->header('X-Inertia')) {
        //             return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
        //         }
        //         return redirect()->back()->withErrors(['error' => $e->getMessage()]);
        //     }
        // }

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
                'generation_type' => 'invoice',
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
        $ewb = EwaybillDetail::where('origin_id', $invoice->id)
            ->where('generation_type', 'invoice')
            ->where('status', 1)
            ->where('ewaybill_status', 'ACT')
            ->first();

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
}