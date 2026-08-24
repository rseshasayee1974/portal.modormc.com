@php
    $pdfSettings = $data['settings']['pdf'] ?? [];
    $labels = $pdfSettings['labels'] ?? [];
@endphp
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <title>{{ $data['doc_title'] }} - {{ $data['doc_no'] }}</title>
    @include('pdfs.partials._common_styles')
    <style>
        .inv-root {
            border: 1px solid #cbd5e1;
            width: 100%;
            display: flex;
            flex-direction: column;
        }

        @media screen {
            .inv-root {
                min-height: 297mm;
            }
        }

        /* HEADER */
        .inv-header {
            display: table;
            width: 100%;
            border-bottom: 1px solid #cbd5e1;
        }

        .header-left {
            display: table-cell;
            vertical-align: top;
            padding: 10px 14px;
        }

        .header-right {
            display: table-cell;
            vertical-align: top;
            text-align: right;
            padding: 10px 14px;
        }

        .co-name {
            font-size: 15px;
            font-weight: 700;
        }

        .co-detail {
            font-size: 10px;
            color: #64748b;
            line-height: 1.45;
        }

        .inv-title {
            font-size: 24px;
            font-weight: 900;
            line-height: 1.1;
        }

        .inv-ref {
            font-size: 10.5px;
            color: #64748b;
            margin-top: 2px;
        }

        /* INFO GRID (3 columns) */
        .info-table {
            width: 100%;
            border-collapse: collapse;
            border-bottom: 1px solid #cbd5e1;
        }

        .info-cell {
            padding: 7px 12px;
            vertical-align: top;
            border-right: 1px solid #cbd5e1;
            font-size: 11px;
        }

        .no-right {
            border-right: none;
        }

        .kv-table {
            border-collapse: collapse;
            width: 100%;
        }

        .kv-key {
            color: #64748b;
            white-space: nowrap;
            padding: 1px 0;
            min-width: 80px;
        }

        .kv-sep {
            padding: 1px 5px;
            color: #64748b;
        }

        .kv-val {
            color: #1e293b;
        }

        .addr-hdr {
            font-size: 9px;
            font-weight: 700;
            color: #4f46e5;
            text-transform: uppercase;
            letter-spacing: 0.15em;
            margin-bottom: 3px;
        }

        .addr-name {
            font-weight: 700;
        }

        .addr-line {
            color: #94a3b8;
            line-height: 1.5;
        }

        /* ITEMS TABLE */
        .items-table {
            width: 100%;
            border-collapse: collapse;
            border-bottom: 1px solid #cbd5e1;
        }

        .items-table thead tr {
            background: #fff;
        }

        .items-table th {
            border-top: 1.5px solid #1e293b;
            border-bottom: 1.5px solid #1e293b;
            padding: 5px 8px;
            font-size: 10px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.12em;
        }

        .items-table td {
            padding: 6px 8px;
            vertical-align: top;
            border-bottom: 1px solid #e2e8f0;
            font-size: 11px;
        }

        /* TOTALS + FOOTER BLOCK */
        .totals-split {
            display: table;
            width: 100%;
            border-bottom: 1px solid #cbd5e1;
        }

        .totals-left {
            display: table-cell;
            vertical-align: top;
            padding: 8px 12px;
            border-right: 1px solid #cbd5e1;
            font-size: 11px;
            width: 55%;
        }

        .totals-right {
            display: table-cell;
            vertical-align: top;
        }

        .breakdown-table {
            width: 100%;
            border-collapse: collapse;
        }

        .breakdown-table td {
            padding: 3px 10px;
            vertical-align: middle;
        }

        .bt-label {
            text-align: right;
            color: #64748b;
            padding-right: 14px !important;
            width: 58%;
            font-size: 11px;
        }

        .bt-val {
            text-align: right;
            white-space: nowrap;
            font-size: 11px;
        }

        .bt-total-row {
            border-top: 1px solid #cbd5e1;
            border-bottom: 1px solid #cbd5e1;
        }

        .tow-label {
            color: #64748b;
            font-size: 10px;
            margin-bottom: 2px;
        }

        .tow-value {
            font-style: italic;
            font-weight: 700;
            font-size: 11px;
            line-height: 1.5;
        }

        .sig-section {
            display: table;
            width: 100%;
            border-bottom: 1px solid #cbd5e1;
        }

        .sig-left {
            display: table-cell;
            vertical-align: bottom;
            font-size: 11px;
            width: 60%;
            padding: 8px 12px;
        }

        .sig-right {
            display: table-cell;
            vertical-align: bottom;
            text-align: right;
            width: 40%;
            padding: 8px 12px;
        }

        .sig-line {
            display: inline-block;
            width: 160px;
            border-top: 1px solid #999;
            padding-top: 4px;
            text-align: center;
            font-size: 10.5px;
            color: #64748b;
        }
    </style>
