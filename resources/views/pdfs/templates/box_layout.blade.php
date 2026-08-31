@php
    $pdfSettings = [];
    foreach ($data['settings']['pdf'] ?? [] as $k => $v) {
        if ($v === '0' || $v === 0 || $v === false || $v === 'false') {
            $pdfSettings[$k] = false;
        } else {
            $pdfSettings[$k] = $v;
        }
    }
    $labels = $pdfSettings['labels'] ?? [];
    $copyType = $copy_type ?? 'ORIGINAL';
@endphp
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>{{ $data['doc_title'] }} - {{ $data['doc_no'] }}</title>
    @include('pdfs.partials._common_styles')
    <style>
        @page {
            size: A4 portrait;
            margin: 6mm 6mm 6mm 6mm;
        }
        @media print {
            body { -webkit-print-color-adjust: exact; print-color-adjust: exact; }
            .no-print { display: none !important; }
            .inv-root { border: 2px solid #000 !important; }
        }
        body { 
            font-family: 'Helvetica Neue', Helvetica, Arial, sans-serif; 
            color: #000; 
            font-size: 8.5pt; 
            margin: 0; 
            padding: 0; 
            line-height: 1.2; 
            background: #fff;
        }
        .inv-root { 
            width: 100%; 
            border: 2px solid #000; 
            box-sizing: border-box; 
            background: #fff;
            page-break-inside: avoid;
        }
        
        .header-table { width: 100%; border-collapse: collapse; border-bottom: 2px solid #000; }
        .header-table td { vertical-align: top; padding: 6px 8px; }
        .header-title { text-align: right; text-transform: uppercase; font-size: 12pt; font-weight: bold; }
        .header-subtitle { text-align: right; text-transform: uppercase; font-size: 8.5pt; color: #333; margin-top: 1px; }

        .irn-bar { border-bottom: 2px solid #000; padding: 4px 8px; font-size: 8pt; width: 100%; box-sizing: border-box; }
        .irn-bar table { width: 100%; border-collapse: collapse; }
        .irn-bar td { vertical-align: middle; padding: 0; }

        .block-table { width: 100%; border-collapse: collapse; border-bottom: 2px solid #000; }
        .block-th { background: #fff; border-bottom: 1.5px solid #000; border-right: 1.5px solid #000; font-size: 8.5pt; font-weight: bold; padding: 4px 6px; text-align: left; }
        .block-th:last-child { border-right: none; }
        .block-td { border-right: 1.5px solid #000; padding: 4px 6px; vertical-align: top; font-size: 8pt; line-height: 1.25; }
        .block-td:last-child { border-right: none; }

        .kv-line { margin-bottom: 2px; }
        .kv-label { font-weight: normal; color: #111; }
        .kv-val { font-weight: bold; color: #000; }

        .carrier-table { width: 100%; border-collapse: collapse; border-bottom: 2px solid #000; }
        .carrier-th { width: 38%; border-right: 1.5px solid #000; padding: 4px 6px; font-size: 8.5pt; font-weight: bold; }
        .carrier-td { width: 62%; padding: 4px 6px; font-size: 8pt; vertical-align: middle; }

        .items-table { width: 100%; border-collapse: collapse; border-bottom: 2px solid #000; }
        .items-table th { border-bottom: 1.5px solid #000; border-right: 1.5px solid #000; padding: 4px 3px; font-size: 8pt; font-weight: bold; text-align: center; background: #fff; }
        .items-table th:last-child { border-right: none; }
        .items-table td { border-right: 1.5px solid #000; border-bottom: 1.5px solid #000; padding: 4px 4px; font-size: 8pt; vertical-align: middle; }
        .items-table td:last-child { border-right: none; }
        .items-table tr:last-child td { border-bottom: none; }

        .tax-subtable { width: 100%; border-collapse: collapse; margin: 0; }
        .tax-subtable td { border: 1px solid #000; padding: 2px 3px; font-size: 7.5pt; text-align: center; }
        .tax-subtable tr:first-child td { border-top: none; }
        .tax-subtable tr:last-child td { border-bottom: none; }
        .tax-subtable td:first-child { border-left: none; text-align: left; font-weight: bold; }
        .tax-subtable td:last-child { border-right: none; text-align: right; }

        .totals-table { width: 100%; border-collapse: collapse; border-bottom: 2px solid #000; }
        .totals-table td { vertical-align: top; padding: 0; }
        .totals-words { width: 60%; border-right: 1.5px solid #000; padding: 6px 8px; font-size: 8pt; line-height: 1.3; }

        .summary-subtable { width: 100%; border-collapse: collapse; }
        .summary-subtable td { border-bottom: 1.5px solid #000; padding: 3px 6px; font-size: 8pt; }
        .summary-subtable td.label-cell { text-align: right; border-right: 1.5px solid #000; }
        .summary-subtable td.val-cell { text-align: right; width: 85px; }
        .summary-subtable tr:last-child td { border-bottom: none; }

        .terms-block { border-right: 1.5px solid #000; width: 60%; padding: 5px 6px; vertical-align: top; font-size: 7pt; line-height: 1.25; }
        .bank-block { width: 40%; padding: 5px 6px; vertical-align: top; font-size: 7.5pt; line-height: 1.3; }

        .footer-table { width: 100%; border-collapse: collapse; border-top: 2px solid #000; }
        .footer-cert { border-right: 1.5px solid #000; width: 40%; padding: 4px 6px; font-size: 7pt; color: #000; vertical-align: top; line-height: 1.2; }
        .footer-sig-cust { border-right: 1.5px solid #000; width: 25%; padding: 4px 6px; vertical-align: bottom; text-align: center; font-size: 8pt; font-weight: bold; }
        .footer-sig-auth { width: 35%; padding: 4px 6px; vertical-align: bottom; text-align: center; font-size: 8pt; }
    </style>
</head>
<body>
@include('pdfs.partials._print_actions')
<div class="inv-root">
    {{-- TOP LOGO & TITLE HEADER --}}
    <table class="header-table">
        <tr>
            <td style="width: 65%;">
                @if (($pdfSettings['logo'] ?? true) && !empty($data['company']['logo_path']))
                    @php
                        $cleanLogoPath = ltrim(str_replace(['public/', 'storage/', '/storage/'], '', $data['company']['logo_path']), '/');
                        $logoUrl = (request()->route('action') !== 'download' && !($is_pdf ?? false)) ? asset('storage/' . $cleanLogoPath) : public_path('storage/' . $cleanLogoPath);
                    @endphp
                    <img src="{{ $logoUrl }}" style="max-height: 40px; max-width: 160px; object-fit: contain; vertical-align: middle; margin-right: 8px;" />
                @endif
                @if($pdfSettings['company_name'] ?? true)
                    <span style="font-size: 12pt; font-weight: bold; text-transform: uppercase; vertical-align: middle;">{{ $data['company']['name'] }}</span>
                @endif
            </td>
            <td style="width: 35%; text-align: right;">
                <div class="header-subtitle">{{ $copyType }}</div>
                <div class="header-title">{{ !empty($pdfSettings['labels']['invoice_title']) ? $pdfSettings['labels']['invoice_title'] : $data['doc_title'] }}</div>
            </td>
        </tr>
    </table>

    {{-- E-INVOICE / IRN BAR --}}
    @php
        $irnVal = $data['meta']['irn'] ?? '';
        $ackNoVal = $data['meta']['ack_no'] ?? '';
        $ackDateVal = $data['meta']['ack_date'] ?? '';
        $rawQr = $data['meta']['qr_code'] ?? '';
        $hasValidQr = !empty($rawQr) && $rawQr !== '{qr_code}' && (str_starts_with($rawQr, 'data:image') || str_starts_with($rawQr, 'http://') || str_starts_with($rawQr, 'https://') || file_exists(public_path('storage/' . ltrim(str_replace(['public/', 'storage/', '/storage/'], '', $rawQr), '/'))));
    @endphp
    @if (($pdfSettings['show_einvoice_details'] ?? true) && (!empty($irnVal) || $hasValidQr))
        <div class="irn-bar">
            <table>
                <tr>
                    <td style="vertical-align: top; line-height: 1.35;">
                        @if(!empty($irnVal)) <div>IRN : <strong>{{ $irnVal }}</strong></div> @endif
                        @if(!empty($ackNoVal)) <div>Ack No. : <strong>{{ $ackNoVal }}</strong></div> @endif
                        @if(!empty($ackDateVal)) <div>Ack Date : <strong>{{ $ackDateVal }}</strong></div> @endif
                    </td>
                    @if($hasValidQr)
                        <td style="width: 70px; text-align: right; vertical-align: top;">
                            <img src="{{ $rawQr }}" style="max-height: 65px; max-width: 65px; object-fit: contain;" />
                        </td>
                    @endif
                </tr>
            </table>
        </div>
    @endif

    {{-- ADDRESS & INVOICE INFO BLOCK (3-COL) --}}
    <table class="block-table">
        <tr>
            <td class="block-th" style="width: 38%;">Reg. Address</td>
            <td class="block-th" style="width: 30%;">Plant Address</td>
            <td class="block-th" style="width: 32%;">Invoice Information</td>
        </tr>
        <tr>
            <td class="block-td">
                @if($pdfSettings['address'] ?? true)
                    <div>{{ $data['company']['address'] }}</div>
                    <div>{{ $data['company']['city'] }} - {{ $data['company']['pin'] }}</div>
                    <div>{{ strtoupper($data['company']['state']) }}</div>
                    @if(!empty($data['company']['phone'])) <div>Mobile Number : {{ $data['company']['phone'] }}</div> @endif
                @endif
                @if(($pdfSettings['gstin'] ?? true) && !empty($data['company']['gstin']))
                    <div>GSTIN : <strong>{{ $data['company']['gstin'] }}</strong></div>
                @endif
                @if($pdfSettings['address'] ?? true)
                    <div>STATE : {{ strtoupper($data['company']['state']) }}</div>
                    @if(!empty($data['company']['state_code'])) <div>STATE CODE : {{ $data['company']['state_code'] }}</div> @endif
                    @if(!empty($data['company']['pan'])) <div>PAN : {{ $data['company']['pan'] }}</div> @endif
                    @if(!empty($data['company']['msme_no'])) <div>MSME/Udyam No : {{ $data['company']['msme_no'] }}</div> @endif
                @endif
            </td>
            <td class="block-td">
                @if($pdfSettings['address'] ?? true)
                    <div>{{ $data['company']['address'] }}</div>
                    <div>{{ $data['company']['city'] }} - {{ $data['company']['pin'] }}</div>
                    <div>{{ $data['company']['state'] }}</div>
                @endif
            </td>
            <td class="block-td">
                @if($pdfSettings['invoice_number'] ?? true)
                    <div class="kv-line">Invoice No : <strong>{{ $data['doc_no'] }}</strong></div>
                @endif
                @if($pdfSettings['date'] ?? true)
                    <div class="kv-line">Date : <strong>{{ $data['doc_date'] }}</strong></div>
                @endif
                @if(!empty($data['meta']['so_no'])) <div class="kv-line">SO No : <strong>{{ $data['meta']['so_no'] }}</strong></div> @endif
                @if(($pdfSettings['show_einvoice_details'] ?? true) && !empty($data['meta']['eway_bill_no']))
                    <div class="kv-line">EWayBillNo : <strong>{{ $data['meta']['eway_bill_no'] }}</strong></div>
                @endif
            </td>
        </tr>
    </table>

    {{-- CUSTOMER BILLING, SHIPPING & REF BLOCK (3-COL) --}}
    <table class="block-table">
        <tr>
            <td class="block-th" style="width: 38%;">Customer Billing Address</td>
            <td class="block-th" style="width: 30%;">Customer Shipping Address</td>
            <td class="block-th" style="width: 32%;">Customer Ref</td>
        </tr>
        <tr>
            <td class="block-td">
                @if($pdfSettings['bill_to'] ?? true)
                    <div style="font-weight: bold; text-transform: uppercase;">{{ $data['bill_to']['name'] }}</div>
                    <div>{{ $data['bill_to']['address'] }}</div>
                    <div>{{ $data['bill_to']['city'] }} - {{ $data['bill_to']['pin'] }}</div>
                    @if(($pdfSettings['gstin'] ?? true) && !empty($data['bill_to']['gstin']))
                        <div style="margin-top: 2px;">GSTIN : <strong>{{ $data['bill_to']['gstin'] }}</strong></div>
                    @endif
                @endif
            </td>
            <td class="block-td">
                @if($pdfSettings['ship_to'] ?? true)
                    <div style="font-weight: bold; text-transform: uppercase;">{{ $data['ship_to']['name'] }}</div>
                    <div>{{ $data['ship_to']['address'] }}</div>
                    <div>{{ $data['ship_to']['city'] }} - {{ $data['ship_to']['pin'] }}</div>
                    @if(($pdfSettings['gstin'] ?? true) && !empty($data['ship_to']['gstin']))
                        <div style="margin-top: 2px;">GSTIN : <strong>{{ $data['ship_to']['gstin'] }}</strong></div>
                    @endif
                @endif
            </td>
            <td class="block-td">
                @if($pdfSettings['show_customer_ref'] ?? true)
                    <div class="kv-line">Acc No : <strong>{{ $data['meta']['acc_no'] ?? '-' }}</strong></div>
                    <div class="kv-line">PO : <strong>{{ $data['meta']['po_number'] ?? '-' }}</strong></div>
                    <div class="kv-line">Sales Person : <strong>{{ $data['meta']['sales_person'] ?? '-' }}</strong></div>
                    <div class="kv-line">Pump : <strong>{{ $data['meta']['pump'] ?? '-' }}</strong></div>
                    <div class="kv-line">Quality InCharge :-</div>
                    <div class="kv-line">Design Mix Ref : <strong>{{ $data['meta']['design_mix_ref'] ?? '-' }}</strong></div>
                @endif
            </td>
        </tr>
    </table>

    {{-- CARRIER - DRIVER BAR --}}
    @if(($pdfSettings['show_carrier_driver'] ?? true) && !empty($data['meta']['carrier_driver']))
        <table class="carrier-table">
            <tr>
                <td class="carrier-th">Carrier - Driver</td>
                <td class="carrier-td">{{ $data['meta']['carrier_driver'] }}</td>
            </tr>
        </table>
    @endif

    {{-- ITEMS TABLE --}}
    <table class="items-table">
        <thead>
            <tr>
                @if($pdfSettings['hsn_code'] ?? true) <th style="width: 8%;">Code</th> @endif
                <th style="width: 12%;">Grade</th>
                @if($pdfSettings['description'] ?? true) <th style="width: 16%;">Description</th> @endif
                @if($pdfSettings['show_pump_charges'] ?? false) <th style="width: 8%;">Op. Type</th> @endif
                @if($pdfSettings['qty'] ?? true) <th style="width: 9%;">QTY.in CU.M</th> @endif
                @if($pdfSettings['unit'] ?? true) <th style="width: 9%;">Unit Price</th> @endif
                @if($pdfSettings['discount'] ?? false) <th style="width: 8%;">Discount</th> @endif
                @if($pdfSettings['show_pump_charges'] ?? false) <th style="width: 8%;">Pump Chg</th> @endif
                <th style="width: 11%;">Taxable Amount</th>
                @if(($pdfSettings['tax_rate'] ?? true) || ($pdfSettings['tax_amount'] ?? true)) <th style="width: 20%;">Tax</th> @endif
                @if($pdfSettings['amount'] ?? true) <th style="width: 10%;">Total</th> @endif
            </tr>
        </thead>
        <tbody>
            @php
                $boxTotalCols = 2; // Grade + Taxable Amount
                if ($pdfSettings['hsn_code'] ?? true) $boxTotalCols++;
                if ($pdfSettings['description'] ?? true) $boxTotalCols++;
                if ($pdfSettings['show_pump_charges'] ?? false) $boxTotalCols += 2;
                if ($pdfSettings['qty'] ?? true) $boxTotalCols++;
                if ($pdfSettings['unit'] ?? true) $boxTotalCols++;
                if ($pdfSettings['discount'] ?? false) $boxTotalCols++;
                if (($pdfSettings['tax_rate'] ?? true) || ($pdfSettings['tax_amount'] ?? true)) $boxTotalCols++;
                if ($pdfSettings['amount'] ?? true) $boxTotalCols++;

                $boxRecipeColspan = min(7, $boxTotalCols - 1);
                $boxRemainingCols = max(0, $boxTotalCols - 1 - $boxRecipeColspan);
            @endphp
            @foreach($data['items'] as $item)
                @php
                    $hasRecipe = !empty($item['recipe_materials']) && count($item['recipe_materials']) > 0;
                    $itemTaxRate = (float)($item['tax_rate'] ?? 18);
                    $itemTaxAmt = (float)($item['tax_amount'] ?? 0);
                    $taxGroup = strtoupper($item['tax_group'] ?? '');
                    $taxName = strtoupper($item['tax_name'] ?? '');
                    $isIgst = !empty($item['is_igst']) || $taxGroup === 'IGST' || str_contains($taxName, 'IGST');
                    $formattedTaxRate = $itemTaxRate == floor($itemTaxRate) ? (int)$itemTaxRate : number_format($itemTaxRate, 2);
                    $halfRate = $itemTaxRate / 2;
                    $halfAmt = $itemTaxAmt / 2;
                    $formattedHalfRate = $halfRate == floor($halfRate) ? (int)$halfRate : number_format($halfRate, 2);
                @endphp
                <tr>
                    @if($pdfSettings['hsn_code'] ?? true) <td style="text-align: center; {{ $hasRecipe ? 'border-bottom: none;' : '' }}">HSN :<br><strong>{{ $item['hsn'] ?? '38245010' }}</strong></td> @endif
                    <td style="text-align: center; font-weight: bold; {{ $hasRecipe ? 'border-bottom: none;' : '' }}">{{ $item['name'] }}</td>
                    @if($pdfSettings['description'] ?? true) <td style="{{ $hasRecipe ? 'border-bottom: none;' : '' }}">{{ $item['description'] ?? '-' }}</td> @endif
                    @if($pdfSettings['show_pump_charges'] ?? false) <td style="text-align: center; {{ $hasRecipe ? 'border-bottom: none;' : '' }}">{{ $item['operation_type'] ?? 'TM' }}</td> @endif
                    @if($pdfSettings['qty'] ?? true) <td style="text-align: right; font-weight: bold; {{ $hasRecipe ? 'border-bottom: none;' : '' }}">{{ (float)$item['qty'] == floor((float)$item['qty']) ? number_format($item['qty'], 2) : rtrim(rtrim(number_format($item['qty'], 3), '0'), '.') }}</td> @endif
                    @if($pdfSettings['unit'] ?? true) <td style="text-align: right; {{ $hasRecipe ? 'border-bottom: none;' : '' }}">{{ number_format($item['unit_price'], 2) }}</td> @endif
                    @if($pdfSettings['discount'] ?? false) <td style="text-align: right; {{ $hasRecipe ? 'border-bottom: none;' : '' }}">{{ !empty($item['discount']) && $item['discount'] > 0 ? number_format($item['discount'], 2) : '-' }}</td> @endif
                    @if($pdfSettings['show_pump_charges'] ?? false) <td style="text-align: right; {{ $hasRecipe ? 'border-bottom: none;' : '' }}">{{ number_format($item['pump_charge'] ?? 0, 2) }}</td> @endif
                    <td style="text-align: right; font-weight: bold; {{ $hasRecipe ? 'border-bottom: none;' : '' }}">{{ number_format($item['taxable_amount'] ?? ($item['qty'] * $item['unit_price']), 2) }}</td>
                    @if(($pdfSettings['tax_rate'] ?? true) || ($pdfSettings['tax_amount'] ?? true))
                        <td style="padding: 1px; {{ $hasRecipe ? 'border-bottom: none;' : '' }}">
                            <table class="tax-subtable">
                                @if($isIgst)
                                    @if(($pdfSettings['igst'] ?? true) !== false)
                                        <tr>
                                            <td style="width: 38%;">IGST@ {{ $formattedTaxRate }}%</td>
                                            <td style="width: 30%;">{{ $formattedTaxRate }}%</td>
                                            <td style="width: 32%;">{{ number_format($itemTaxAmt, 2) }}</td>
                                        </tr>
                                    @endif
                                @else
                                    @if(($pdfSettings['cgst'] ?? true) !== false)
                                        <tr>
                                            <td style="width: 38%;">CGST@ {{ $formattedHalfRate }}%</td>
                                            <td style="width: 30%;">{{ $formattedHalfRate }}%</td>
                                            <td style="width: 32%;">{{ number_format($halfAmt, 2) }}</td>
                                        </tr>
                                    @endif
                                    @if(($pdfSettings['sgst'] ?? true) !== false)
                                        <tr>
                                            <td style="width: 38%;">SGST@ {{ $formattedHalfRate }}%</td>
                                            <td style="width: 30%;">{{ $formattedHalfRate }}%</td>
                                            <td style="width: 32%;">{{ number_format($halfAmt, 2) }}</td>
                                        </tr>
                                    @endif
                                @endif
                            </table>
                        </td>
                    @endif
                    @if($pdfSettings['amount'] ?? true) <td style="text-align: right; font-weight: bold; {{ $hasRecipe ? 'border-bottom: none;' : '' }}">{{ number_format($item['total'], 2) }}</td> @endif
                </tr>
                @if ($hasRecipe)
                    <tr>
                        <td style="border-top: none; padding-top: 0;"></td>
                        <td colspan="{{ $boxRecipeColspan }}" style="border-top: none; padding-top: 0; padding-bottom: 6px;">
                            <div style="font-size: 8.5px; font-weight: 700; color: #2563eb; margin-top: 1px; margin-bottom: 2px;">Recipe Details:</div>
                            <div style="display: inline-block; background-color: #f8faff; border: 1px solid #dbeafe; border-radius: 4px; padding: 2px 6px;">
                                <table style="border-collapse: collapse; border: none; margin: 0; padding: 0; font-size: 8.5px; color: #334155;">
                                    @php
                                        $allSegments = [];
                                        foreach ($item['recipe_materials'] as $rm) {
                                            $allSegments[] = ['is_hsn' => false, 'name' => $rm['name'], 'qty' => $rm['qty'], 'uom' => $rm['uom']];
                                        }
                                        $chunkSize = count($allSegments) > 3 ? (int)ceil(count($allSegments) / 2) : count($allSegments);
                                        $chunks = array_chunk($allSegments, max(1, $chunkSize));
                                    @endphp
                                    @foreach ($chunks as $cIdx => $chunk)
                                        <tr style="{{ $cIdx > 0 ? 'border-top: 1px solid #e2e8f0;' : '' }}">
                                            @foreach ($chunk as $sIdx => $seg)
                                                @php $isLast = ($sIdx === count($chunk) - 1); @endphp
                                                <td style="padding: 1px 6px 1px 3px; {{ !$isLast ? 'border-right: 1px solid #e2e8f0;' : '' }} white-space: nowrap; vertical-align: middle;">
                                                    <span style="color: #64748b; margin-right: 2px;">&bull;</span> {{ $seg['name'] }} ({{ $seg['qty'] }} {{ $seg['uom'] }})
                                                </td>
                                            @endforeach
                                        </tr>
                                    @endforeach
                                </table>
                            </div>
                        </td>
                        @if ($boxRemainingCols > 0)
                            <td colspan="{{ $boxRemainingCols }}" style="border-top: none;"></td>
                        @endif
                    </tr>
                @endif
            @endforeach
        </tbody>
    </table>

    {{-- AMOUNT IN WORDS & GRAND TOTAL SUMMARY --}}
    @php
        $totTaxAmt = (float)($data['totals']['tax_amount'] ?? array_sum(array_column($data['totals']['tax_lines'] ?? [], 'amount')));
        $firstItem = $data['items'][0] ?? [];
        $totTaxRate = (float)($firstItem['tax_rate'] ?? 18);
        $firstTaxGroup = strtoupper($firstItem['tax_group'] ?? '');
        $firstTaxName = strtoupper($firstItem['tax_name'] ?? '');
        $isGlobalIgst = !empty($firstItem['is_igst']) || $firstTaxGroup === 'IGST' || str_contains($firstTaxName, 'IGST');
        $formattedTaxRate = $totTaxRate == floor($totTaxRate) ? (int)$totTaxRate : number_format($totTaxRate, 2);
        $halfTotRate = $totTaxRate / 2;
        $halfTotAmt = $totTaxAmt / 2;
        $formattedHalfRate = $halfTotRate == floor($halfTotRate) ? (int)$halfTotRate : number_format($halfTotRate, 2);
        $rawGrandWords = !empty($data['meta']['total_words']) ? $data['meta']['total_words'] : \App\Services\PrintDataFormatter::numberToWords($data['totals']['grand_total'], 'INR');
        $cleanGrandWords = preg_replace('/^(Rupees|Rs\.?)\s*/i', '', $rawGrandWords);
    @endphp
    <table class="totals-table">
        <tr>
            <td class="totals-words">
                @if(($pdfSettings['total_words'] ?? true) !== false)
                    <div style="font-weight: bold; margin-bottom: 3px;">Amount in Words :</div>
                    @if($isGlobalIgst)
                        @if(($pdfSettings['igst'] ?? true) !== false && $totTaxAmt > 0)
                            <div>IGST@ {{ $formattedTaxRate }}% {{ str_replace('Rupees ', 'Rs. ', \App\Services\PrintDataFormatter::numberToWords($totTaxAmt, 'INR')) }}</div>
                        @endif
                    @else
                        @if(($pdfSettings['cgst'] ?? true) !== false && $halfTotAmt > 0)
                            <div>CGST@ {{ $formattedHalfRate }}% {{ str_replace('Rupees ', 'Rs. ', \App\Services\PrintDataFormatter::numberToWords($halfTotAmt, 'INR')) }}</div>
                        @endif
                        @if(($pdfSettings['sgst'] ?? true) !== false && $halfTotAmt > 0)
                            <div>SGST@ {{ $formattedHalfRate }}% {{ str_replace('Rupees ', 'Rs. ', \App\Services\PrintDataFormatter::numberToWords($halfTotAmt, 'INR')) }}</div>
                        @endif
                    @endif
                    <div style="margin-top: 3px;">Grand Total <strong>Rs. {{ $cleanGrandWords }}</strong></div>
                @endif
            </td>
            <td style="width: 40%;">
                <table class="summary-subtable">
                    @if (!empty($data['totals']['sub_total']) && $data['totals']['sub_total'] > 0)
                        <tr>
                            <td class="label-cell">Sub Total / Gross Amount</td>
                            <td class="val-cell">{{ number_format($data['totals']['sub_total'], 2) }}</td>
                        </tr>
                    @endif
                    @php $pumpChg = $data['totals']['pump_charge'] ?? $data['totals']['pump_charges'] ?? $data['totals']['pump_rate'] ?? 0; @endphp
                    @if ((($pdfSettings['show_pump_charges'] ?? true) || ($pdfSettings['pump_rates'] ?? true)) && $pumpChg > 0)
                        <tr>
                            <td class="label-cell">Pump Charge</td>
                            <td class="val-cell">{{ number_format($pumpChg, 2) }}</td>
                        </tr>
                    @endif
                    @if (($pdfSettings['discount'] ?? true) && !empty($data['totals']['discount']) && $data['totals']['discount'] > 0)
                        <tr>
                            <td class="label-cell" style="color: #dc2626;">Discount (-)</td>
                            <td class="val-cell" style="color: #dc2626;">-{{ number_format($data['totals']['discount'], 2) }}</td>
                        </tr>
                    @endif
                    @php $hireChg = $data['totals']['hire_charge'] ?? $data['totals']['transport_expenses'] ?? 0; @endphp
                    @if (($pdfSettings['hire_charge'] ?? true) && $hireChg > 0)
                        <tr>
                            <td class="label-cell">Hire Charge</td>
                            <td class="val-cell">{{ number_format($hireChg, 2) }}</td>
                        </tr>
                    @endif
                    @if (($pdfSettings['pass_amount'] ?? true) && !empty($data['totals']['pass_amount']) && $data['totals']['pass_amount'] > 0)
                        <tr>
                            <td class="label-cell">Pass Amount</td>
                            <td class="val-cell">{{ number_format($data['totals']['pass_amount'], 2) }}</td>
                        </tr>
                    @endif
                    @if (!empty($data['totals']['tax_lines']))
                        @foreach ($data['totals']['tax_lines'] as $tl)
                            @php
                                $showTax = true;
                                if (str_contains($tl['label'], 'CGST') && !($pdfSettings['cgst'] ?? true)) $showTax = false;
                                if (str_contains($tl['label'], 'SGST') && !($pdfSettings['sgst'] ?? true)) $showTax = false;
                                if (str_contains($tl['label'], 'IGST') && !($pdfSettings['igst'] ?? true)) $showTax = false;
                            @endphp
                            @if ($showTax)
                                <tr>
                                    <td class="label-cell">{{ $tl['label'] }}</td>
                                    <td class="val-cell">{{ number_format($tl['amount'], 2) }}</td>
                                </tr>
                            @endif
                        @endforeach
                    @endif
                    @if (($pdfSettings['shipping'] ?? true) && !empty($data['totals']['shipping']) && $data['totals']['shipping'] > 0)
                        <tr>
                            <td class="label-cell">Shipping</td>
                            <td class="val-cell">{{ number_format($data['totals']['shipping'], 2) }}</td>
                        </tr>
                    @endif
                    @if (($pdfSettings['adjustment'] ?? true) && ($data['totals']['adjustment'] ?? 0) != 0)
                        <tr>
                            <td class="label-cell">Adjustment</td>
                            <td class="val-cell">{{ $data['totals']['adjustment'] > 0 ? '+' : '' }}{{ number_format($data['totals']['adjustment'], 2) }}</td>
                        </tr>
                    @endif
                    @if (($pdfSettings['round_off'] ?? true) && ($data['totals']['round_off'] ?? 0) != 0)
                        <tr>
                            <td class="label-cell">Rounding off</td>
                            <td class="val-cell">{{ $data['totals']['round_off'] > 0 ? '+' : '' }}{{ number_format($data['totals']['round_off'], 2) }}</td>
                        </tr>
                    @endif
                    <tr>
                        <td class="label-cell" style="font-weight: bold; font-size: 9pt;">Grand Total</td>
                        <td class="val-cell" style="font-weight: bold; font-size: 9pt;">{{ number_format($data['totals']['grand_total'], 2) }}</td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>

    {{-- TERMS & CONDITIONS + BANK INFORMATION --}}
    <table style="width: 100%; border-collapse: collapse;">
        <tr>
            <td class="terms-block">
                @if(($pdfSettings['terms'] ?? true) !== false)
                    @php
                        $termsText = trim(!empty($pdfSettings['terms_text']) ? $pdfSettings['terms_text'] : ($data['meta']['terms_text'] ?? ''));
                        $termsHtml = (!empty($termsText) && $termsText === strip_tags($termsText)) ? nl2br(e($termsText)) : $termsText;
                    @endphp
                    <div style="font-weight: bold; margin-bottom: 2px; text-transform: uppercase;">TERMS &amp; CONDITIONS :</div>
                    <div class="terms-text-content" style="font-size: 6.8pt; line-height: 1.25; white-space: normal !important; word-break: break-word;">
                        @if(!empty($termsHtml))
                            {!! $termsHtml !!}
                        @else
                            • Goods once sold will not be taken or exchanged<br>
                            • Seller is not responsible for any loss or damaged of goods in transit<br>
                            • Buyer undertakes to submit prescribed s.t.decln. to the seller on demand or wholly unpaid after due date<br>
                            • Dispute, if any subject to coimbatore jurisdication<br>
                            • Pay us within 45 days from the date of invoice to avoid disallowance u/s.43B(h) of Income Tax Act, 1961.<br>
                            • As per MSME Act 2006, any delayed payments to MSMEs will attract interest at 3 times the bank rate notified by RBI.
                        @endif
                    </div>
                @endif
            </td>
            <td class="bank-block">
                @if (($pdfSettings['show_bank_details'] ?? true) && !empty($data['company']['bank']['bank_name']))
                    <div style="font-weight: bold; margin-bottom: 2px; text-transform: uppercase;">BANK INFORMATION:</div>
                    <div style="font-size: 7.5pt; line-height: 1.25;">
                        ACCOUNT NAME: <strong>{{ strtoupper($data['company']['bank']['account_name']) }}</strong><br>
                        ACCOUNT NUMBER: <strong style="letter-spacing: 0.5px;">{{ $data['company']['bank']['account_number'] }}</strong><br>
                        BANK: <strong>{{ strtoupper($data['company']['bank']['bank_name']) }}</strong><br>
                        BRANCH: <strong>{{ strtoupper($data['company']['bank']['branch']) }}</strong><br>
                        IFSC CODE: <strong>{{ strtoupper($data['company']['bank']['ifsc_code']) }}</strong>
                    </div>
                @endif
                @if (($pdfSettings['upi_qr'] ?? true) && !empty($data['company']['upi_qr_path']))
                    @php
                        $qrPath = ltrim(str_replace(['public/', 'storage/', '/storage/'], '', $data['company']['upi_qr_path']), '/');
                        $qrUrl = (request()->route('action') !== 'download' && !($is_pdf ?? false))
                            ? asset('storage/' . $qrPath)
                            : public_path('storage/' . $qrPath);
                    @endphp
                    <div style="display: inline-block; text-align: left; vertical-align: top; margin-top: 4px;">
                        <div style="font-size: 7pt; color: #64748b; font-weight: bold; margin-bottom: 1px;">Scan to Pay (UPI)</div>
                        <img src="{{ $qrUrl }}" style="max-height: 55px; max-width: 55px; object-fit: contain; border: 1px solid #000; padding: 1px; background: #fff;" />
                    </div>
                @endif
            </td>
        </tr>
    </table>

    {{-- FOOTER SIGNATURE & CERTIFICATE SECTION --}}
    @if(($pdfSettings['signature'] ?? true) !== false)
        <table class="footer-table">
            <tr>
                <td class="footer-cert">
                    Certificate that the goods on which the GST tax has been charged have not been exempted under the GST Tax Act or the rules made thereunder and the amount charged on Account of GST Tax on these goods are not more than that what is payable under the provisions of the relevant Act or the Rules made thereunder<br>
                    <strong style="margin-top: 4px; display: inline-block;">E. & O. E.</strong>
                </td>
                <td class="footer-sig-cust">
                    Customer Signature
                </td>
                <td class="footer-sig-auth" style="position: relative;">
                    @if (($pdfSettings['show_seal_signature'] ?? true) && !empty($data['company']['seal_sign_path']))
                        @php
                            $sealPath = ltrim(str_replace(['public/', 'storage/', '/storage/'], '', $data['company']['seal_sign_path']), '/');
                            $sealUrl = (request()->route('action') !== 'download' && !($is_pdf ?? false)) ? asset('storage/' . $sealPath) : public_path('storage/' . $sealPath);
                        @endphp
                        <div style="text-align: center; margin-bottom: -6px;">
                            <img src="{{ $sealUrl }}" style="max-height: 45px; max-width: 95px; object-fit: contain;" />
                        </div>
                    @endif
                    <div style="font-size: 7.5pt; margin-bottom: 15px;">For <strong>{{ strtoupper($data['company']['name']) }}</strong></div>
                    <div>Authorised Signatory</div>
                </td>
            </tr>
        </table>
    @endif
</div>
</body>
</html>
