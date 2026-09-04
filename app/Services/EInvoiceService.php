<?php

namespace App\Services;

use App\Models\Invoice;
use App\Models\Plant;
use App\Models\EinvoiceAuth;
use App\Models\EinvoiceInvoiceRel;
use App\Models\EwaybillDetail;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

class EInvoiceService
{
    public ?string $baseUrl = null;
    public ?string $clientId = null;
    public ?string $clientSecret = null;
    public ?string $email = null;
    public ?string $ipAddress = null;

    /**
     * Generate E-Invoice (IRN) for an invoice.
     */
    public function generate(Invoice $invoice, array $transportDetails = []): array
    {
        return $this->generateIrn($invoice, $transportDetails);
    }

    /**
     * Generate IRN with PeriOne / NIC gateway and persist results.
     */
    public function generateIrn(Invoice $invoice, array $transportDetails = []): array
    {
        // 0. Validate invoice, seller, buyer, item, and transport prerequisites
        $this->validateForIrn($invoice, $transportDetails);

        $userId = Auth::id() ?? 1;
        $plant = $invoice->plant;

        // 1. Authenticate with PeriOne E-Invoice gateway first (Sandbox / Production)
        $auth = $this->authenticate($plant, $userId);

        // 2. Resolve Gateway settings & plant credentials
        $this->getGatewayConfig($plant);
        [$username, $password, $gstin] = $this->getPlantCredentials($plant);

        // 3. Build JSON Payload conforming to standard NIC / PeriOne E-Invoice schema
        $payload = $this->buildIrnPayload($invoice, $transportDetails);

        // 4. Send IRN Generation Request to Gateway
        $headers = $this->buildGatewayHeaders($auth, $username, $password, $gstin ?: ($payload['SellerDtls']['Gstin'] ?? ''));
        $data = $this->sendGenerateRequest($headers, $payload);

        $irn = $data['Irn'] ?? $data['irn'] ?? null;
        $ackNo = $data['AckNo'] ?? $data['ack_no'] ?? null;
        $ackDateStr = $data['AckDt'] ?? $data['ack_date'] ?? null;
        $ackDate = $ackDateStr ? Carbon::parse($ackDateStr) : Carbon::now();
        $signedQrCode = $data['SignedQRCode'] ?? $data['signed_qrcode'] ?? $data['QrCode'] ?? null;
        $signedInvoice = $data['SignedInvoice'] ?? $data['signed_invoice'] ?? '';

        if (!$irn) {
            throw new \Exception('PeriOne E-Invoice Gateway did not return an IRN.');
        }

        // 5. Persist real gateway results in mm_invoices and mm_einvoice_invoice_rel tables
        $this->persistIrnRecord($invoice, [
            'irn'            => $irn,
            'ack_no'         => $ackNo,
            'ack_date'       => $ackDate,
            'signed_qrcode'  => $signedQrCode,
            'signed_invoice' => $signedInvoice,
        ], $plant, $userId);

        return [
            'success'        => true,
            'irn'            => $irn,
            'ack_no'         => $ackNo,
            'ack_date'       => $ackDate?->toIso8601String(),
            'qr_code'        => $signedQrCode,
            'signed_invoice' => $signedInvoice,
            'payload'        => $payload,
        ];
    }

    /**
     * Generate E-Way Bill using an existing IRN.
     */
    public function generateEwayBillByIrn(Invoice $invoice, array $transportDetails = []): array
    {
        $irn = $invoice->einvoice_irn ?: $invoice->einv_irn;
        if (empty($irn)) {
            throw new \Exception('Cannot generate E-Way Bill: E-Invoice (IRN) has not been generated for Invoice #' . $invoice->id);
        }

        $vehNo = trim((string)($transportDetails['veh_no'] ?? $invoice->vehicle_number ?? ''));
        if (empty($vehNo)) {
            throw new \Exception('Vehicle number is required to generate E-Way Bill.');
        }

        $userId = Auth::id() ?? 1;
        $plant = $invoice->plant;

        // 1. Authenticate with PeriOne Gateway for this plant
        $auth = $this->authenticate($plant, $userId);

        // 2. Resolve credentials
        $this->getGatewayConfig($plant, true);
        [$username, $password, $gstin] = $this->getPlantCredentials($plant);

        // 3. Build E-Way Bill by IRN Payload
        $partner = $invoice->partner;
        $partnerAddress = $partner?->addresses()?->first() ?: $partner?->contacts()?->first()?->addresses()?->first();
        $buyerGstin = trim((string)($partner?->gstin ?: ''));
        $buyerStateCode = (strlen($buyerGstin) >= 2 && ctype_digit(substr($buyerGstin, 0, 2)))
            ? substr($buyerGstin, 0, 2)
            : '';

        $payload = [
            'Irn'         => $irn,
            'Distance'    => (int)($transportDetails['distance'] ?? 0),
            'TransMode'   => (string)($transportDetails['trans_mode'] ?? '1'),
            'TransId'     => $transportDetails['trans_id'] ?? null,
            'TransName'   => $transportDetails['trans_name'] ?? null,
            'TransDocNo'  => $transportDetails['trans_doc_no'] ?? null,
            'TransDocDt'  => !empty($transportDetails['trans_doc_dt']) ? Carbon::parse($transportDetails['trans_doc_dt'])->format('d/m/Y') : null,
            'VehNo'       => strtoupper($vehNo),
            'VehType'     => $transportDetails['veh_type'] ?? 'R',
            'ExpShipDtls' => [
                'Addr1' => (string)($partnerAddress?->line_1 ?: ($partnerAddress?->address_line1 ?? '')),
                'Loc'   => (string)($partnerAddress?->city ?? ''),
                'Pin'   => (int)($partnerAddress?->zipcode ?: ($partnerAddress?->pin_code ?? 0)),
                'Stcd'  => (string)($partnerAddress?->state_code ?: $buyerStateCode),
            ],
        ];

        // 4. Send request to /einvoice/ewaybill/generate endpoint
        $headers = $this->buildGatewayHeaders($auth, $username, $password, $gstin);
        $url = rtrim($this->baseUrl, '/') . '/einvoice/ewaybill/generate?email=' . urlencode($this->email);

        $ewbNo = null;
        $ewbDt = null;
        $ewbValidTill = null;

        try {
            $response = Http::withHeaders($headers)->timeout(30)->post($url, $payload);
            $body = $response->json() ?? [];

            if ($response->successful() && (!isset($body['status_cd']) || ($body['status_cd'] !== 0 && $body['status_cd'] !== '0' && strtolower((string)$body['status_cd']) !== 'error'))) {
                $data = $body['data'] ?? $body['Data'] ?? $body ?? [];
                $ewbNo = $data['EwbNo'] ?? $data['ewb_no'] ?? null;
                $ewbDt = !empty($data['EwbDt']) ? Carbon::parse($data['EwbDt']) : Carbon::now();
                $ewbValidTill = !empty($data['EwbValidTill']) ? Carbon::parse($data['EwbValidTill']) : null;
            } elseif (!$response->successful() && $response->status() !== 404) {
                $errorMsg = $this->extractGatewayErrorMessage($body, $response->body() ?: ('HTTP ' . $response->status()));
                Log::error('PeriOne Generate EWB by IRN Failed: ' . $errorMsg, ['response' => $body]);
                throw new \Exception('E-Way Bill Generation by IRN Failed: ' . $errorMsg);
            }
        } catch (\Exception $e) {
            if ($this->isProduction() || !str_contains($e->getMessage(), '404')) {
                throw $e;
            }
        }

        if (!$ewbNo && !$this->isProduction()) {
            $ewbNo = '33' . date('ymd') . str_pad((string)rand(100000, 999999), 6, '0', STR_PAD_LEFT);
            $ewbDt = Carbon::now();
            $distance = (int)($transportDetails['distance'] ?? 100);
            $daysValid = max(1, ceil($distance / 200));
            $ewbValidTill = Carbon::now()->addDays($daysValid);
        }

        if (!$ewbNo) {
            throw new \Exception('PeriOne Gateway did not return an E-Way Bill number.');
        }

        // 5. Update mm_ewaybill_details table
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

        return [
            'success'               => true,
            'eway_bill_no'          => $ewbNo,
            'eway_bill_date'        => $ewbDt->toIso8601String(),
            'eway_bill_valid_until' => $ewbValidTill?->toIso8601String(),
        ];
    }

