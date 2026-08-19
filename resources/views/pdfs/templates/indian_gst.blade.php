@php
    $pdfSettings = $data['settings']['pdf'] ?? [];
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
        body { font-family: 'Helvetica Neue', Helvetica, Arial, sans-serif; color: #000; font-size: 9.5pt; margin: 0; padding: 0; line-height: 1.25; }
        .inv-root { width: 100%; border: 1px solid #000; box-sizing: border-box; }
        
        .header-table { width: 100%; border-collapse: collapse; }
        .header-table td { vertical-align: top; padding: 8px 10px; }
        .header-title { text-align: right; text-transform: uppercase; font-size: 13pt; font-weight: bold; }
        .header-subtitle { text-align: right; text-transform: uppercase; font-size: 9pt; color: #333; margin-top: 2px; }

        .irn-bar { border-top: 1px solid #000; padding: 6px 10px; font-size: 8.5pt; width: 100%; box-sizing: border-box; }
        .irn-bar table { width: 100%; border-collapse: collapse; }
        .irn-bar td { vertical-align: middle; padding: 0; }

        .block-table { width: 100%; border-collapse: collapse; border-top: 1px solid #000; }
        .block-th { background: #f2f2f2; border-bottom: 1px solid #000; border-right: 1px solid #000; font-size: 9pt; font-weight: bold; padding: 4px 8px; text-align: left; }
        .block-th:last-child { border-right: none; }
        .block-td { border-right: 1px solid #000; padding: 6px 8px; vertical-align: top; font-size: 8.5pt; }
        .block-td:last-child { border-right: none; }

        .kv-line { margin-bottom: 3px; }
        .kv-label { font-weight: normal; color: #111; }
        .kv-val { font-weight: bold; color: #000; }

        .carrier-bar { border-top: 1px solid #000; padding: 5px 8px; font-size: 9pt; background: #fff; }

        .items-table { width: 100%; border-collapse: collapse; border-top: 1px solid #000; border-bottom: 1px solid #000; }
        .items-table th { border-bottom: 1px solid #000; border-right: 1px solid #000; padding: 4px 6px; font-size: 8.5pt; font-weight: bold; text-align: center; background: #f9f9f9; }
        .items-table th:last-child { border-right: none; }
        .items-table td { border-right: 1px solid #000; border-bottom: 1px solid #ddd; padding: 5px 6px; font-size: 8.5pt; vertical-align: middle; }
        .items-table td:last-child { border-right: none; }
        .items-table tr:last-child td { border-bottom: none; }

        .tax-table { width: 100%; border-collapse: collapse; }
        .tax-table td { border-bottom: 1px solid #000; border-right: 1px solid #000; padding: 3px 6px; font-size: 8pt; }
        .tax-table td:last-child { border-right: none; }
        .tax-table tr:last-child td { border-bottom: none; }

        .totals-table { width: 100%; border-collapse: collapse; border-top: 1px solid #000; }
        .totals-table td { padding: 5px 8px; vertical-align: top; font-size: 8.5pt; }

        .terms-block { border-top: 1px solid #000; border-right: 1px solid #000; width: 60%; padding: 6px 8px; vertical-align: top; font-size: 8pt; }
        .bank-block { border-top: 1px solid #000; width: 40%; padding: 6px 8px; vertical-align: top; font-size: 8pt; }

        .footer-cert { border-top: 1px solid #000; border-right: 1px solid #000; width: 40%; padding: 5px 8px; font-size: 7.5pt; color: #222; vertical-align: top; }
        .footer-sig-cust { border-top: 1px solid #000; border-right: 1px solid #000; width: 25%; padding: 5px 8px; vertical-align: bottom; text-align: center; font-size: 8.5pt; }
        .footer-sig-auth { border-top: 1px solid #000; width: 35%; padding: 5px 8px; vertical-align: bottom; text-align: center; font-size: 8.5pt; }
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
                    <img src="{{ $logoUrl }}" style="max-height: 45px; max-width: 180px; object-fit: contain; vertical-align: middle; margin-right: 8px;" />
                @endif
                <span style="font-size: 13pt; font-weight: bold; text-transform: uppercase; vertical-align: middle;">{{ $data['company']['name'] }}</span>
            </td>
            <td style="width: 35%; text-align: right;">
                <div class="header-subtitle">{{ $copyType }}</div>
                <div class="header-title">{{ $data['doc_title'] }}</div>
            </td>
        </tr>
    </table>

    {{-- E-INVOICE / IRN BAR --}}
    @if (($pdfSettings['show_einvoice_details'] ?? true) && (!empty($data['meta']['irn']) || !empty($data['meta']['qr_code'])))
        <div class="irn-bar">
            <table>
                <tr>
                    <td style="vertical-align: top;">
                        @if(!empty($data['meta']['irn'])) <div>IRN : {{ $data['meta']['irn'] }}</div> @endif
                        @if(!empty($data['meta']['ack_no'])) <div>Ack No. : {{ $data['meta']['ack_no'] }}</div> @endif
                        @if(!empty($data['meta']['ack_date'])) <div>Ack Date : {{ $data['meta']['ack_date'] }}</div> @endif
                    </td>
                    @if(!empty($data['meta']['qr_code']))
                        <td style="width: 70px; text-align: right; vertical-align: top;">
                            <img src="{{ $data['meta']['qr_code'] }}" style="max-height: 65px; max-width: 65px; object-fit: contain;" />
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
                <div>{{ $data['company']['address'] }}</div>
                <div>{{ $data['company']['city'] }} - {{ $data['company']['pin'] }}</div>
                <div>{{ strtoupper($data['company']['state']) }}</div>
                @if(!empty($data['company']['phone'])) <div>Mobile Number : {{ $data['company']['phone'] }}</div> @endif
                @if(!empty($data['company']['gstin'])) <div>GSTIN : <strong>{{ $data['company']['gstin'] }}</strong></div> @endif
                <div>STATE : {{ strtoupper($data['company']['state']) }}</div>
                @if(!empty($data['company']['state_code'])) <div>STATE CODE : {{ $data['company']['state_code'] }}</div> @endif
                @if(!empty($data['company']['pan'])) <div>PAN : {{ $data['company']['pan'] }}</div> @endif
                @if(!empty($data['company']['msme_no'])) <div>MSME/Udyam No : {{ $data['company']['msme_no'] }}</div> @endif
            </td>
            <td class="block-td">
                <div>{{ $data['company']['address'] }}</div>
                <div>{{ $data['company']['city'] }} - {{ $data['company']['pin'] }}</div>
                <div>{{ $data['company']['state'] }}</div>
            </td>
            <td class="block-td">
                <div class="kv-line">Invoice No : <strong>{{ $data['doc_no'] }}</strong></div>
                <div class="kv-line">Date : <strong>{{ $data['doc_date'] }}</strong></div>
                @if(!empty($data['meta']['so_no'])) <div class="kv-line">SO No : <strong>{{ $data['meta']['so_no'] }}</strong></div> @endif
                @if(!empty($data['meta']['eway_bill_no'])) <div class="kv-line">EWayBillNo : <strong>{{ $data['meta']['eway_bill_no'] }}</strong></div> @endif
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
                        <div style="margin-top: 3px;">GSTIN : <strong>{{ $data['bill_to']['gstin'] }}</strong></div>
                    @endif
                @endif
            </td>
            <td class="block-td">
                @if($pdfSettings['ship_to'] ?? true)
                    <div style="font-weight: bold; text-transform: uppercase;">{{ $data['ship_to']['name'] }}</div>
                    <div>{{ $data['ship_to']['address'] }}</div>
                    <div>{{ $data['ship_to']['city'] }} - {{ $data['ship_to']['pin'] }}</div>
                    @if(($pdfSettings['gstin'] ?? true) && !empty($data['ship_to']['gstin']))
                        <div style="margin-top: 3px;">GSTIN : <strong>{{ $data['ship_to']['gstin'] }}</strong></div>
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
        <div class="carrier-bar">
            <strong>Carrier - Driver</strong> &nbsp;&nbsp;&nbsp;&nbsp;&nbsp; {{ $data['meta']['carrier_driver'] }}
        </div>
    @endif

    {{-- ITEMS TABLE --}}
    <table class="items-table">
        <thead>
            <tr>
                <th style="width: 8%;">Code</th>
                <th style="width: 12%;">Grade</th>
                <th style="width: 20%;">Description</th>
                <th style="width: 10%;">QTY.in CU.M</th>
                <th style="width: 10%;">Unit Price</th>
                <th style="width: 12%;">Taxable Amount</th>
                <th style="width: 18%;">Tax</th>
                <th style="width: 10%;">Total</th>
            </tr>
        </thead>
        <tbody>
            @foreach($data['items'] as $item)
                @php
                    $hasRecipe = !empty($item['recipe_materials']) && count($item['recipe_materials']) > 0;
                @endphp
                <tr>
                    <td style="text-align: center; {{ $hasRecipe ? 'border-bottom: none;' : '' }}">HSN :<br><strong>{{ $item['hsn'] ?? '38245010' }}</strong></td>
                    <td style="text-align: center; font-weight: bold; {{ $hasRecipe ? 'border-bottom: none;' : '' }}">{{ $item['name'] }}</td>
                    <td style="{{ $hasRecipe ? 'border-bottom: none;' : '' }}">{{ $item['description'] }}</td>
                    <td style="text-align: right; font-weight: bold; {{ $hasRecipe ? 'border-bottom: none;' : '' }}">{{ number_format($item['qty'], 2) }}</td>
                    <td style="text-align: right; {{ $hasRecipe ? 'border-bottom: none;' : '' }}">{{ number_format($item['unit_price'], 2) }}</td>
                    <td style="text-align: right; font-weight: bold; {{ $hasRecipe ? 'border-bottom: none;' : '' }}">{{ number_format($item['taxable_amount'] ?? ($item['qty'] * $item['unit_price']), 2) }}</td>
                    <td style="padding: 0; {{ $hasRecipe ? 'border-bottom: none;' : '' }}">
                        <table class="tax-table">
                            @php
                                $itemTaxRate = (float)($item['tax_rate'] ?? 18);
                                $itemTaxAmt = (float)($item['tax_amount'] ?? 0);
                                $taxGroup = strtoupper($item['tax_group'] ?? '');
                                $taxName = strtoupper($item['tax_name'] ?? '');
                                $isIgst = !empty($item['is_igst']) || $taxGroup === 'IGST' || str_contains($taxName, 'IGST');
                                $formattedTaxRate = $itemTaxRate == floor($itemTaxRate) ? (int)$itemTaxRate : number_format($itemTaxRate, 2);
                            @endphp
                            @if($isIgst)
                                @if(($pdfSettings['igst'] ?? true) !== false)
                                    <tr>
                                        <td style="width: 40%;">IGST@ {{ $formattedTaxRate }}%</td>
                                        <td style="width: 30%; text-align: right;">{{ $formattedTaxRate }}%</td>
                                        <td style="width: 30%; text-align: right;">{{ number_format($itemTaxAmt, 2) }}</td>
                                    </tr>
                                @endif
                            @else
                                @php
                                    $halfRate = $itemTaxRate / 2;
                                    $halfAmt = $itemTaxAmt / 2;
                                    $formattedHalfRate = $halfRate == floor($halfRate) ? (int)$halfRate : number_format($halfRate, 2);
                                @endphp
                                @if(($pdfSettings['cgst'] ?? true) !== false)
                                    <tr>
                                        <td style="width: 40%;">CGST@ {{ $formattedHalfRate }}%</td>
                                        <td style="width: 30%; text-align: right;">{{ $formattedHalfRate }}%</td>
                                        <td style="width: 30%; text-align: right;">{{ number_format($halfAmt, 2) }}</td>
                                    </tr>
                                @endif
                                @if(($pdfSettings['sgst'] ?? true) !== false)
                                    <tr>
                                        <td style="width: 40%;">SGST@ {{ $formattedHalfRate }}%</td>
                                        <td style="width: 30%; text-align: right;">{{ $formattedHalfRate }}%</td>
                                        <td style="width: 30%; text-align: right;">{{ number_format($halfAmt, 2) }}</td>
                                    </tr>
                                @endif
                            @endif
                        </table>
                    </td>
                    <td style="text-align: right; font-weight: bold; {{ $hasRecipe ? 'border-bottom: none;' : '' }}">{{ number_format($item['total'], 2) }}</td>
                </tr>
                @if ($hasRecipe)
                    <tr>
                        <td style="border-top: none; padding-top: 0;"></td>
                        <td colspan="6" style="border-top: none; padding-top: 0; padding-bottom: 8px;">
                            <div style="font-size: 9.5px; font-weight: 700; color: #2563eb; margin-top: 2px; margin-bottom: 3px;">Recipe Details:</div>
                            <div style="display: inline-block; background-color: #f8faff; border: 1px solid #dbeafe; border-radius: 6px; padding: 3px 8px;">
                                <table style="border-collapse: collapse; border: none; margin: 0; padding: 0; font-size: 9.5px; color: #334155;">
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
                                                <td style="padding: 2px 8px 2px 4px; {{ !$isLast ? 'border-right: 1px solid #e2e8f0;' : '' }} white-space: nowrap; vertical-align: middle;">
                                                    <span style="color: #64748b; margin-right: 2px;">&bull;</span> {{ $seg['name'] }} ({{ $seg['qty'] }} {{ $seg['uom'] }})
                                                </td>
                                            @endforeach
                                        </tr>
                                    @endforeach
                                </table>
                            </div>
                        </td>
                        <td style="border-top: none;"></td>
                    </tr>
                @endif
            @endforeach
        </tbody>
    </table>

    {{-- AMOUNT IN WORDS & GRAND TOTAL SUMMARY --}}
    <table class="totals-table">
        <tr>
            <td style="width: 65%; border-right: 1px solid #000;">
                <div style="font-weight: bold; margin-bottom: 4px;">Amount in Words :</div>
                @php
                    $totTaxAmt = (float)($data['totals']['tax_amount'] ?? array_sum(array_column($data['totals']['tax_lines'] ?? [], 'amount')));
                    $firstItem = $data['items'][0] ?? [];
                    $totTaxRate = (float)($firstItem['tax_rate'] ?? 18);
                    $firstTaxGroup = strtoupper($firstItem['tax_group'] ?? '');
                    $firstTaxName = strtoupper($firstItem['tax_name'] ?? '');
                    $isGlobalIgst = !empty($firstItem['is_igst']) || $firstTaxGroup === 'IGST' || str_contains($firstTaxName, 'IGST');
                    $formattedTotTaxRate = $totTaxRate == floor($totTaxRate) ? (int)$totTaxRate : number_format($totTaxRate, 2);
                @endphp
                @if($isGlobalIgst)
                    @if(($pdfSettings['igst'] ?? true) !== false)
                        <div>IGST@ {{ $formattedTotTaxRate }}% Rs. {{ number_format($totTaxAmt, 2) }}</div>
                    @endif
                @else
                    @php
                        $halfTotRate = $totTaxRate / 2;
                        $halfTotAmt = $totTaxAmt / 2;
                        $formattedHalfTotRate = $halfTotRate == floor($halfTotRate) ? (int)$halfTotRate : number_format($halfTotRate, 2);
                    @endphp
                    @if(($pdfSettings['cgst'] ?? true) !== false)
                        <div>CGST@ {{ $formattedHalfTotRate }}% Rs. {{ number_format($halfTotAmt, 2) }}</div>
                    @endif
                    @if(($pdfSettings['sgst'] ?? true) !== false)
                        <div>SGST@ {{ $formattedHalfTotRate }}% Rs. {{ number_format($halfTotAmt, 2) }}</div>
                    @endif
                @endif
                <div style="margin-top: 4px;">Grand Total <strong>{{ $data['meta']['total_words'] ?: 'Rs. ' . number_format($data['totals']['grand_total'], 2) . ' Only' }}</strong></div>
            </td>
            <td style="width: 35%; padding: 0;">
                <table style="width: 100%; border-collapse: collapse;">
                    @if (($pdfSettings['round_off'] ?? true) && ($data['totals']['round_off'] ?? 0) != 0)
                        <tr>
                            <td style="text-align: right; border-bottom: 1px solid #000; padding: 4px 8px;">Rounding off</td>
                            <td style="text-align: right; border-bottom: 1px solid #000; padding: 4px 8px; width: 80px;">{{ number_format($data['totals']['round_off'], 2) }}</td>
                        </tr>
                    @endif
                    <tr>
                        <td style="text-align: right; font-weight: bold; font-size: 10pt; padding: 6px 8px;">Grand Total</td>
                        <td style="text-align: right; font-weight: bold; font-size: 10pt; padding: 6px 8px; width: 80px;">{{ number_format($data['totals']['grand_total'], 2) }}</td>
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
                    <div style="font-weight: bold; margin-bottom: 3px; text-transform: uppercase;">TERMS &amp; CONDITIONS :</div>
                    <div class="terms-text-content" style="font-size: 7.5pt; line-height: 1.35; white-space: normal !important; word-break: break-word;">
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
                    <div style="font-weight: bold; margin-bottom: 3px; text-transform: uppercase;">BANK INFORMATION:</div>
                    <div style="font-size: 8pt; line-height: 1.4;">
                        ACCOUNT NAME: <strong>{{ strtoupper($data['company']['bank']['account_name']) }}</strong><br>
                        ACCOUNT NUMBER: <strong>{{ $data['company']['bank']['account_number'] }}</strong><br>
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
                    <div style="display: inline-block; text-align: left; vertical-align: top; margin-top: 6px;">
                        <div style="font-size: 7.5pt; color: #64748b; font-weight: bold; margin-bottom: 2px;">Scan to Pay (UPI)</div>
                        <img src="{{ $qrUrl }}" style="max-height: 70px; max-width: 70px; object-fit: contain; border: 1px solid #cbd5e1; padding: 2px; background: #fff;" />
                    </div>
                @endif
            </td>
        </tr>
    </table>

    {{-- FOOTER SIGNATURE & CERTIFICATE SECTION --}}
    <table style="width: 100%; border-collapse: collapse;">
        <tr>
            <td class="footer-cert">
                Certificate that the goods on which the GST tax has been charged have not been exempted under the GST Tax Act or the rules made thereunder and the amount charged on Account of GST Tax on these goods are not more than that what is payable under the provisions of the relevant Act or the Rules made thereunder<br>
                <strong style="margin-top: 6px; display: inline-block;">E. & O. E.</strong>
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
                    <div style="text-align: center; margin-bottom: -10px;">
                        <img src="{{ $sealUrl }}" style="max-height: 55px; max-width: 110px; object-fit: contain;" />
                    </div>
                @endif
                <div style="font-size: 8pt; margin-bottom: 20px;">For <strong>{{ strtoupper($data['company']['name']) }}</strong></div>
                <div>Authorised Signatory</div>
            </td>
        </tr>
    </table>
</div>
</body>
</html>
