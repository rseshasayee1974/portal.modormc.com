@php
    // Determine Invoice Model instance
    if (isset($invoice) && is_object($invoice)) {
        $inv = $invoice;
    } elseif (isset($data['invoice']) && is_object($data['invoice'])) {
        $inv = $data['invoice'];
    } elseif (isset($data['id'])) {
        $inv = \App\Models\Invoice::with([
            'plant.entity.bankAccounts',
            'partner.addresses',
            'items.tax',
            'items.uom',
        ])->find($data['id']);
    } else {
        $inv = $invoice ?? null;
    }

    $plant =
        $inv?->plant ?? \App\Models\Plant::with(['entity.bankAccounts', 'addresses'])->find(session('active_plant_id'));
    $entity = $plant?->entity;
    $partner = $inv?->partner;

    // Resolve Dispatches, Sales Orders, Customer POs if available
    $dispatches = \App\Models\Dispatch::with([
        'mixDesign',
        'truck',
        'driver',
        'transport',
        'salesOrder.site',
        'salesOrder.salesExecutive',
        'salesOrder.customerPO',
        'salesExecutive',
        'customerPO',
        'concretePump',
    ])
        ->whereHas('status', function ($q) use ($inv) {
            if ($inv) {
                $q->where('invoice_id', $inv->id);
            }
        })
        ->get();

    if ($dispatches->isEmpty() && $inv?->ref_id) {
        $refIds = array_filter(array_map('trim', explode(',', $inv->ref_id)));
        $dispatches = \App\Models\Dispatch::with([
            'mixDesign',
            'truck',
            'driver',
            'transport',
            'salesOrder.site',
            'salesOrder.salesExecutive',
            'salesOrder.customerPO',
            'salesExecutive',
            'customerPO',
            'concretePump',
        ])
            ->whereIn('id', $refIds)
            ->get();
    }

    $firstDispatch = $dispatches->first();
    $salesOrder = $firstDispatch?->salesOrder;
    $customerPO = $firstDispatch?->customerPO ?? $salesOrder?->customerPO;

    // Copy Type: ORIGINAL / DUPLICATE / TRIPLICATE / EXTRA COPY
    $isDup = isset($data['is_duplicate']) ? (bool)$data['is_duplicate'] : (!empty($inv?->is_duplicate));
    $copyType = strtoupper($copy_type ?? ($data['copy_type'] ?? ($isDup ? 'DUPLICATE' : 'ORIGINAL')));

    // Company & Addresses
    $companyName = $plant?->name ?? ($entity?->legal_name ?? '');

    // Reg Address
    $regAddress =
        $entity?->addresses()?->first() ??
        ($plant?->addresses()?->first() ?? $entity?->addresses()?->first());
    $plantAddress = $plant?->addresses()?->first() ?? $regAddress;

    // Contact Details
    $mobile = $plant?->mobile_number ?? ($entity?->mobile ?? '');
    $telephone = $entity?->telephone ?? '';
    $companyGstin = $plant?->gstin ?? ($entity?->gstin ?? '');
    $stateName = $regAddress?->state?->name ?? '';
    $stateCode = $regAddress?->state_code ?? ($regAddress?->state?->state_code ?? '');
    $pan =
        $entity?->pan ??
        (!empty($companyGstin) && strlen($companyGstin) >= 12 ? substr($companyGstin, 2, 10) : '');
    $msmeNo = $entity?->msme_no ?? ($plant?->msme_no ?? '');

    // Customer Billing & Shipping
    $billingAddress = $partner?->addresses()?->first() ?? $partner?->addresses()?->first();
    $shippingSite = $salesOrder?->site ?? $firstDispatch?->site;
    $shippingAddress = $shippingSite?->address ?? $billingAddress;

    // Customer Ref
    $accNo = $partner?->code ?? ($partner?->reference ?? ($partner?->id ? 'AC-' . $partner->id : ''));
    $poRef = $customerPO?->customer_po_reference 
        ?? ($customerPO?->reference 
        ?? ($salesOrder?->customer_po_reference 
        ?? ($salesOrder?->po_number 
        ?? ($firstDispatch?->customer_po_reference 
        ?? ($inv?->ref_title ?? '')))));

    $salesPerson = $firstDispatch?->salesExecutive ?? $salesOrder?->salesExecutive;
    $salesPersonName = $salesPerson
        ? trim(($salesPerson->first_name ?? '') . ' ' . ($salesPerson->last_name ?? ''))
        : '';

    $pumpName = '';
    if ($firstDispatch?->concretePump) {
        $pumpName = $firstDispatch->concretePump->registration ?: $firstDispatch->concretePump->name ?? '';
    } elseif (!empty($firstDispatch?->concrete_pump)) {
        $pumpName = is_numeric($firstDispatch->concrete_pump)
            ? \App\Models\Machine::find($firstDispatch->concrete_pump)?->registration ?? ''
            : ucwords(str_replace('_', ' ', $firstDispatch->concrete_pump));
    }

    $mixDesignObj = $firstDispatch?->mixDesign ?? $salesOrder?->mixDesign;
    $designMixRef = $mixDesignObj?->concrete_grade?->name ?? ($mixDesignObj?->concreteGrade?->name ?? ($mixDesignObj?->design_type ?? ($mixDesignObj?->design_name ?? '')));

    // Carrier & Driver
    $transporterName = $firstDispatch?->transport?->name ?? '';
    $truckReg = $firstDispatch?->truck?->registration ?? '';
    $driverName = $firstDispatch?->driver
        ? trim(($firstDispatch->driver->first_name ?? '') . ' ' . ($firstDispatch->driver->last_name ?? ''))
        : '';
    $carrierDriverParts = array_filter([$transporterName, array_filter([$truckReg, $driverName]) ? implode(' - ', array_filter([$truckReg, $driverName])) : '']);
    $carrierDriver = implode(' , ', $carrierDriverParts);

    // Invoice Meta
    $invoiceNo = $inv?->full_number ?? ($inv?->invoice_number ?? '');
    $invoiceDate = $inv?->invoice_date ? \Carbon\Carbon::parse($inv->invoice_date)->format('d-m-Y') : '';
    $soNo = $salesOrder?->order_no
        ? ($salesOrder->prefix ?? '') . $salesOrder->order_no
        : ($inv?->ref_id
            ? 'RS/04/26-27/' . str_pad($inv->ref_id, 5, '0', STR_PAD_LEFT)
            : '');
    $ewayBillNo = $inv?->eway_bill_no ?? '';

    // E-Invoice
    $einvoiceRel = $inv?->einvoiceRelation;
    $irn = $inv?->einvoice_irn ?? ($einvoiceRel?->einv_irn ?? '');
    $ackNo = $inv?->einvoice_ack_no ?? ($einvoiceRel?->einv_ackno ?? '');
    $ackDate = $inv?->einvoice_ack_date ? \Carbon\Carbon::parse($inv->einvoice_ack_date)->format('d/m/Y') : ($einvoiceRel?->einv_ack_date ? \Carbon\Carbon::parse($einvoiceRel->einv_ack_date)->format('d/m/Y') : '');
    $qrCode = $inv?->einvoice_qr_code ?? ($einvoiceRel?->einv_signed_qrcode ?? '');

    // Bank Account
    $bankAccount = $entity?->bankAccounts()?->first() ?? $entity?->bankAccounts()?->first();
    $bankAccountName = $bankAccount?->account_name ?? $companyName;
    $bankAccountNo = $bankAccount?->account_number ?? ($bankAccount?->bank_account_no ?? '');
    $bankName = $bankAccount?->bank_name ?? '';
    $bankBranch = $bankAccount?->bank_branch ?? ($bankAccount?->branch ?? '');
    $bankIfsc = $bankAccount?->bank_ifsc ?? ($bankAccount?->ifsc_code ?? '');

    // Helper for Number to Words (Indian Rupee format)
    if (!function_exists('taxInvoiceNumberToWords')) {
        function taxInvoiceNumberToWords($num)
        {
            $num = (float) $num;
            $whole = floor($num);
            $fraction = round(($num - $whole) * 100);

            $ones = [
                0 => '',
                1 => 'One',
                2 => 'Two',
                3 => 'Three',
                4 => 'Four',
                5 => 'Five',
                6 => 'Six',
                7 => 'Seven',
                8 => 'Eight',
                9 => 'Nine',
                10 => 'Ten',
                11 => 'Eleven',
                12 => 'Twelve',
                13 => 'Thirteen',
                14 => 'Fourteen',
                15 => 'Fifteen',
                16 => 'Sixteen',
                17 => 'Seventeen',
                18 => 'Eighteen',
                19 => 'Nineteen',
            ];
            $tens = [
                2 => 'Twenty',
                3 => 'Thirty',
                4 => 'Forty',
                5 => 'Fifty',
                6 => 'Sixty',
                7 => 'Seventy',
                8 => 'Eighty',
                9 => 'Ninety',
            ];

            $convertGroup = function ($n) use ($ones, $tens) {
                $str = '';
                if ($n >= 100) {
                    $str .= $ones[floor($n / 100)] . ' Hundred ';
                    $n %= 100;
                }
                if ($n >= 20) {
                    $str .= $tens[floor($n / 10)] . ' ';
                    $n %= 10;
                }
                if ($n > 0) {
                    $str .= $ones[$n] . ' ';
                }
                return trim($str);
            };

            if ($whole == 0) {
                $words = 'Zero';
            } else {
                $crore = floor($whole / 10000000);
                $whole %= 10000000;
                $lakh = floor($whole / 100000);
                $whole %= 100000;
                $thousand = floor($whole / 1000);
                $whole %= 1000;
                $hundreds = $whole;

                $parts = [];
                if ($crore > 0) {
                    $parts[] = $convertGroup($crore) . ' Crore';
                }
                if ($lakh > 0) {
                    $parts[] = $convertGroup($lakh) . ' Lakh';
                }
                if ($thousand > 0) {
                    $parts[] = $convertGroup($thousand) . ' Thousand';
                }
                if ($hundreds > 0) {
                    $parts[] = $convertGroup($hundreds);
                }

                $words = implode(' ', $parts);
            }

            $result = 'Rs. ' . trim($words);
            if ($fraction > 0) {
                $result .= ' and ' . $convertGroup($fraction) . ' Paise';
            }
            $result .= ' Only';
            return $result;
        }
    }

    // Tax Calculations
    $items = $inv?->items ?? collect();
    $totalTaxable = 0;
    $totalCgst = 0;
    $totalSgst = 0;
    $totalIgst = 0;
    $isIntra = true;

    if (!empty($companyGstin) && !empty($partner?->gstin)) {
        $isIntra = substr($companyGstin, 0, 2) === substr($partner->gstin, 0, 2);
    }

    foreach ($items as $it) {
        $sub = (float) ($it->subtotal ?? $it->quantity * $it->price_unit);
        $taxAmt = (float) ($it->line_tax_amount ?? 0);
        $totalTaxable += $sub;
        if ($isIntra) {
            $totalCgst += $taxAmt / 2;
            $totalSgst += $taxAmt / 2;
        } else {
            $totalIgst += $taxAmt;
        }
    }

    $roundOff = (float) ($inv?->round_off ?? 0);
    $grandTotal = (float) ($inv?->total_amount ?? $totalTaxable + $totalCgst + $totalSgst + $totalIgst + $roundOff);