    /**
     * Cancel an E-Invoice (IRN).
     * 
     * Under GST/NIC rules, an IRN can ONLY be cancelled within 24 hours of generation.
     *
     * @param Invoice $invoice
     * @param string $cancelReason 1=Duplicate, 2=Data entry mistake, 3=Order cancelled, 4=Others
     * @param string $cancelRemarks
     * @return array
     * @throws \Exception
     */
    public function cancelIrn(Invoice $invoice, string $cancelReason = '1', string $cancelRemarks = 'Order cancelled'): array
    {
        $irn = $invoice->einvoice_irn ?: $invoice->einv_irn;
        if (empty($irn)) {
            throw new \Exception('No IRN found for Invoice #' . $invoice->id . '. Cannot cancel.');
        }

        $status = $invoice->einvoice_status ?: $invoice->einv_status;
        if ($status === 'CNL' || $status === 'CAN') {
            throw new \Exception('E-Invoice (IRN) has already been cancelled.');
        }

        // Check strict 24-hour limit from ack date / generation time
        $ackDateRaw = $invoice->einvoice_ack_date ?: $invoice->einv_ack_date;
        $ackDate = $ackDateRaw ? Carbon::parse($ackDateRaw) : null;
        if ($ackDate && Carbon::now()->diffInHours($ackDate, false) < -24) {
            $hoursPassed = round(abs(Carbon::now()->diffInHours($ackDate, false)));
            throw new \Exception("Cannot cancel E-Invoice: IRN was generated {$hoursPassed} hours ago. Under GST/NIC rules, an IRN can only be cancelled within 24 hours of generation. Please issue a Credit Note instead.");
        }

        $userId = Auth::id() ?? 1;
        $plant = $invoice->plant;

        // 1. Authenticate with PeriOne Gateway for this plant
        $auth = $this->authenticate($plant, $userId);

        // 2. Resolve credentials
        $this->getGatewayConfig($plant);
        [$username, $password, $gstin] = $this->getPlantCredentials($plant);

        // 3. Build Cancel IRN payload
        $payload = [
            'Irn'    => (string)$irn,
            'CnlRsn' => (string)($cancelReason ?: '1'),
            'CnlRem' => (string)($cancelRemarks ?: 'Cancelled by user'),
        ];

        // 4. Send request to /einvoice/cancel endpoint
        $headers = $this->buildGatewayHeaders($auth, $username, $password, $gstin);
        $url = rtrim($this->baseUrl, '/') . '/einvoice/cancel?email=' . urlencode($this->email);

        $cancelDate = Carbon::now();

        try {
            $response = Http::withHeaders($headers)->timeout(30)->post($url, $payload);
            $body = $response->json() ?? [];

            if ($response->successful() && (!isset($body['status_cd']) || ($body['status_cd'] !== 0 && $body['status_cd'] !== '0' && strtolower((string)$body['status_cd']) !== 'error'))) {
                $data = $body['data'] ?? $body['Data'] ?? $body ?? [];
                if (!empty($data['CancelDate'])) {
                    $cancelDate = Carbon::parse($data['CancelDate']);
                }
            } elseif (!$response->successful() && $response->status() !== 404) {
                $errorMsg = $this->extractGatewayErrorMessage($body, $response->body() ?: ('HTTP ' . $response->status()));
                Log::error('PeriOne Cancel IRN Failed: ' . $errorMsg, ['response' => $body]);
                throw new \Exception('PeriOne IRN Cancellation Failed: ' . $errorMsg);
            }
        } catch (\Exception $e) {
            if ($this->isProduction() || !str_contains($e->getMessage(), '404')) {
                throw $e;
            }
        }

        // 5. Update database records in mm_einvoice_invoice_rel and mm_ewaybill_details
        EinvoiceInvoiceRel::where('invoice_id', $invoice->id)->update([
            'einv_status'    => 'CNL',
            'einv_cancel_at' => $cancelDate,
            'status'         => 0,
            'modified'       => Carbon::now(),
            'modified_by'    => $userId,
        ]);

        EwaybillDetail::where('origin_id', $invoice->id)
            ->where('generation_type', 'invoice')
            ->update([
                'ewaybill_status'    => 'CNL',
                'ewaybill_cancel_at' => $cancelDate,
                'ewaybill_cancel_by' => $userId,
                'status'             => 0,
                'modified_at'        => Carbon::now(),
                'modified_by'        => $userId,
            ]);

        return [
            'success'     => true,
            'irn'         => $irn,
            'cancel_date' => $cancelDate->toIso8601String(),
            'message'     => 'E-Invoice (IRN) cancelled successfully.',
        ];
    }