</head>

<body>
    @include('pdfs.partials._print_actions')
    <div class="inv-root">

        @if (
            ($pdfSettings['show_einvoice_details'] ?? true) &&
                (!empty($data['meta']['irn']) || !empty($data['meta']['qr_code'])))
            <div
                style="display: table; width: 100%; border-bottom: 1px solid #cbd5e1; padding: 6px 12px; font-size: 9.5px; background: #fafafa;">
                <div style="display: table-cell; vertical-align: middle;">
                    @if (!empty($data['meta']['irn']))
                        <div><strong>IRN :</strong> {{ $data['meta']['irn'] }}</div>
                    @endif
                    @if (!empty($data['meta']['ack_no']))
                        <div><strong>Ack No. :</strong> {{ $data['meta']['ack_no'] }}</div>
                    @endif
                    @if (!empty($data['meta']['ack_date']))
                        <div><strong>Ack Date :</strong> {{ $data['meta']['ack_date'] }}</div>
                    @endif
                </div>
                @if (!empty($data['meta']['qr_code']))
                    <div style="display: table-cell; vertical-align: middle; text-align: right; width: 70px;">
                        <img src="{{ $data['meta']['qr_code'] }}"
                            style="max-height: 60px; max-width: 60px; object-fit: contain;" />
                    </div>
                @endif
            </div>
        @endif

        {{-- HEADER --}}
        <div class="inv-header">
            <div class="header-left">
                @if (($pdfSettings['logo'] ?? true) && !empty($data['company']['logo_path']))
                    @php
                        $cleanLogoPath = ltrim(
                            str_replace(['public/', 'storage/', '/storage/'], '', $data['company']['logo_path']),
                            '/',
                        );
                        $logoUrl =
                            request()->route('action') !== 'download' && !($is_pdf ?? false)
                                ? asset('storage/' . $cleanLogoPath)
                                : public_path('storage/' . $cleanLogoPath);
                    @endphp
                    <div style="margin-bottom: 6px;">
                        <img src="{{ $logoUrl }}"
                            style="max-height: 50px; max-width: 180px; object-fit: contain;" />
                    </div>
                @endif
                @if ($pdfSettings['company_name'] ?? true)
                    <div class="co-name">{{ $data['company']['name'] }}</div>
                @endif
                @if ($pdfSettings['address'] ?? true)
                    <div class="co-detail">{{ $data['company']['address'] }}</div>
                    <div class="co-detail">{{ $data['company']['city'] }}, {{ $data['company']['state'] }} -
                        {{ $data['company']['pin'] }}</div>
                @endif
                @if (($pdfSettings['gstin'] ?? true) && $data['company']['gstin'])
                    <div class="co-detail">GSTIN: {{ $data['company']['gstin'] }}</div>
                @endif
            </div>
            <div class="header-right">
                <div class="inv-title">{{ $data['doc_title'] }}</div>
                <div class="inv-ref">
                    @if ($pdfSettings['invoice_number'] ?? true)
                        {{ str_contains($data['doc_title'], 'INVOICE') ? 'Invoice#' : ($data['doc_title'] === 'PURCHASE ORDER' ? 'PO # : ' : 'Ref # : ') }}
                        <strong>{{ $data['doc_no'] }}</strong>
                    @endif
                </div>
            </div>
        </div>

        {{-- INFO GRID (3 col: details | bill_to | ship_to) --}}
        <table class="info-table">
            <tr>
                <td class="info-cell" style="width:33%">
                    <table class="kv-table">
                        @php
                            $infoLines = [];
                            if ($pdfSettings['date'] ?? true) {
                                $infoLines['Date'] = $data['doc_date'];
                            }
                            if (
                                ($pdfSettings['due_date'] ?? true) &&
                                !empty($data['due_date']) &&
                                $data['due_date'] !== 'N/A'
                            ) {
                                $infoLines['Due Date'] = $data['due_date'];
                            }
                            $infoLines['Delivery'] = $data['delivery_date'];
                            if (!empty($data['meta']['so_no'])) {
                                $infoLines['SO No'] = $data['meta']['so_no'];
                            }
                            $infoLines['PO#'] = $data['meta']['po_number'] ?? '';
                            if (
                                ($pdfSettings['show_einvoice_details'] ?? true) &&
                                !empty($data['meta']['eway_bill_no'])
                            ) {
                                $infoLines['EWayBillNo'] = $data['meta']['eway_bill_no'];
                            }
                            if ($pdfSettings['status'] ?? false) {
                                $infoLines['Status'] = $data['state'];
                            }
                            if (!empty($data['meta']['sales_executive_name'])) {
                                $infoLines['Sales Executive'] = $data['meta']['sales_executive_name'];
                            }
                            if (!empty($data['meta']['sales_executive_mobile'])) {
                                $infoLines['Contact No'] = $data['meta']['sales_executive_mobile'];
                            }
                        @endphp
                        @foreach ($infoLines as $label => $val)
                            @if ($val)
                                <tr>
                                    <td class="kv-key">{{ $label }}</td>
                                    <td class="kv-sep">:</td>
                                    <td class="kv-val bold">{{ $val }}</td>
                                </tr>
                            @endif
                        @endforeach
                    </table>
                </td>
                <td class="info-cell" style="width:34%">
                    @if ($pdfSettings['bill_to'] ?? true)
                        <div class="addr-hdr">{{ $labels['bill_to'] ?? 'Bill To' }}</div>
                        <div class="addr-name">{{ $data['bill_to']['name'] }}</div>
                        <div class="addr-line">{{ $data['bill_to']['address'] }}</div>
                        <div class="addr-line">{{ $data['bill_to']['city'] }}, {{ $data['bill_to']['state'] }}
                            {{ $data['bill_to']['pin'] }}</div>
                        @if (($pdfSettings['gstin'] ?? true) && $data['bill_to']['gstin'])
                            <div class="addr-line small">GSTIN: {{ $data['bill_to']['gstin'] }}</div>
                        @endif
                    @endif
                </td>
                <td class="info-cell no-right" style="width:33%">
                    @if (
                        ($pdfSettings['show_customer_ref'] ?? true) &&
                            (!empty($data['meta']['acc_no']) ||
                                !empty($data['meta']['sales_person']) ||
                                !empty($data['meta']['pump']) ||
                                !empty($data['meta']['design_mix_ref'])))
                        <div class="addr-hdr">Customer Ref</div>
                        <table class="kv-table" style="font-size: 10px;">
                            @if (!empty($data['meta']['acc_no']))
                                <tr>
                                    <td class="kv-key">Acc No</td>
                                    <td class="kv-sep">:</td>
                                    <td class="kv-val bold">{{ $data['meta']['acc_no'] }}</td>
                                </tr>
                            @endif
                            @if (!empty($data['meta']['po_number']))
                                <tr>
                                    <td class="kv-key">PO</td>
                                    <td class="kv-sep">:</td>
                                    <td class="kv-val bold">{{ $data['meta']['po_number'] }}</td>
                                </tr>
                            @endif
                            @if (!empty($data['meta']['sales_person']))
                                <tr>
                                    <td class="kv-key">Sales Person</td>
                                    <td class="kv-sep">:</td>
                                    <td class="kv-val bold">{{ $data['meta']['sales_person'] }}</td>
                                </tr>
                            @endif
                            @if (!empty($data['meta']['pump']))
                                <tr>
                                    <td class="kv-key">Pump</td>
                                    <td class="kv-sep">:</td>
                                    <td class="kv-val bold">{{ $data['meta']['pump'] }}</td>
                                </tr>
                            @endif
                            @if (!empty($data['meta']['quality_incharge']))
                                <tr>
                                    <td class="kv-key">Quality InCharge</td>
                                    <td class="kv-sep">:</td>
                                    <td class="kv-val bold">{{ $data['meta']['quality_incharge'] }}</td>
                                </tr>
                            @endif
                            @if (!empty($data['meta']['design_mix_ref']))
                                <tr>
                                    <td class="kv-key">Design Mix Ref</td>
                                    <td class="kv-sep">:</td>
                                    <td class="kv-val bold">{{ $data['meta']['design_mix_ref'] }}</td>
                                </tr>
                            @endif
                        </table>
                    @elseif ($pdfSettings['ship_to'] ?? true)
                        <div class="addr-hdr">{{ $labels['ship_to'] ?? 'Ship To' }}</div>
                        <div class="addr-name">{{ $data['ship_to']['name'] }}</div>
                        <div class="addr-line">{{ $data['ship_to']['address'] }}</div>
                        <div class="addr-line">{{ $data['ship_to']['city'] }}, {{ $data['ship_to']['state'] }}
                            {{ $data['ship_to']['pin'] }}</div>
                    @endif
                </td>
            </tr>
        </table>

        @if (
            ($pdfSettings['show_carrier_driver'] ?? true) &&
                !empty($data['meta']['carrier_driver']) &&
                $data['meta']['carrier_driver'] !== '-')
            <div style="padding: 5px 12px; border-bottom: 1px solid #cbd5e1; font-size: 11px; background: #f8fafc;">
                <strong>Carrier - Driver:</strong> {{ $data['meta']['carrier_driver'] }}
            </div>
        @endif

        {{-- ITEMS --}}
        <table class="items-table">
            <thead>
                <tr>
                    <th class="text-center" style="width:28px">#</th>
                    <th class="text-left">Item &amp; Description</th>
                    @if ($pdfSettings['show_pump_charges'] ?? true)
                        <th class="text-left" style="width:120px">Concrete Type</th>
                        <th class="text-right" style="width:90px">Pump Charges</th>
                    @endif
                    @if ($pdfSettings['qty'] ?? true)
                        <th class="text-right" style="width:55px">Qty</th>
                    @endif
                    @if ($pdfSettings['unit'] ?? true)
                        <th class="text-center" style="width:50px">Unit</th>
                    @endif
                    <th class="text-right" style="width:80px">{{ $labels['rate'] ?? 'Rate' }}</th>
                    @if ($pdfSettings['tax_rate'] ?? true)
                        <th class="text-right" style="width:55px">Tax %</th>
                    @endif
                    @if ($pdfSettings['tax_amount'] ?? true)
                        <th class="text-right" style="width:70px">Tax Amt</th>
                    @endif
                    <th class="text-right" style="width:80px">{{ $labels['amount'] ?? 'Amount' }}</th>
                </tr>
            </thead>
            <tbody>
                @php
                    $totalCols = 2; // # and Name
                    if ($pdfSettings['show_pump_charges'] ?? true) $totalCols += 2;
                    if ($pdfSettings['qty'] ?? true) $totalCols++;
                    if ($pdfSettings['unit'] ?? true) $totalCols++;
                    $totalCols++; // rate
                    if ($pdfSettings['tax_rate'] ?? true) $totalCols++;
                    if ($pdfSettings['tax_amount'] ?? true) $totalCols++;
                    $totalCols++; // amount

                    $recipeColspan = min(7, $totalCols - 1);
                    $remainingCols = max(0, $totalCols - 1 - $recipeColspan);
                @endphp
                @foreach ($data['items'] as $item)
                    @php
                        $hasRecipe = !empty($item['recipe_materials']) && count($item['recipe_materials']) > 0;
                        $hasDesc = ($pdfSettings['description'] ?? true) && !empty($item['description']);
                        $hasSubRow = $hasRecipe || $hasDesc || !empty($item['pump_rates']);
                    @endphp
                    <tr>
                        <td class="text-center" style="vertical-align: middle; {{ $hasSubRow ? 'border-bottom: none;' : '' }}">
                            <span style="display: inline-block; width: 22px; height: 22px; line-height: 22px; text-align: center; background-color: #e0edff; color: #2563eb; font-weight: 700; font-size: 11px; border-radius: 6px;">{{ $item['no'] }}</span>
                        </td>
                        <td style="vertical-align: middle; {{ $hasSubRow ? 'border-bottom: none;' : '' }}">
                            <div style="font-size: 12.5px; font-weight: 700; color: #0f172a;">{{ $item['name'] }}</div>
                        </td>
                        @if ($pdfSettings['show_pump_charges'] ?? true)
                            <td class="text-left" style="vertical-align: middle; {{ $hasSubRow ? 'border-bottom: none;' : '' }}">{{ $item['operation_type'] ?? '-' }}</td>
                            <td class="text-right" style="vertical-align: middle; {{ $hasSubRow ? 'border-bottom: none;' : '' }}">
                                {{ isset($item['pump_charge']) && $item['pump_charge'] > 0 ? number_format($item['pump_charge'], 2) : '-' }}
                            </td>
                        @endif
                        @if ($pdfSettings['qty'] ?? true)
                            <td class="text-right bold" style="vertical-align: middle; font-size: 11.5px; color: #0f172a; {{ $hasSubRow ? 'border-bottom: none;' : '' }}">{{ number_format($item['qty'], 2) }}</td>
                        @endif
                        @if ($pdfSettings['unit'] ?? true)
                            <td class="text-center" style="vertical-align: middle; {{ $hasSubRow ? 'border-bottom: none;' : '' }}">{{ $item['unit'] }}</td>
                        @endif
                        <td class="text-right" style="vertical-align: middle; {{ $hasSubRow ? 'border-bottom: none;' : '' }}">{{ number_format($item['unit_price'], 2) }}</td>
                        @if ($pdfSettings['tax_rate'] ?? true)
                            <td class="text-right muted" style="vertical-align: middle; {{ $hasSubRow ? 'border-bottom: none;' : '' }}">
                                {{ $item['tax_rate'] > 0 || (isset($item['tax_name']) && $item['tax_name'] !== '-') ? ($item['tax_rate'] == floor($item['tax_rate']) ? number_format($item['tax_rate'], 0) : number_format($item['tax_rate'], 2)) . '%' : '-' }}
                            </td>
                        @endif
                        @if ($pdfSettings['tax_amount'] ?? true)
                            <td class="text-right muted" style="vertical-align: middle; {{ $hasSubRow ? 'border-bottom: none;' : '' }}">
                                {{ $item['tax_amount'] > 0 || (isset($item['tax_name']) && $item['tax_name'] !== '-') ? number_format($item['tax_amount'], 2) : '-' }}
                            </td>
                        @endif
                        <td class="text-right" style="vertical-align: middle; font-weight: 800; font-size: 12.5px; color: #2563eb; {{ $hasSubRow ? 'border-bottom: none;' : '' }}">
                            {{ $data['meta']['currency_symbol'] ?? '₹' }}{{ number_format($item['total'], 2) }}
                        </td>
                    </tr>
                    @if ($hasSubRow)
                        <tr>
                            {{-- <td style="border-top: none; padding-top: 0;"></td> --}}
                            <td colspan="{{ $recipeColspan }}" style="border-top: none; padding-top: 0; padding-bottom: 8px;">
                                @if ($hasRecipe)
                                    <div style="font-size: 9.5px; font-weight: 700; color: #2563eb; margin-top: 2px; margin-bottom: 3px;">Recipe Details:</div>
                                    <div style="display: inline-block; background-color: #f8faff; border: 1px solid #dbeafe; border-radius: 6px; padding: 3px 8px;">
                                        <table style="border-collapse: collapse; border: none; margin: 0; padding: 0; font-size: 9.5px; color: #334155;">
                                            @php
                                                $allSegments = [];
                                                foreach ($item['recipe_materials'] as $rm) {
                                                    $allSegments[] = ['is_hsn' => false, 'name' => $rm['name'], 'qty' => $rm['qty'], 'uom' => $rm['uom']];
                                                }
                                                if ($pdfSettings['hsn_code'] ?? true) {
                                                    $allSegments[] = ['is_hsn' => true, 'val' => $item['hsn'] ?? '-'];
                                                }
                                                $chunkSize = count($allSegments) > 3 ? (int)ceil(count($allSegments) / 2) : count($allSegments);
                                                $chunks = array_chunk($allSegments, max(1, $chunkSize));
                                            @endphp
                                            @foreach ($chunks as $cIdx => $chunk)
                                                <tr style="{{ $cIdx > 0 ? 'border-top: 1px solid #e2e8f0;' : '' }}">
                                                    @foreach ($chunk as $sIdx => $seg)
                                                        @php $isLast = ($sIdx === count($chunk) - 1); @endphp
                                                        <td style="padding: 2px 8px 2px 4px; {{ !$isLast ? 'border-right: 1px solid #e2e8f0;' : '' }} white-space: nowrap; vertical-align: middle;">
                                                            @if (!empty($seg['is_hsn']))
                                                                <span style="color: #2563eb; font-weight: 700;">HSN:</span> {{ $seg['val'] }}
                                                            @else
                                                                <span style="color: #64748b; margin-right: 2px;">&bull;</span> {{ $seg['name'] }} ({{ $seg['qty'] }} {{ $seg['uom'] }})
                                                            @endif
                                                        </td>
                                                    @endforeach
                                                </tr>
                                            @endforeach
                                        </table>
                                    </div>
                                @elseif ($hasDesc)
                                    <div class="item-sub">{{ $item['description'] }}</div>
                                    @if (($pdfSettings['hsn_code'] ?? true) && ($item['hsn'] ?? false))
                                        <div class="small muted" style="margin-top: 2px;"><span style="color: #2563eb; font-weight: 700;">HSN:</span> {{ $item['hsn'] }}</div>
                                    @endif
                                @elseif (($pdfSettings['hsn_code'] ?? true) && ($item['hsn'] ?? false))
                                    <div class="small muted" style="margin-top: 2px;"><span style="color: #2563eb; font-weight: 700;">HSN:</span> {{ $item['hsn'] }}</div>
                                @endif
                                @include('pdfs.partials._pump_rates_table', ['item' => $item])
                            </td>
                            @if ($remainingCols > 0)
                                <td colspan="{{ $remainingCols }}" style="border-top: none;"></td>
                            @endif
                        </tr>
                    @endif
                @endforeach
            </tbody>
        </table>

        {{-- TOTALS SPLIT --}}
        <div class="totals-split">
            <div class="totals-left">
                @if (($pdfSettings['notes'] ?? true) && ($data['meta']['notes'] ?? false))
                    <div class="small muted" style="margin-bottom:6px">Notes</div>
                    <div style="margin-bottom:8px;font-size:11px">{{ $data['meta']['notes'] }}</div>
                @endif
                @if ($pdfSettings['total_words'] ?? true)
                    <div class="tow-label">Total In Words</div>
                    <div class="tow-value">
                        {{ $data['meta']['total_words'] ?: ($data['meta']['currency_code'] ?? 'INR') . ' ' . number_format($data['totals']['grand_total'], 2) . ' Only' }}
                    </div>
                @endif
            </div>
            <div class="totals-right">
                <table class="breakdown-table">
                    <tr>
                        <td class="bt-label">Sub Total</td>
                        <td class="bt-val">
                            {{ $data['meta']['currency_symbol'] ?? '₹' }}{{ number_format($data['totals']['sub_total'], 2) }}
                        </td>
                    </tr>
                    @if (
                        (($pdfSettings['show_pump_charges'] ?? true) || ($pdfSettings['pump_rates'] ?? true)) &&
                            isset($data['totals']['pump_rate']) &&
                            $data['totals']['pump_rate'] > 0)
                        <tr>
                            <td class="bt-label">Concrete Pump Charges</td>
                            <td class="bt-val">
                                {{ $data['meta']['currency_symbol'] ?? '₹' }}{{ number_format($data['totals']['pump_rate'], 2) }}
                            </td>
                        </tr>
                    @endif
                    @if (($pdfSettings['discount'] ?? true) && $data['totals']['discount'] > 0)
                        <tr>
                            <td class="bt-label red">Discount (-)</td>
                            <td class="bt-val red">
                                {{ $data['meta']['currency_symbol'] ?? '₹' }}{{ number_format($data['totals']['discount'], 2) }}
                            </td>
                        </tr>
                    @endif
                    @foreach ($data['totals']['tax_lines'] as $tl)
                        @php
                            $showTax = true;
                            if (str_contains($tl['label'], 'CGST') && !($pdfSettings['cgst'] ?? true)) {
                                $showTax = false;
                            }
                            if (str_contains($tl['label'], 'SGST') && !($pdfSettings['sgst'] ?? true)) {
                                $showTax = false;
                            }
                            if (str_contains($tl['label'], 'IGST') && !($pdfSettings['igst'] ?? true)) {
                                $showTax = false;
                            }
                        @endphp
                        @if ($showTax)
                            <tr>
                                <td class="bt-label">{{ $tl['label'] }}</td>
                                <td class="bt-val">
                                    {{ $data['meta']['currency_symbol'] ?? '₹' }}{{ number_format($tl['amount'], 2) }}
                                </td>
                            </tr>
                        @endif
                    @endforeach
                    @if (($pdfSettings['shipping'] ?? true) && $data['totals']['shipping'] > 0)
                        <tr>
                            <td class="bt-label">Shipping</td>
                            <td class="bt-val">
                                {{ $data['meta']['currency_symbol'] ?? '₹' }}{{ number_format($data['totals']['shipping'], 2) }}
                            </td>
                        </tr>
                    @endif
                    @if (($pdfSettings['adjustment'] ?? true) && ($data['totals']['adjustment'] ?? 0) != 0)
                        <tr>
                            <td class="bt-label">Adjustment</td>
                            <td class="bt-val">
                                {{ $data['totals']['adjustment'] > 0 ? '+' : '' }}{{ $data['meta']['currency_symbol'] ?? '₹' }}{{ number_format($data['totals']['adjustment'], 2) }}
                            </td>
                        </tr>
                    @endif
                    @if (($pdfSettings['round_off'] ?? true) && ($data['totals']['round_off'] ?? 0) != 0)
                        <tr>
                            <td class="bt-label">Round Off</td>
                            <td class="bt-val">
                                {{ $data['totals']['round_off'] > 0 ? '+' : '' }}{{ $data['meta']['currency_symbol'] ?? '₹' }}{{ number_format($data['totals']['round_off'], 2) }}
                            </td>
                        </tr>
                    @endif
                    <tr class="bt-total-row">
                        <td class="bt-label bold">Total</td>
                        <td class="bt-val bold">
                            {{ $data['meta']['currency_symbol'] ?? '₹' }}{{ number_format($data['totals']['grand_total'], 2) }}
                        </td>
                    </tr>
                </table>
            </div>
        </div>

        {{-- TERMS --}}
        @php
            $termsText = trim(
                !empty($pdfSettings['terms_text']) ? $pdfSettings['terms_text'] : $data['meta']['terms_text'] ?? '',
            );
            $termsHtml =
                !empty($termsText) && $termsText === strip_tags($termsText) ? nl2br(e($termsText)) : $termsText;
        @endphp
        @if (($pdfSettings['terms'] ?? true) && !empty($termsText))
            <div style="padding:7px 12px;border-bottom:1px solid #cbd5e1;font-size:11px">
                <div class="small muted" style="margin-bottom:2px">Terms &amp; Conditions</div>
                <div class="terms-text-content"
                    style="font-size:10px;color:#334155;text-align:justify;white-space:normal !important;word-break:break-word;">
                    {!! $termsHtml !!}</div>
            </div>
        @endif

        {{-- SIGNATURE --}}
        @if ($pdfSettings['signature'] ?? true)
            <div class="sig-section" style="min-height:80px">
                <div class="sig-left">
                    @if (($pdfSettings['show_bank_details'] ?? true) && !empty($data['company']['bank']['bank_name']))
                        <div style="margin-bottom: 8px; font-size: 10px; color: #334155;">
                            <div class="small muted"
                                style="font-weight: bold; text-transform: uppercase; color: #4f46e5; margin-bottom: 2px;">
                                Bank Information</div>
                            <div>Account Name: <strong>{{ $data['company']['bank']['account_name'] }}</strong></div>
                            <div>Account Number: <strong>{{ $data['company']['bank']['account_number'] }}</strong>
                            </div>
                            <div>Bank: <strong>{{ $data['company']['bank']['bank_name'] }}</strong> (Branch:
                                {{ $data['company']['bank']['branch'] }})</div>
                            <div>IFSC Code: <strong>{{ $data['company']['bank']['ifsc_code'] }}</strong></div>
                        </div>
                    @endif
                    @if (($pdfSettings['upi_qr'] ?? true) && !empty($data['company']['upi_qr_path']))
                        @php
                            $qrPath = ltrim(
                                str_replace(['public/', 'storage/', '/storage/'], '', $data['company']['upi_qr_path']),
                                '/',
                            );
                            $qrUrl =
                                request()->route('action') !== 'download' && !($is_pdf ?? false)
                                    ? asset('storage/' . $qrPath)
                                    : public_path('storage/' . $qrPath);
                        @endphp
                        <div style="display: inline-block; text-align: left; vertical-align: top; margin-top: 10px;">
                            <div class="small muted" style="margin-bottom: 4px; font-weight: bold;">Scan to Pay (UPI)
                            </div>
                            <img src="{{ $qrUrl }}"
                                style="max-height: 80px; max-width: 80px; object-fit: contain; border: 1px solid #cbd5e1; padding: 2px; background: #fff;" />
                        </div>
                    @endif
                </div>
                <div class="sig-right" style="padding-bottom:10px; position: relative;">
                    @if (!empty($data['company']['seal_sign_path']))
                        @php
                            $sealPath = ltrim(
                                str_replace(
                                    ['public/', 'storage/', '/storage/'],
                                    '',
                                    $data['company']['seal_sign_path'],
                                ),
                                '/',
                            );
                            $sealUrl =
                                request()->route('action') !== 'download' && !($is_pdf ?? false)
                                    ? asset('storage/' . $sealPath)
                                    : public_path('storage/' . $sealPath);
                        @endphp
                        <div style="margin-bottom: -15px; text-align: center;">
                            <img src="{{ $sealUrl }}"
                                style="margin-left:150px;max-height: 100px; max-width: 120px; object-fit: contain;" />
                        </div>
                    @endif
                    <div class="sig-line">Authorized Signatory<br><span class="small muted">For
                            {{ $data['company']['name'] }}</span></div>
                </div>
            </div>
        @endif

        @include('pdfs.partials._footer')
    </div>
</body>

</html>