@endphp
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>TAX INVOICE - {{ $invoiceNo }}</title>
    <style>
        @page {
            size: A4 portrait;
            margin: 8mm 8mm 8mm 8mm;
        }

        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }

        body {
            font-family: Arial, Helvetica, sans-serif;
            font-size: 8.5pt;
            color: #000;
            background: #fff;
            line-height: 1.25;
            -webkit-print-color-adjust: exact;
            print-color-adjust: exact;
        }

        .invoice-container {
            width: 100%;
            max-width: 210mm;
            margin: 0 auto;
            border: 1.5px solid #000;
        }

        /* Top Header */
        .header-table {
            width: 100%;
            border-collapse: collapse;
        }

        .header-table td {
            vertical-align: middle;
            padding: 4px 6px;
        }

        .company-logo {
            max-height: 48px;
            max-width: 120px;
            object-fit: contain;
        }

        .company-title {
            font-size: 13pt;
            font-weight: bold;
            text-align: center;
            letter-spacing: 0.5px;
        }

        .invoice-type-tag {
            text-align: right;
            font-size: 8.5pt;
            font-weight: bold;
            line-height: 1.2;
        }

        .invoice-type-tag .copy-title {
            font-size: 9pt;
        }

        /* IRN & Compliance Bar */
        .compliance-bar {
            width: 100%;
            border-top: 1px solid #000;
            border-bottom: 1px solid #000;
            border-collapse: collapse;
        }

        .compliance-bar td {
            padding: 3px 6px;
            font-size: 7.5pt;
            vertical-align: middle;
        }

        .qr-cell {
            width: 70px;
            text-align: right;
            padding-right: 6px;
        }

        .qr-img {
            max-height: 60px;
            max-width: 60px;
        }

        /* 3-Column Info Boxes */
        .info-grid-table {
            width: 100%;
            border-collapse: collapse;
            border-bottom: 1px solid #000;
        }

        .info-grid-table td {
            width: 33.333%;
            vertical-align: top;
            padding: 4px 6px;
            border-right: 1px solid #000;
            font-size: 7.5pt;
        }

        .info-grid-table td:last-child {
            border-right: none;
        }

        .box-heading {
            font-weight: bold;
            font-size: 8pt;
            border-bottom: 1px solid #000;
            padding-bottom: 2px;
            margin-bottom: 3px;
        }

        .info-line {
            margin-bottom: 1.5px;
            line-height: 1.2;
        }

        .info-line strong {
            font-weight: bold;
        }

        /* Full-width Carrier Bar */
        .carrier-row {
            width: 100%;
            border-bottom: 1px solid #000;
            padding: 3px 6px;
            font-size: 8pt;
            font-weight: normal;
        }

        .carrier-row strong {
            font-weight: bold;
        }

        /* Main Items Table */
        .items-table {
            width: 100%;
            border-collapse: collapse;
            border-bottom: 1px solid #000;
        }

        .items-table th {
            font-size: 7.5pt;
            font-weight: bold;
            border: 1px solid #000;
            border-top: none;
            padding: 4px 3px;
            text-align: center;
            background-color: #fdfdfd;
        }

        .items-table td {
            font-size: 7.5pt;
            border-right: 1px solid #000;
            border-left: 1px solid #000;
            padding: 4px 4px;
            vertical-align: middle;
        }

        .text-center {
            text-align: center;
        }

        .text-right {
            text-align: right;
        }

        .text-left {
            text-align: left;
        }

        .font-bold {
            font-weight: bold;
        }

        /* Inner Tax Breakdown Table */
        .tax-subtable {
            width: 100%;
            border-collapse: collapse;
        }

        .tax-subtable td {
            border: none;
            padding: 1px 2px;
            font-size: 7.5pt;
        }

        /* Totals and Words Area */
        .summary-table {
            width: 100%;
            border-collapse: collapse;
            border-bottom: 1px solid #000;
        }

        .summary-table td {
            vertical-align: top;
            padding: 4px 6px;
            font-size: 8pt;
        }

        .words-col {
            width: 65%;
            border-right: 1px solid #000;
            line-height: 1.35;
        }

        .calc-col {
            width: 35%;
            padding: 0 !important;
        }

        .calc-subtable {
            width: 100%;
            border-collapse: collapse;
        }

        .calc-subtable td {
            padding: 3px 6px;
            font-size: 8pt;
        }

        .calc-subtable tr {
            border-bottom: 1px solid #000;
        }

        .calc-subtable tr:last-child {
            border-bottom: none;
        }

        /* Terms & Bank Information */
        .terms-bank-table {
            width: 100%;
            border-collapse: collapse;
            border-bottom: 1px solid #000;
        }

        .terms-bank-table td {
            width: 50%;
            vertical-align: top;
            padding: 4px 6px;
            font-size: 7pt;
            line-height: 1.25;
        }

        .terms-bank-table td:first-child {
            border-right: 1px solid #000;
        }

        .terms-list {
            list-style: none;
            padding-left: 0;
        }

        .terms-list li {
            margin-bottom: 2px;
            position: relative;
            padding-left: 8px;
        }

        .terms-list li::before {
            content: "•";
            position: absolute;
            left: 0;
        }

        /* Bottom Declaration & Signatures */
        .declaration-table {
            width: 100%;
            border-collapse: collapse;
        }

        .declaration-table td {
            vertical-align: bottom;
            padding: 4px 6px;
        }

        .dec-text-cell {
            width: 40%;
            font-size: 6.5pt;
            line-height: 1.15;
            border-right: 1px solid #000;
            vertical-align: top !important;
        }

        .cust-sign-cell {
            width: 25%;
            text-align: center;
            border-right: 1px solid #000;
            font-size: 7.5pt;
            padding-bottom: 6px !important;
        }

        .auth-sign-cell {
            width: 35%;
            text-align: center;
            font-size: 7.5pt;
            padding-bottom: 6px !important;
        }

        .sign-space {
            height: 38px;
        }

        /* Floating action buttons for web preview */
        .print-actions-bar {
            position: fixed;
            top: 12px;
            right: 12px;
            background: #ffffff;
            border: 1px solid #cbd5e1;
            padding: 6px 12px;
            border-radius: 8px;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
            display: flex;
            gap: 8px;
            z-index: 9999;
        }

        .print-btn {
            background: #4f46e5;
            color: #ffffff;
            border: none;
            padding: 6px 14px;
            font-size: 12px;
            font-weight: bold;
            border-radius: 6px;
            cursor: pointer;
        }

        .print-btn:hover {
            background: #4338ca;
        }

        @media print {
            .print-actions-bar {
                display: none !important;
            }

            body {
                background: none;
            }

            .invoice-container {
                border: 1.5px solid #000 !important;
            }
        }
    </style>