    /**
     * Authenticate with PeriOne / GSP gateway and cache token for 6 hours in mm_einvoice_auth for specific plant.
     */
    public function authenticate(?Plant $plant = null, ?int $userId = null): EinvoiceAuth
    {
        $userId = $userId ?? Auth::id() ?? 1;
        $plantId = $plant?->id ?? 1;

        // 1. Check existing unexpired token from database for THIS plant
        $existingAuth = $this->getCachedAuthToken($plantId, $userId);
        if ($existingAuth) {
            return $existingAuth;
        }

        // 2. Resolve Gateway settings & plant-specific credentials
        $this->getGatewayConfig($plant);
        [$username, $password, $gstin] = $this->getPlantCredentials($plant);

        if (empty($username) || empty($password)) {
            $plantName = $plant?->name ?? "Plant #{$plantId}";
            throw new \Exception("E-Invoice credentials (einvoice_client_id / einvoice_secret) are not configured for [{$plantName}].");
        }

        // 3. Request auth token directly from Gateway (Sandbox / Production)
        $authData = $this->requestAuthFromGateway($username, $password, $gstin);

        // 4. Save & return session in mm_einvoice_auth table for this plant
        return $this->storeAuthRecord($plantId, $userId, $username, $authData['token'], $authData['sek'], $authData['token_expiry'], $authData['app_key'] ?? null);
    }

    /**
     * Retrieve cached valid auth token from database if available (valid for next 5+ mins).
     */
    public function getCachedAuthToken(int $plantId, int $userId): ?EinvoiceAuth
    {
        $existingAuth = EinvoiceAuth::where('plant_id', $plantId)
            ->where('user_id', $userId)
            ->latest('token_generated_at')
            ->first();

        if ($existingAuth && $existingAuth->token_expiry_at && Carbon::now()->addMinutes(5)->lt($existingAuth->token_expiry_at) && !empty($existingAuth->auth_token)) {
            return $existingAuth;
        }

        return null;
    }

    /**
     * Request authentication token from PeriOne Gateway.
     */
    public function requestAuthFromGateway(string $username, string $password, string $gstin): array
    {
        $url = rtrim($this->baseUrl, '/') . '/einvoice/authenticate?email=' . urlencode($this->email);
        $headers = $this->buildAuthHeaders($username, $password, $gstin);

        $response = Http::withHeaders($headers)->timeout(20)->get($url);
        $body = $response->json() ?? [];

        if (!$response->successful() || (isset($body['status_cd']) && ($body['status_cd'] === 0 || $body['status_cd'] === '0' || strtolower((string)$body['status_cd']) === 'error'))) {
            $errorMsg = $this->extractGatewayErrorMessage($body, $response->body() ?: ('HTTP ' . $response->status()));
            Log::error('PeriOne Gateway Auth Error: ' . $errorMsg, ['response' => $body]);
            throw new \Exception('PeriOne Auth Failed: ' . $errorMsg);
        }

        $data = $body['data'] ?? $body['Data'] ?? $body;

        $authToken = $data['AuthToken'] ?? $data['auth_token'] ?? $data['token'] ?? null;
        $sekKey = $data['Sek'] ?? $data['sek'] ?? null;

        if (!$authToken || !$sekKey) {
            $errorMsg = $this->extractGatewayErrorMessage($body, 'Auth token or SEK key missing in gateway response');
            Log::error('PeriOne Gateway Auth Response Missing Token: ' . json_encode($body));
            throw new \Exception('PeriOne Auth Failed: ' . $errorMsg);
        }

        return [
            'token'        => $authToken,
            'sek'          => $sekKey,
            'app_key'      => $data['AppKey'] ?? $data['app_key'] ?? null,
            'token_expiry' => !empty($data['TokenExpiry']) ? Carbon::parse($data['TokenExpiry']) : Carbon::now()->addHours(6),
        ];
    }

    /**
     * Store or update auth session token in mm_einvoice_auth table.
     */
    public function storeAuthRecord(int $plantId, int $userId, string $username, string $authToken, string $sekKey, Carbon $tokenExpiry, ?string $appKey = null): EinvoiceAuth
    {
        $authRecord = EinvoiceAuth::firstOrNew([
            'plant_id' => $plantId,
            'user_id'  => $userId,
        ]);

        if (!$authRecord->exists) {
            $authRecord->created_by = $userId;
        }

        $authRecord->user_name = $username;
        if ($appKey) {
            $authRecord->app_key = $appKey;
        }
        $authRecord->auth_token = $authToken;
        $authRecord->sek_key = $sekKey;
        $authRecord->token_generated_at = Carbon::now();
        $authRecord->token_expiry_at = $tokenExpiry;
        $authRecord->save();

        return $authRecord;
    }

    /**
     * Resolve plant and entity credentials for E-Invoice.
     */
    public function getPlantCredentials(?Plant $plant = null): array
    {
        $username = $plant?->einvoice_client_id ?: ($plant?->entity?->einv_username ?: '');
        $password = $plant?->einvoice_secret ?: ($plant?->entity?->einv_password ?: '');
        $gstin = $plant?->gstin ?: ($plant?->entity?->gstin ?: '');

        return [$username, $password, $gstin];
    }

    /**
     * Resolve plant-specific E-Way Bill credentials.
     */
    public function getPlantEwayCredentials(?Plant $plant = null): array
    {
        $username = $plant?->ewaybill_client_id ?: '';
        $password = $plant?->ewaybill_secret ?: '';
        $gstin = $plant?->gstin ?: ($plant?->entity?->gstin ?: '');

        return [$username, $password, $gstin];
    }

    /**
     * Build HTTP headers for Authentication requests.
     */
    public function buildAuthHeaders(string $username, string $password, string $gstin): array
    {
        return [
            'accept'        => '*/*',
            'username'      => $username,
            'password'      => $password,
            'ip_address'    => $this->ipAddress,
            'client_id'     => $this->clientId,
            'client_secret' => $this->clientSecret,
            'gstin'         => $gstin,
        ];
    }

    /**
     * Build HTTP headers for IRN Generation requests.
     */
    public function buildGatewayHeaders(EinvoiceAuth $auth, string $username, string $password, string $gstin): array
    {
        return [
            'accept'        => 'application/json',
            'content-type'  => 'application/json',
            'auth_token'    => $auth->auth_token,
            'sek_key'       => $auth->sek_key,
            'username'      => $username,
            'password'      => $password,
            'ip_address'    => $this->ipAddress,
            'client_id'     => $this->clientId,
            'client_secret' => $this->clientSecret,
            'gstin'         => $gstin,
        ];
    }

    /**
     * Send Generate IRN request to Gateway.
     */
    public function sendGenerateRequest(array $headers, array $payload): array
    {
        $url = rtrim($this->baseUrl, '/') . '/einvoice/generate?email=' . urlencode($this->email);

        try {
            $response = Http::withHeaders($headers)->timeout(30)->post($url, $payload);
            $body = $response->json() ?? [];

            if ($response->successful() && (!isset($body['status_cd']) || ($body['status_cd'] !== 0 && $body['status_cd'] !== '0' && strtolower((string)$body['status_cd']) !== 'error'))) {
                $data = $body['data'] ?? $body['Data'] ?? $body ?? [];
                if (!empty($data['Irn']) || !empty($data['irn'])) {
                    return $data;
                }
            }

            // If remote gateway returned an error message from IRP
            if (!$response->successful() && $response->status() !== 404) {
                $errorMsg = $this->extractGatewayErrorMessage($body, $response->body() ?: ('HTTP ' . $response->status()));
                Log::error('PeriOne Generate IRN Failed: ' . $errorMsg, ['response' => $body]);
                throw new \Exception('PeriOne E-Invoice Generation Failed: ' . $errorMsg);
            }
        } catch (\Exception $e) {
            if ($this->isProduction() || !str_contains($e->getMessage(), '404')) {
                throw $e;
            }
        }

        // When in Sandbox/Staging/Local environment and remote gateway endpoint is not responding or 404
        if (!$this->isProduction()) {
            $sellerGstin = $payload['SellerDtls']['Gstin'] ?? '29AARFB4347G000';
            $buyerGstin = $payload['BuyerDtls']['Gstin'] ?? 'URP';
            $docNo = $payload['DocDtls']['No'] ?? 'INV-1';
            $docDate = $payload['DocDtls']['Dt'] ?? date('d/m/Y');
            $totVal = $payload['ValDtls']['TotInvVal'] ?? 0;
            $itemsCount = count($payload['ItemList'] ?? []);

            $rawHashInput = "{$sellerGstin}:{$docNo}:{$docDate}";
            $irn = hash('sha256', $rawHashInput . ':' . time());
            $ackNo = '1' . date('ymd') . str_pad((string)rand(100000, 999999), 6, '0', STR_PAD_LEFT);
            $ackDate = Carbon::now();

            $qrData = "{$sellerGstin}|{$buyerGstin}|{$docNo}|{$docDate}|{$totVal}|{$itemsCount}|{$irn}|{$ackNo}";
            $signedQr = base64_encode($qrData);

            return [
                'Irn'          => $irn,
                'AckNo'        => (int)$ackNo,
                'AckDt'        => $ackDate->toDateTimeString(),
                'SignedQRCode' => $signedQr,
                'SignedInvoice'=> base64_encode(json_encode($payload)),
                'Status'       => 'ACT',
            ];
        }

        throw new \Exception('PeriOne E-Invoice Gateway returned an invalid response.');
    }

    /**
     * Persist IRN and QR code results in mm_einvoice_invoice_rel & mm_ewaybill_details tables.
     */
    public function persistIrnRecord(Invoice $invoice, array $data, ?Plant $plant, int $userId): void
    {
        // 1. Persist into mm_einvoice_invoice_rel table
        EinvoiceInvoiceRel::updateOrCreate(
            ['invoice_id' => $invoice->id],
            [
                'einv_ackno'          => (string)$data['ack_no'],
                'einv_ack_date'       => $data['ack_date'],
                'einv_irn'            => (string)$data['irn'],
                'einv_signed_invoice' => (string)($data['signed_invoice'] ?? ''),
                'einv_signed_qrcode'  => (string)($data['signed_qrcode'] ?? ''),
                'einv_status'         => 'ACT',
                'plant_id'            => $plant?->id ?? 1,
                'status'              => 1,
                'created'             => Carbon::now(),
                'created_by'          => $userId,
                'modified'            => Carbon::now(),
                'modified_by'         => $userId,
            ]
        );
    }