</head>

<body>

    <!-- Web Print Actions Toolbar -->
    <div class="print-actions-bar">
        <button class="print-btn" onclick="window.print()">Print Invoice</button>
    </div>

    <div class="invoice-container">

        <!-- 1. Header Bar -->
        <table class="header-table">
            <tr>
                <td style="width: 20%;">
                    @if (!empty($plant?->logo_path))
                        @php
                            $cleanLogo = ltrim(
                                str_replace(['public/', 'storage/', '/storage/'], '', $plant->logo_path),
                                '/',
                            );
                        @endphp
                        <img src="{{ asset('storage/' . $cleanLogo) }}" class="company-logo" alt="Logo"
                            onerror="this.style.display='none'" />
                    @endif
                </td>
                <td style="width: 55%;">
                    <div class="company-title">{{ strtoupper($companyName) }}</div>
                </td>
                <td style="width: 25%;">
                    <div class="invoice-type-tag">
                        <div class="copy-title">{{ $copyType }}</div>
                        <div>TAX INVOICE</div>
                    </div>
                </td>
            </tr>
        </table>

        <!-- 2. IRN & E-Invoice Compliance Bar -->
        <table class="compliance-bar">
            <tr>
                <td style="width: 75%;">
                    @if (!empty($irn))
                        <div class="info-line"><strong>IRN :</strong> {{ $irn }}</div>
                    @endif
                    @if (!empty($ackNo))
                        <div class="info-line"><strong>Ack No. :</strong> {{ $ackNo }}</div>
                    @endif
                    @if (!empty($ackDate))
                        <div class="info-line"><strong>Ack Date :</strong> {{ $ackDate }}</div>
                    @endif
                </td>
                <td class="qr-cell">
                    @php
                        $qrSrc = '';
                        if (!empty($qrCode)) {
                            if (str_starts_with($qrCode, 'data:image') || str_starts_with($qrCode, 'http')) {
                                $qrSrc = $qrCode;
                            } else {
                                $qrSrc = 'https://api.qrserver.com/v1/create-qr-code/?size=150x150&data=' . urlencode($qrCode);
                            }
                        } elseif (!empty($irn)) {
                            $qrSrc = 'https://api.qrserver.com/v1/create-qr-code/?size=150x150&data=' . urlencode($irn);
                        }
                    @endphp
                    @if (!empty($qrSrc))
                        <img src="{{ $qrSrc }}" class="qr-img" alt="E-Invoice QR" />
                    @elseif(!empty($irn))
                        <div style="font-size: 6.5pt; text-align: center; border: 1px dashed #999; padding: 6px 2px;">
                            [IRN REGISTERED]</div>
                    @endif
                </td>
            </tr>
        </table>

        <!-- 3. Top 3-Column Info (Reg Address, Plant Address, Invoice Info) -->
        <table class="info-grid-table">
            <tr>
                <!-- Col 1: Reg. Address -->
                <td>
                    <div class="box-heading">Reg. Address</div>
                    <div class="info-line">
                        @if ($regAddress)
                            {{ $regAddress->line_1 }}@if ($regAddress->line_2)
                                , {{ $regAddress->line_2 }}
                            @endif,<br>
                            {{ $regAddress->city }} - {{ $regAddress->zipcode }}<br>
                            {{ strtoupper($stateName) }}
                        @endif
                    </div>
                    <div class="info-line"><strong>Mobile Number :</strong> {{ $mobile }}</div>
                    <div class="info-line"><strong>Telephone :</strong> {{ $telephone }}</div>
                    <div class="info-line"><strong>GSTIN :</strong> {{ $companyGstin }}</div>
                    <div class="info-line"><strong>STATE :</strong> {{ strtoupper($stateName) }}</div>
                    <div class="info-line"><strong>STATE CODE :</strong> {{ $stateCode }}</div>
                    <div class="info-line"><strong>PAN :</strong> {{ $pan }}</div>
                    <div class="info-line"><strong>MSME/Udyam No :</strong> {{ $msmeNo }}</div>
                </td>

                <!-- Col 2: Plant Address -->
                <td>
                    <div class="box-heading">Plant Address</div>
                    <div class="info-line">
                        @if ($plantAddress)
                            {{ $plantAddress->line_1 }}@if ($plantAddress->line_2)
                                , {{ $plantAddress->line_2 }}
                            @endif,<br>
                            {{ $plantAddress->city }} - {{ $plantAddress->zipcode }}<br>
                            {{ $plantAddress->state?->name ?? '' }}
                        @endif
                    </div>
                </td>

                <!-- Col 3: Invoice Information -->
                <td>
                    <div class="box-heading">Invoice Information</div>
                    <div class="info-line"><strong>Invoice No :</strong> {{ $invoiceNo }}</div>
                    <div class="info-line"><strong>Date :</strong> {{ $invoiceDate }}</div>
                    <div class="info-line"><strong>SO No :</strong> {{ $soNo }}</div>
                    <div class="info-line"><strong>EWayBillNo :</strong> {{ $ewayBillNo }}</div>
                </td>
            </tr>
        </table>

        <!-- 4. Middle 3-Column Info (Customer Billing, Customer Shipping, Customer Ref) -->
        <table class="info-grid-table">
            <tr>
                <!-- Col 1: Customer Billing Address -->
                <td>
                    <div class="box-heading">Customer Billing Address</div>
                    <div class="info-line font-bold">
                        {{ strtoupper($partner?->legal_name ?? '') }}</div>
                    <div class="info-line">
                        @if ($billingAddress)
                            {{ $billingAddress->line_1 }}@if ($billingAddress->line_2)
                                , {{ $billingAddress->line_2 }}
                            @endif,<br>
                            {{ $billingAddress->city }} - {{ $billingAddress->zipcode }}
                        @endif
                    </div>
                    <div class="info-line"><strong>GSTIN :</strong> {{ $partner?->gstin ?? '' }}</div>
                </td>

                <!-- Col 2: Customer Shipping Address -->
                <td>
                    <div class="box-heading">Customer Shipping Address</div>
                    <div class="info-line font-bold">
                        {{ strtoupper($shippingSite?->name ?? '') }}</div>
                    <div class="info-line">
                        @if ($shippingAddress)
                            {{ $shippingAddress->line_1 }}@if ($shippingAddress->line_2)
                                , {{ $shippingAddress->line_2 }}
                            @endif,<br>
                            {{ $shippingAddress->city }} - {{ $shippingAddress->zipcode }}
                        @endif
                    </div>
                    <div class="info-line"><strong>GSTIN :</strong>
                        {{ $shippingSite?->gstin ?? ($partner?->gstin ?? '') }}</div>
                </td>

                <!-- Col 3: Customer Ref -->
                <td>
                    <div class="box-heading">Customer Ref</div>
                    <div class="info-line"><strong>Acc No :</strong> {{ $accNo }}</div>
                    <div class="info-line"><strong>PO :</strong> {{ $poRef }}</div>
                    <div class="info-line"><strong>Sales Person :</strong> {{ $salesPersonName }}</div>
                    <div class="info-line"><strong>Pump :</strong> {{ $pumpName }}</div>
                    <div class="info-line"><strong>Quality InCharge :</strong> -</div>
                    <div class="info-line"><strong>Design Mix Ref :</strong> {{ $designMixRef }}</div>
                </td>
            </tr>
        </table>

        <!-- 5. Carrier - Driver Row -->
        <div class="carrier-row">
            <strong>Carrier - Driver :</strong> {{ $carrierDriver }}
        </div>

        <!-- 6. Line Items Table -->
        <table class="items-table">
            <thead>
                <tr>
                    <th style="width: 10%;">Code</th>
                    <th style="width: 14%;">Grade</th>
                    <th style="width: 12%;">Description</th>
                    <th style="width: 11%;">QTY.in CU.M</th>
                    <th style="width: 11%;">Unit Price</th>
                    <th style="width: 13%;">Taxable Amount</th>
                    <th style="width: 18%;">Tax</th>
                    <th style="width: 11%;">Total</th>
                </tr>
            </thead>
            <tbody>
                @forelse($items as $item)
                    @php
                        $qty = (float) ($item->quantity ?? 0);
                        $unitPrice = (float) ($item->price_unit ?? 0);
                        $taxable = (float) ($item->subtotal ?? $qty * $unitPrice);
                        $lineTax = (float) ($item->line_tax_amount ?? 0);
                        $lineTotal = (float) ($item->line_total ?? $taxable + $lineTax);
                        $hsn = $item->hsn_code ?? '38245010';
                        $gradeName = $item->item_name ?? 'M20 (Gst)';
                    @endphp
                    <tr>
                        <td class="text-center font-bold">
                            HSN :<br>{{ $hsn }}
                        </td>
                        <td class="text-center font-bold">
                            {{ $gradeName }}
                        </td>
                        <td class="text-center">
                            {{ $item->description ?? '' }}
                        </td>
                        <td class="text-center">
                            {{ number_format($qty, 2) }}
                        </td>
                        <td class="text-right">
                            {{ number_format($unitPrice, 2) }}
                        </td>
                        <td class="text-right">
                            {{ number_format($taxable, 2) }}
                        </td>
                        <td>
                            @if ($isIntra)
                                <table class="tax-subtable">
                                    <tr>
                                        <td class="font-bold">CGST@9%</td>
                                        <td class="text-center">9.00%</td>
                                        <td class="text-right">{{ number_format($lineTax / 2, 2) }}</td>
                                    </tr>
                                    <tr>
                                        <td class="font-bold">SGST@9%</td>
                                        <td class="text-center">9.00%</td>
                                        <td class="text-right">{{ number_format($lineTax / 2, 2) }}</td>
                                    </tr>
                                </table>
                            @else
                                <table class="tax-subtable">
                                    <tr>
                                        <td class="font-bold">IGST@18%</td>
                                        <td class="text-center">18.00%</td>
                                        <td class="text-right">{{ number_format($lineTax, 2) }}</td>
                                    </tr>
                                </table>
                            @endif
                        </td>
                        <td class="text-right font-bold">
                            {{ number_format($lineTotal, 2) }}
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="8" class="text-center" style="padding: 12px;">No items found.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>

        <!-- 7. Amount in Words & Totals -->
        <table class="summary-table">
            <tr>
                <!-- Left: Amount in Words -->
                <td class="words-col">
                    <div class="font-bold" style="margin-bottom: 2px;">Amount in Words :</div>
                    @if ($isIntra)
                        @if ($totalCgst > 0)
                            <div class="info-line"><strong>CGST</strong>
                                {{ taxInvoiceNumberToWords($totalCgst) }}</div>
                        @endif
                        @if ($totalSgst > 0)
                            <div class="info-line"><strong>SGST</strong>
                                {{ taxInvoiceNumberToWords($totalSgst) }}</div>
                        @endif
                    @else
                        @if ($totalIgst > 0)
                            <div class="info-line"><strong>IGST</strong> {{ taxInvoiceNumberToWords($totalIgst) }}
                            </div>
                        @endif
                    @endif
                    @if ($grandTotal > 0)
                        <div class="info-line font-bold" style="margin-top: 2px;">Grand Total
                            {{ taxInvoiceNumberToWords($grandTotal) }}</div>
                    @endif
                </td>

                <!-- Right: Rounding & Grand Total -->
                <td class="calc-col">
                    <table class="calc-subtable">
                        <tr>
                            <td class="text-right" style="width: 60%;">Rounding off</td>
                            <td class="text-right font-bold" style="width: 40%;">
                                {{ number_format($roundOff, 2) }}</td>
                        </tr>
                        <tr>
                            <td class="text-right font-bold">Grand Total</td>
                            <td class="text-right font-bold">
                                {{ number_format($grandTotal, 2) }}</td>
                        </tr>
                    </table>
                </td>
            </tr>
        </table>

        <!-- 8. Terms of Sales & Bank Information -->
        <table class="terms-bank-table">
            <tr>
                <!-- Left: Terms of Sales -->
                <td>
                    <div class="box-heading">TERMS OF SALES :</div>
                    <ul class="terms-list">
                        <li>Goods once sold will not be taken or exchanged</li>
                        <li>Seller is not responsible for any loss or damaged of goods in transit</li>
                        <li>Buyer undertakes to submit prescribed s.t.decln. to the seller on demand or wholly unpaid
                            after due date</li>
                        <li>Dispute, if any subject to coimbatore jurisdication</li>
                        <li>Pay us within 45 days from the date of invoice to avoid disallowance u/s.43B(h) of Income
                            Tax Act, 1961.</li>
                        <li>As per MSME Act 2006, any delayed payments to MSMEs will attract interest at 3 times the
                            bank rate notified by RBI.</li>
                    </ul>
                </td>

                <!-- Right: Bank Information -->
                <td>
                    <div class="box-heading">BANK INFORMATION:</div>
                    <div class="info-line"><strong>ACCOUNT NAME:</strong> {{ strtoupper($bankAccountName)?? '' }}</div>
                    <div class="info-line"><strong>ACCOUNT NUMBER:</strong> {{ $bankAccountNo?? '' }}</div>
                    <div class="info-line"><strong>BANK:</strong> {{ strtoupper($bankName)?? '' }}</div>
                    <div class="info-line"><strong>BRANCH:</strong> {{ strtoupper($bankBranch)?? '' }}</div>
                    <div class="info-line"><strong>IFSC CODE:</strong> {{ strtoupper($bankIfsc)?? '' }}</div>
                </td>
            </tr>
        </table>

        <!-- 9. Bottom Declaration, Customer Sign & Authorized Signatory -->
        <table class="declaration-table">
            <tr>
                <!-- Declaration Text -->
                <td class="dec-text-cell">
                    Certificate that the goods on which the GST tax has been charged have not been exempted under the
                    GST Tax Act or the rules made thereunder and the amount charged on Account of GST Tax on these goods
                    are not more than that what is payable under the provisions of the relevant Act or the Rules made
                    thereunder.
                    <div style="font-weight: bold; margin-top: 4px;">E. & O. E.</div>
                </td>

                <!-- Customer Signature -->
                <td class="cust-sign-cell">
                    <div class="sign-space"></div>
                    <strong>Customer Signature</strong>
                </td>

                <!-- Authorised Signatory -->
                <td class="auth-sign-cell">
                    <div style="font-weight: bold; margin-bottom: 2px;">For {{ strtoupper($companyName) }}</div>
                    <div class="sign-space"></div>
                    <strong>Authorised Signatory</strong>
                </td>
            </tr>
        </table>

    </div>

</body>

</html>