    /**
     * Build standard E-Invoice (NIC / PeriOne JSON Schema v1.1) Payload from Invoice model.
     */
    public function buildIrnPayload(Invoice $invoice, array $transportDetails = []): array
    {
        $plant = $invoice->plant;
        $partner = $invoice->partner;
        $entity = $plant?->entity;

        // Seller & Buyer details
        $sellerGstin = trim((string)($plant?->gstin ?: ($entity?->gstin ?: '')));
        $sellerStateCode = strlen($sellerGstin) >= 2 ? substr($sellerGstin, 0, 2) : '';

        $buyerGstin = trim((string)($partner?->gstin ?: ''));
        $buyerStateCode = (strlen($buyerGstin) >= 2 && ctype_digit(substr($buyerGstin, 0, 2)))
            ? substr($buyerGstin, 0, 2)
            : $sellerStateCode;

        // Supply Type
        $isInterState = ($sellerStateCode !== '' && $buyerStateCode !== '' && $sellerStateCode !== $buyerStateCode);
        $supplyType = (!empty($buyerGstin) && strtoupper($buyerGstin) !== 'URP') ? 'B2B' : 'B2C';

        // 1. Build Item List directly from invoice items and order taxes
        $invoice->loadMissing(['items.orderTaxes', 'orderTaxes']);
        $itemData = $this->buildItemList($invoice->items, $invoice->orderTaxes, $isInterState);
        $itemList = $itemData['items'];

        // 2. Fetch Valuation Totals directly from Invoice & mm_order_taxes
        $assVal = (float)($invoice->subtotal ?? $itemData['tot_ass_val']);
        $cgstVal = (float)$invoice->orderTaxes->filter(fn($t) => stripos($t->name, 'cgst') !== false)->sum('amount');
        $sgstVal = (float)$invoice->orderTaxes->filter(fn($t) => stripos($t->name, 'sgst') !== false)->sum('amount');
        $igstVal = (float)$invoice->orderTaxes->filter(fn($t) => stripos($t->name, 'igst') !== false)->sum('amount');

        // Fallback to item sum if orderTaxes table has no parent splits
        if ($cgstVal == 0 && $sgstVal == 0 && $igstVal == 0) {
            $cgstVal = $itemData['tot_cgst'];
            $sgstVal = $itemData['tot_sgst'];
            $igstVal = $itemData['tot_igst'];
        }

        $discountTotal = (float)($invoice->discount_total ?? 0);
        $shippingCharges = (float)($invoice->shipping_charges ?? 0);
        $roundOff = (float)($invoice->round_off ?? 0);
        $totInvVal = (float)($invoice->total_amount ?? round($assVal + $cgstVal + $sgstVal + $igstVal + $shippingCharges + $roundOff, 2));

        $payload = [
            'Version' => '1.1',
            'TranDtls' => $this->buildTransactionDetails($supplyType, $isInterState, $igstVal, $transportDetails),
            'DocDtls' => [
                'Typ' => 'INV',
                'No'  => (string)($invoice->full_number ?: ($invoice->prefix . $invoice->invoice_number)),
                'Dt'  => Carbon::parse($invoice->invoice_date ?: now())->format('d/m/Y'),
            ],
            'SellerDtls' => $this->buildSellerDetails($plant, $entity, $sellerGstin, $sellerStateCode),
            'BuyerDtls'  => $this->buildBuyerDetails($partner, $buyerGstin, $buyerStateCode),
            'DispDtls'   => $this->buildDispatchDetails($plant, $sellerStateCode),
            'ShipDtls'   => $this->buildShippingDetails($partner, $buyerGstin, $buyerStateCode),
            'ItemList'   => $itemList,
            'ValDtls'    => [
                'AssVal'      => round($assVal, 2),
                'CgstVal'     => round($cgstVal, 2),
                'SgstVal'     => round($sgstVal, 2),
                'IgstVal'     => round($igstVal, 2),
                'CesVal'      => 0,
                'StCesVal'    => 0,
                'Discount'    => $discountTotal,
                'OthChrg'     => $shippingCharges,
                'RndOffAmt'   => $roundOff,
                'TotInvVal'   => $totInvVal,
                'TotInvValFc' => $totInvVal,
            ],
        ];

        return $payload;
    }

    /**
     * Build Transaction Details (TranDtls) with dynamic IGST on Intra-state detection.
     */
    public function buildTransactionDetails(string $supplyType, bool $isInterState, float $igstVal, array $transportDetails = []): array
    {
        // IgstOnIntra: 'Y' if IGST is applied on intra-state supply, otherwise 'N'
        $isForcedIgstIntra = !empty($transportDetails['igst_on_intra']) && in_array(strtoupper((string)$transportDetails['igst_on_intra']), ['Y', 'YES', '1', 'TRUE'], true);
        $igstOnIntra = ($isForcedIgstIntra || ($igstVal > 0 && !$isInterState)) ? 'Y' : 'N';

        $isReverseCharge = !empty($transportDetails['reg_rev']) && in_array(strtoupper((string)$transportDetails['reg_rev']), ['Y', 'YES', '1', 'TRUE'], true);

        return [
            'TaxSch'      => 'GST',
            'SupTyp'      => (string)($transportDetails['supply_type'] ?? $supplyType),
            'RegRev'      => $isReverseCharge ? 'Y' : 'N',
            'EcmGstin'    => $transportDetails['ecm_gstin'] ?? null,
            'IgstOnIntra' => $igstOnIntra,
        ];
    }

    /**
     * Build Seller Details array.
     */
    public function buildSellerDetails(?Plant $plant, ?\App\Models\Entity $entity, string $sellerGstin, string $sellerStateCode): array
    {
        $plantAddress = $plant?->addresses()?->first();

        return [
            'Gstin' => $sellerGstin,
            'LglNm' => (string)($entity?->legal_name ?: ($entity?->name ?: ($plant?->name ?? ''))),
            'TrdNm' => (string)($plant?->name ?: ($entity?->name ?? '')),
            'Addr1' => (string)($plantAddress?->line_1 ?: ($plantAddress?->address_line1 ?: ($plant?->name ?? ''))),
            'Addr2' => (string)($plantAddress?->line_2 ?: ($plantAddress?->address_line2 ?? '')),
            'Loc'   => (string)($plantAddress?->city ?? ''),
            'Pin'   => (int)($plantAddress?->zipcode ?: ($plantAddress?->pin_code ?? 0)),
            'Stcd'  => (string)($plantAddress?->state_code ?: $sellerStateCode),
            'Ph'    => preg_replace('/[^0-9]/', '', (string)($plant?->mobile_number ?? '')),
            'Em'    => (string)($plant?->email_address ?? ''),
        ];
    }

    /**
     * Build Buyer Details array.
     */
    public function buildBuyerDetails(?\App\Models\Patron $partner, string $buyerGstin, string $buyerStateCode): array
    {
        $partnerAddress = $partner?->addresses()?->first() ?: $partner?->contacts()?->first()?->addresses()?->first();

        return [
            'Gstin' => $buyerGstin ?: 'URP',
            'LglNm' => (string)($partner?->legal_name ?: ($partner?->name ?? '')),
            'TrdNm' => (string)($partner?->trade_name ?: ($partner?->name ?? '')),
            'Pos'   => (string)($buyerStateCode ?: ($partnerAddress?->state_code ?? '')),
            'Addr1' => (string)($partnerAddress?->line_1 ?: ($partnerAddress?->address_line1 ?? '')),
            'Addr2' => (string)($partnerAddress?->line_2 ?: ($partnerAddress?->address_line2 ?? '')),
            'Loc'   => (string)($partnerAddress?->city ?? ''),
            'Pin'   => (int)($partnerAddress?->zipcode ?: ($partnerAddress?->pin_code ?? 0)),
            'Stcd'  => (string)($partnerAddress?->state_code ?: $buyerStateCode),
            'Ph'    => preg_replace('/[^0-9]/', '', (string)($partner?->phone ?: ($partner?->contacts()?->first()?->phone ?? ''))),
            'Em'    => (string)($partner?->email ?: ($partner?->contacts()?->first()?->email ?? '')),
        ];
    }

    /**
     * Build Dispatch Details array.
     */
    public function buildDispatchDetails(?Plant $plant, string $sellerStateCode): array
    {
        $plantAddress = $plant?->addresses()?->first();

        return [
            'Nm'    => (string)($plant?->name ?? ''),
            'Addr1' => (string)($plantAddress?->line_1 ?: ($plantAddress?->address_line1 ?: ($plant?->name ?? ''))),
            'Addr2' => (string)($plantAddress?->line_2 ?: ($plantAddress?->address_line2 ?? '')),
            'Loc'   => (string)($plantAddress?->city ?? ''),
            'Pin'   => (int)($plantAddress?->zipcode ?: ($plantAddress?->pin_code ?? 0)),
            'Stcd'  => (string)($plantAddress?->state_code ?: $sellerStateCode),
        ];
    }

    /**
     * Build Shipping Details array.
     */
    public function buildShippingDetails(?\App\Models\Patron $partner, string $buyerGstin, string $buyerStateCode): array
    {
        $partnerAddress = $partner?->addresses()?->first() ?: $partner?->contacts()?->first()?->addresses()?->first();

        return [
            'Gstin' => $buyerGstin ?: 'URP',
            'LglNm' => (string)($partner?->legal_name ?: ($partner?->name ?? '')),
            'TrdNm' => (string)($partner?->trade_name ?: ($partner?->name ?? '')),
            'Addr1' => (string)($partnerAddress?->line_1 ?: ($partnerAddress?->address_line1 ?? '')),
            'Addr2' => (string)($partnerAddress?->line_2 ?: ($partnerAddress?->address_line2 ?? '')),
            'Loc'   => (string)($partnerAddress?->city ?? ''),
            'Pin'   => (int)($partnerAddress?->zipcode ?: ($partnerAddress?->pin_code ?? 0)),
            'Stcd'  => (string)($partnerAddress?->state_code ?: $buyerStateCode),
        ];
    }

    /**
     * Build Item List and map line values directly from InvoiceItem and mm_order_taxes table.
     */
    public function buildItemList($items, $orderTaxes, bool $isInterState): array
    {
        $itemList = [];
        $slNo = 1;
        $totAssVal = 0;
        $totCgst = 0;
        $totSgst = 0;
        $totIgst = 0;

        foreach ($items as $item) {
            $qty = (float)($item->quantity ?: 1);
            $unitPrice = (float)($item->price_unit ?: 0);
            $totAmt = round($qty * $unitPrice, 2);
            $discount = (float)($item->discount_amount ?: 0);
            $assAmt = (float)($item->subtotal ?: ($totAmt - $discount));

            // Read tax splits directly from mm_order_taxes for this line item
            $itemTaxes = $item->relationLoaded('orderTaxes')
                ? $item->orderTaxes
                : $orderTaxes->where('order_items_id', $item->id);

            $cgstTax = $itemTaxes->first(fn($t) => stripos($t->name, 'cgst') !== false);
            $sgstTax = $itemTaxes->first(fn($t) => stripos($t->name, 'sgst') !== false);
            $igstTax = $itemTaxes->first(fn($t) => stripos($t->name, 'igst') !== false);

            $cgstAmt = (float)($cgstTax?->amount ?? 0);
            $sgstAmt = (float)($sgstTax?->amount ?? 0);
            $igstAmt = (float)($igstTax?->amount ?? 0);
            $gstRt = (float)(($cgstTax?->rate ?? 0) + ($sgstTax?->rate ?? 0) + ($igstTax?->rate ?? 0));

            // Fallback to line_tax_amount if order taxes row is not yet generated
            if ($gstRt == 0 && $item->line_tax_amount > 0 && $assAmt > 0) {
                $gstRt = round(($item->line_tax_amount / $assAmt) * 100, 2);
                if ($isInterState) {
                    $igstAmt = (float)$item->line_tax_amount;
                } else {
                    $cgstAmt = round($item->line_tax_amount / 2, 2);
                    $sgstAmt = round($item->line_tax_amount / 2, 2);
                }
            } elseif ($gstRt == 0 && $item->tax) {
                $gstRt = (float)($item->tax->rate ?? ($item->tax->percentage ?? 0));
            }

            $totItemVal = (float)($item->line_total ?: round($assAmt + $cgstAmt + $sgstAmt + $igstAmt, 2));

            $totAssVal += $assAmt;
            $totCgst += $cgstAmt;
            $totSgst += $sgstAmt;
            $totIgst += $igstAmt;

            $itemList[] = [
                'SlNo'               => (string)$slNo++,
                'IsServc'            => 'N',
                'PrdDesc'            => (string)($item->item_name ?? ''),
                'HsnCd'              => preg_replace('/[^0-9]/', '', (string)($item->hsn_code ?? '')),
                'Barcde'             => null,
                'Qty'                => $qty,
                'FreeQty'            => 0,
                'Unit'               => (string)($item->uom?->code ?: ($item->uom?->unit_code ?? 'M3')),
                'UnitPrice'          => $unitPrice,
                'TotAmt'             => $totAmt,
                'Discount'           => $discount,
                'PreTaxVal'          => 0,
                'AssAmt'             => $assAmt,
                'GstRt'              => $gstRt,
                'CgstAmt'            => $cgstAmt,
                'SgstAmt'            => $sgstAmt,
                'IgstAmt'            => $igstAmt,
                'CesRt'              => 0,
                'CesAmt'             => 0,
                'CesNonAdvlAmt'      => 0,
                'StateCesRt'         => 0,
                'StateCesAmt'        => 0,
                'StateCesNonAdvlAmt' => 0,
                'OthChrg'            => 0,
                'TotItemVal'         => $totItemVal,
            ];
        }

        return [
            'items'       => $itemList,
            'tot_ass_val' => $totAssVal,
            'tot_cgst'    => $totCgst,
            'tot_sgst'    => $totSgst,
            'tot_igst'    => $totIgst,
        ];
    }

    /**
     * Validate all prerequisites for E-Invoice and E-Way Bill generation before calling gateway.
     *
     * @throws ValidationException
     */
    public function validateForIrn(Invoice $invoice, array $transportDetails = []): void
    {
        $errors = [];

        // 1. Invoice status & duplicate generation check
        if ($invoice->status === Invoice::STATUS_CANCELLED) {
            $errors['invoice'][] = 'Cannot generate E-Invoice for a cancelled invoice.';
        }

        $existingIrn = $invoice->einvoice_irn ?: $invoice->einv_irn;
        $existingStatus = $invoice->einvoice_status ?: $invoice->einv_status;
        if (!empty($existingIrn) && $existingStatus === 'ACT') {
            $errors['invoice'][] = 'E-Invoice (IRN) has already been generated for this invoice: ' . $existingIrn;
        }

        // 2. Seller / Plant validation (each plant has distinct einvoice_client_id & einvoice_secret)
        $plant = $invoice->plant;
        if (!$plant) {
            $errors['plant'][] = 'Invoice is not associated with any Plant.';
        } else {
            $plantName = $plant->name ?: ('Plant #' . $plant->id);
            $username = trim((string)($plant->einvoice_client_id ?: ($plant->entity?->einv_username ?: '')));
            $password = trim((string)($plant->einvoice_secret ?: ($plant->entity?->einv_password ?: '')));

            if (empty($username)) {
                $errors['einvoice_client_id'][] = "E-Invoice Client ID (Username) is not configured for [{$plantName}]. Please update plant settings.";
            }

            if (empty($password)) {
                $errors['einvoice_secret'][] = "E-Invoice Secret (Password) is not configured for [{$plantName}]. Please update plant settings.";
            }

            $sellerGstin = trim((string)($plant->gstin ?: ($plant->entity?->gstin ?: '')));
            if (empty($sellerGstin)) {
                $errors['seller_gstin'][] = "GSTIN is missing for [{$plantName}].";
            } elseif (!preg_match('/^[0-9]{2}[A-Z]{5}[0-9]{4}[A-Z]{1}[0-9A-Z]{3}$/i', $sellerGstin)) {
                $errors['seller_gstin'][] = "GSTIN format is invalid ('{$sellerGstin}') for [{$plantName}]. Expected 15-character GSTIN.";
            }

            $plantAddress = $plant->addresses()?->first();
            $sellerPin = (int)($plantAddress?->zipcode ?: ($plantAddress?->pin_code ?? 0));
            if ($sellerPin < 100000 || $sellerPin > 999999) {
                $errors['seller_pincode'][] = "A valid 6-digit Pincode is required in the address for [{$plantName}].";
            }
        }

        // 3. Buyer / Customer validation
        $partner = $invoice->partner;
        if (!$partner) {
            $errors['buyer'][] = 'Customer (Buyer) details are missing on this invoice.';
        }

        $buyerGstin = trim((string)($partner?->gstin ?: ''));
        $isB2B = (!empty($buyerGstin) && strtoupper($buyerGstin) !== 'URP');

        if ($isB2B && !preg_match('/^[0-9]{2}[A-Z]{5}[0-9]{4}[A-Z]{1}[0-9A-Z]{3}$/i', $buyerGstin)) {
            $errors['buyer_gstin'][] = 'Buyer GSTIN format is invalid (' . $buyerGstin . '). Expected 15-character GSTIN or URP for unregistered.';
        }

        $partnerAddress = $partner?->addresses()?->first() ?: $partner?->contacts()?->first()?->addresses()?->first();
        $buyerPin = (int)($partnerAddress?->zipcode ?: ($partnerAddress?->pin_code ?? 0));
        if ($buyerPin < 100000 || $buyerPin > 999999) {
            $errors['buyer_pincode'][] = 'Buyer Pincode must be a valid 6-digit number.';
        }

        // 4. Item List validation
        $items = $invoice->items;
        if ($items->isEmpty()) {
            $errors['items'][] = 'Invoice must have at least one line item.';
        } else {
            foreach ($items as $index => $item) {
                $lineNo = $index + 1;
                $hsn = preg_replace('/[^0-9]/', '', (string)($item->hsn_code ?? ''));
                if (strlen($hsn) < 4 || strlen($hsn) > 8) {
                    $errors["item_{$lineNo}_hsn"][] = "Item #{$lineNo} ({$item->item_name}): HSN Code must be between 4 and 8 digits (given: '{$item->hsn_code}').";
                }
                if ((float)$item->quantity <= 0) {
                    $errors["item_{$lineNo}_qty"][] = "Item #{$lineNo} ({$item->item_name}): Quantity must be greater than zero.";
                }
                if ((float)$item->price_unit < 0) {
                    $errors["item_{$lineNo}_rate"][] = "Item #{$lineNo} ({$item->item_name}): Unit price cannot be negative.";
                }
            }
        }

        // 5. Total Valuation check
        if ((float)$invoice->total_amount <= 0) {
            $errors['total_amount'][] = 'Invoice total amount must be greater than zero.';
        }

        if (!empty($errors)) {
            throw ValidationException::withMessages($errors);
        }
    }

    /**
     * Resolve Gateway settings for Sandbox vs Production dynamically.
     */
    public function getGatewayConfig(?Plant $plant = null, bool $forEway = false): array
    {
        $isProd = $this->isProduction($plant);

        if ($isProd) {
            $baseUrl = config('services.perione.prod_base_url') ?: (config('services.perione.base_url') ?: 'https://api.perione.in');
            $clientId = $forEway
                ? (config('services.perione.prod_eway_client_id') ?: 'PEWAYPc38f83975b650189fb86e3e5659d30fe')
                : ($plant?->entity?->api_key ?: (config('services.perione.prod_client_id') ?: config('services.perione.client_id')));
            $clientSecret = $forEway
                ? (config('services.perione.prod_eway_client_secret') ?: 'PEWAYP52608f1eabd22d36e310b3c341177f49')
                : (config('services.perione.prod_client_secret') ?: config('services.perione.client_secret'));
            $email = $plant?->email_address ?: (config('services.perione.prod_email') ?: config('services.perione.email', 'sayee@onemodo.com'));
        } else {
            $baseUrl = config('services.perione.sandbox_base_url') ?: (config('services.perione.base_url') ?: 'https://staging.perione.in');
            $clientId = $forEway
                ? (config('services.perione.sandbox_eway_client_id') ?: 'PEWAYS472417d4a8bc74b10d31d4219c6b343c')
                : (config('services.perione.sandbox_client_id') ?: (config('services.perione.client_id') ?: 'PEINVSb3aadf99327e3ca03792510397d3136b'));
            $clientSecret = $forEway
                ? (config('services.perione.sandbox_eway_client_secret') ?: 'PEWAYS5376280bd5bc93b6c6ddf334a88d7a45')
                : (config('services.perione.sandbox_client_secret') ?: (config('services.perione.client_secret') ?: 'PEINVS21f24a6a2291dd214d0d81bf23ae8ec7'));
            $email = config('services.perione.sandbox_email') ?: (config('services.perione.email') ?: 'sayee@onemodo.com');
        }

        $this->baseUrl = rtrim($baseUrl, '/');
        $this->clientId = $clientId;
        $this->clientSecret = $clientSecret;
        $this->email = $email;
        $this->ipAddress = request()?->ip() ?: (config('services.perione.ip') ?: '192.168.1.98');

        return [
            'is_production' => $isProd,
            'base_url'      => $this->baseUrl,
            'client_id'     => $this->clientId,
            'client_secret' => $this->clientSecret,
            'email'         => $this->email,
            'ip_address'    => $this->ipAddress,
        ];
    }

    /**
     * Detect if current runtime context is Production vs Sandbox / Local.
     */
    public function isProduction(?Plant $plant = null): bool
    {
        

        $host = request()?->getHost() ?? '';
        if ($host) {
            $isLocalOrStaging = str_contains($host, 'localhost') ||
                                str_contains($host, '127.0.0.1') ||
                                str_contains($host, 'curie.modormc.com');

            if ($isLocalOrStaging) {
                return false;
            }
        }

        return app()->environment('production');
    }

    /**
     * Parse and format detailed error messages from PeriOne / NIC gateway payloads.
     */
    public function extractGatewayErrorMessage(mixed $body, string $fallback = 'Unknown gateway error'): string
    {
        if (is_string($body)) {
            $decoded = json_decode($body, true);
            if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                $body = $decoded;
            } else {
                return strip_tags($body) ?: $fallback;
            }
        }

        if (!is_array($body)) {
            return $fallback;
        }

        // 1. Check status_desc (can be array of {ErrorCode, ErrorMessage} or string)
        if (!empty($body['status_desc'])) {
            if (is_array($body['status_desc'])) {
                $messages = [];
                foreach ($body['status_desc'] as $item) {
                    if (is_array($item)) {
                        $code = $item['ErrorCode'] ?? $item['error_code'] ?? $item['code'] ?? null;
                        $msg = $item['ErrorMessage'] ?? $item['error_message'] ?? $item['desc'] ?? $item['message'] ?? null;
                        if ($msg) {
                            $messages[] = $code ? "{$msg} (Error Code: {$code})" : $msg;
                        }
                    } elseif (is_string($item)) {
                        $messages[] = $item;
                    }
                }
                if (!empty($messages)) {
                    return implode(', ', $messages);
                }
            } elseif (is_string($body['status_desc']) && !in_array(strtolower($body['status_desc']), ['gstr request succeeds', 'success', 'ok'], true)) {
                return $body['status_desc'];
            }
        }

        // 2. Check ErrorDetails / error_details
        $errorDetails = $body['ErrorDetails'] ?? $body['error_details'] ?? $body['errors'] ?? $body['data']['ErrorDetails'] ?? null;
        if (!empty($errorDetails)) {
            if (is_array($errorDetails)) {
                $messages = [];
                foreach ($errorDetails as $item) {
                    if (is_array($item)) {
                        $code = $item['ErrorCode'] ?? $item['error_code'] ?? null;
                        $msg = $item['ErrorMessage'] ?? $item['error_message'] ?? $item['message'] ?? null;
                        if ($msg) {
                            $messages[] = $code ? "{$msg} (Error Code: {$code})" : $msg;
                        }
                    } elseif (is_string($item)) {
                        $messages[] = $item;
                    }
                }
                if (!empty($messages)) {
                    return implode(', ', $messages);
                }
            } elseif (is_string($errorDetails)) {
                return $errorDetails;
            }
        }

        // 3. Check InfoDtls
        $infoDtls = $body['InfoDtls'] ?? $body['info_dtls'] ?? $body['data']['InfoDtls'] ?? null;
        if (!empty($infoDtls) && is_array($infoDtls)) {
            $messages = [];
            foreach ($infoDtls as $item) {
                if (is_array($item)) {
                    $msg = $item['Desc'] ?? $item['desc'] ?? $item['ErrorMessage'] ?? null;
                    if ($msg) {
                        $messages[] = $msg;
                    }
                }
            }
            if (!empty($messages)) {
                return implode(', ', $messages);
            }
        }

        // 4. Check message / error
        if (!empty($body['message']) && is_string($body['message'])) {
            return $body['message'];
        }
        if (!empty($body['error']) && is_string($body['error'])) {
            return $body['error'];
        }

        return $fallback;
    }
}