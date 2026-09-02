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
            width: 100%;
            box-sizing: border-box;
        }

        .inv-header {
            display: table;
            width: 100%;
            margin-bottom: 12px;
        }

        .header-left {
            display: table-cell;
            vertical-align: top;
        }

        .header-right {
            display: table-cell;
            vertical-align: top;
            text-align: right;
        }

        .co-name {
            font-size: 14px;
            font-weight: 800;
            color: #1e293b;
            text-transform: uppercase;
            margin-bottom: 2px;
        }

        .co-detail {
            font-size: 10px;
            color: #64748b;
            line-height: 1.45;
        }

        .inv-title {
            font-size: 26px;
            font-weight: 900;
            line-height: 1.0;
            color: #0f172a;
            text-transform: uppercase;
            letter-spacing: -0.02em;
        }

        .inv-ref {
            font-size: 10.5px;
            color: #64748b;
            margin-top: 4px;
        }

        .addr-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 12px;
        }

        .addr-table td {
            vertical-align: top;
            padding: 0 12px 0 0;
        }

        .addr-table td:last-child {
            padding-right: 0;
        }

        .addr-label {
            color: #64748b;
            font-size: 9.5px;
            font-weight: 700;
            text-transform: uppercase;
            margin-bottom: 4px;
            border-bottom: 1px solid #e2e8f0;
            padding-bottom: 2px;
        }

        .addr-name {
            font-weight: 800;
            font-size: 12px;
            color: #1e293b;
            margin-bottom: 3px;
        }

        .addr-line {
            color: #64748b;
            font-size: 10px;
            line-height: 1.45;
        }

        /* Dark-header span tables */
        .details-bar {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 12px;
            background: #f8fafc;
            border: 1px solid #e2e8f0;
            border-radius: 6px;
        }

        .dbar-th {
            padding: 6px 10px;
            color: #fff;
            background: #1e293b;
            font-size: 9px;
            text-transform: uppercase;
            font-weight: 700;
            text-align: left;
            border-right: 1px solid #334155;
        }

        .dbar-th:last-child {
            border-right: none;
        }

        .dbar-td {
            padding: 6px 10px;
            font-size: 10px;
            color: #1e293b;
            text-align: left;
            border-right: 1px solid #e2e8f0;
        }

        .dbar-td:last-child {
            border-right: none;
        }

        .items-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 12px;
        }

        .items-table thead tr {
            background: #1e293b;
            color: #fff;
        }

        .items-table th {
            padding: 7px 8px;
            font-size: 9.5px;
            text-transform: uppercase;
            font-weight: 700;
            border: 1px solid #1e293b;
        }

        .items-table td {
            padding: 6px 8px;
            vertical-align: top;
            border: 1px solid #e2e8f0;
            font-size: 10px;
            color: #1e293b;
        }

        .items-table tbody tr:nth-child(even) td {
            background: #f8fafc;
        }

        .totals-block {
            text-align: right;
            padding: 2px 0;
            margin-bottom: 12px;
        }

        .breakdown-table {
            width: 280px;
            border-collapse: collapse;
            margin-left: auto;
        }

        .breakdown-table td {
            padding: 4px 8px;
            font-size: 10px;
        }

        .bt-label {
            text-align: right;
            color: #64748b;
            padding-right: 12px !important;
            width: 55%;
        }

        .bt-val {
            text-align: right;
            white-space: nowrap;
            font-weight: 600;
            color: #1e293b;
        }

        .bt-total-row {
            border-top: 1px solid #cbd5e1;
            border-bottom: 1px solid #cbd5e1;
            background: #f8fafc;
        }

        .bt-total-row td {
            padding-top: 6px !important;
            padding-bottom: 6px !important;
            font-size: 11px;
        }

        .tow-row {
            width: 100%;
            padding: 6px 0;
            margin-top: 4px;
        }

        .tow-label {
            font-size: 9.5px;
            color: #64748b;
            margin-bottom: 2px;
            text-align: right;
        }

        .tow-value {
            font-weight: 700;
            text-align: right;
            font-size: 10.5px;
            color: #1e293b;
        }

        .bottom-section {
            padding: 8px 12px;
            border-top: 1px solid #e2e8f0;
            font-size: 10px;
            margin-bottom: 8px;
        }

        .section-label {
            color: #64748b;
            font-size: 9.5px;
            font-weight: 700;
            text-transform: uppercase;
            margin-bottom: 2px;
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
                style="display: table; width: 100%; border-bottom: 1px solid #cbd5e1; padding: 6px 12px; font-size: 9.5px; background: #fafafa; margin-bottom: 12px;">
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
                    <div class="co-detail">{{ $data['company']['city'] }}, {{ $data['company']['state'] }}</div>
                @endif
                @if (($pdfSettings['gstin'] ?? true) && $data['company']['gstin'])
                    <div class="co-detail" style="font-weight:700; color:#1e293b; margin-top:2px;">GSTIN:
                        {{ $data['company']['gstin'] }}</div>
                @endif
            </div>
            <div class="header-right">
                <div class="inv-title">{{ $data['doc_title'] }}</div>
                <div class="inv-ref">Ref# <strong>{{ $data['doc_no'] }}</strong></div>
            </div>
        </div>

        {{-- Bill To / Ship To / Customer Ref — borderless --}}
        @php
            $showCustRef =
                ($pdfSettings['show_customer_ref'] ?? true) &&
                (!empty($data['meta']['acc_no']) ||
                    !empty($data['meta']['sales_person']) ||
                    !empty($data['meta']['pump']) ||
                    !empty($data['meta']['design_mix_ref']));
            $colWidth = $showCustRef ? '33.33%' : '50%';
        @endphp
        <table class="addr-table">
            <tr>
                <td style="width: {{ $colWidth }};">
                    @if ($pdfSettings['bill_to'] ?? true)
                        <div class="addr-label">
                            {{ $labels['bill_to'] ?? ($data['doc_title'] === 'PURCHASE ORDER' ? 'Vendor' : 'Bill To') }}
                        </div>
                        <div class="addr-name">{{ $data['bill_to']['name'] }}</div>
                        <div class="addr-line">
                            @if (!empty($data['bill_to']['address']))
                                {{ $data['bill_to']['address'] }}<br>
                            @endif
                            @if (!empty($data['bill_to']['city']) || !empty($data['bill_to']['state']))
                                {{ $data['bill_to']['city'] }}, {{ $data['bill_to']['state'] }}
                            @endif
                        </div>
                        @if (($pdfSettings['gstin'] ?? true) && $data['bill_to']['gstin'])
                            <div class="addr-line" style="font-weight:700; color:#1e293b; margin-top:2px;">GSTIN:
                                {{ $data['bill_to']['gstin'] }}</div>
                        @endif
                    @endif
                </td>
                <td style="width: {{ $colWidth }};">
                    @if ($pdfSettings['ship_to'] ?? true)
                        <div class="addr-label">{{ $labels['ship_to'] ?? 'Ship To / Delivery' }}</div>
                        <div class="addr-name">{{ $data['ship_to']['name'] }}</div>
                        <div class="addr-line">
                            @if (!empty($data['ship_to']['address']))
                                {{ $data['ship_to']['address'] }}<br>
                            @endif
                            @if (!empty($data['ship_to']['city']) || !empty($data['ship_to']['state']))
                                {{ $data['ship_to']['city'] }}, {{ $data['ship_to']['state'] }}
                            @endif
                        </div>
                    @endif
                </td>
                @if ($showCustRef)
                    <td style="width: 33.33%;">
                        <div class="addr-label">Customer Ref</div>
                        <div class="addr-line" style="font-size: 10px; color: #1e293b;">
                            @if (!empty($data['meta']['acc_no']))
                                <div>Acc No: <strong>{{ $data['meta']['acc_no'] }}</strong></div>
                            @endif
                            @if (!empty($data['meta']['po_number']))
                                <div>PO: <strong>{{ $data['meta']['po_number'] }}</strong></div>
                            @endif
                            @if (!empty($data['meta']['sales_person']))
                                <div>Sales Person: <strong>{{ $data['meta']['sales_person'] }}</strong></div>
                            @endif
                            @if (!empty($data['meta']['pump']))
                                <div>Pump: <strong>{{ $data['meta']['pump'] }}</strong></div>
                            @endif
                            @if (!empty($data['meta']['quality_incharge']))
                                <div>Quality InCharge: <strong>{{ $data['meta']['quality_incharge'] }}</strong></div>
                            @endif
                            @if (!empty($data['meta']['design_mix_ref']))
                                <div>Concrete Grade: <strong>{{ $data['meta']['design_mix_ref'] }}</strong></div>
                            @endif
                        </div>
                    </td>
                @endif
            </tr>
        </table>

        @if (
            ($pdfSettings['show_carrier_driver'] ?? true) &&
                !empty($data['meta']['carrier_driver']) &&
                $data['meta']['carrier_driver'] !== '-')
            <div
                style="padding: 6px 12px; font-size: 10.5px; background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 4px; margin-bottom: 12px;">
                <strong>Carrier - Driver:</strong> {{ $data['meta']['carrier_driver'] }}
            </div>
        @endif

        {{-- Details bar --}}
        <table class="details-bar">
            <thead>
                <tr>
                    <th class="dbar-th">Date</th>
                    @if (($pdfSettings['due_date'] ?? true) && !empty($data['due_date']) && $data['due_date'] !== 'N/A')
                        <th class="dbar-th">Due Date</th>
                    @endif
                    <th class="dbar-th">Delivery</th>
                    @if (!empty($data['meta']['so_no']))
                        <th class="dbar-th">SO No</th>
                    @endif
                    @if (!empty($data['meta']['po_number']) && $data['meta']['po_number'] !== '-')
                        <th class="dbar-th">PO#</th>
                    @endif
                    @if (($pdfSettings['show_einvoice_details'] ?? true) && !empty($data['meta']['eway_bill_no']))
                        <th class="dbar-th">EWayBillNo</th>
                    @endif
                    @if (!empty($data['meta']['sales_executive_name']))
                        <th class="dbar-th">Sales Exec</th>
                    @endif
                    <th class="dbar-th">Project</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td class="dbar-td">{{ $data['doc_date'] }}</td>
                    @if (($pdfSettings['due_date'] ?? true) && !empty($data['due_date']) && $data['due_date'] !== 'N/A')
                        <td class="dbar-td">{{ $data['due_date'] }}</td>
                    @endif
                    <td class="dbar-td">{{ $data['delivery_date'] }}</td>
                    @if (!empty($data['meta']['so_no']))
                        <td class="dbar-td">{{ $data['meta']['so_no'] }}</td>
                    @endif
                    @if (!empty($data['meta']['po_number']) && $data['meta']['po_number'] !== '-')
                        <td class="dbar-td">{{ $data['meta']['po_number'] }}</td>
                    @endif
                    @if (($pdfSettings['show_einvoice_details'] ?? true) && !empty($data['meta']['eway_bill_no']))
                        <td class="dbar-td">{{ $data['meta']['eway_bill_no'] }}</td>
                    @endif
                    @if (!empty($data['meta']['sales_executive_name']))
                        <td class="dbar-td">{{ $data['meta']['sales_executive_name'] }}</td>
                    @endif
                    <td class="dbar-td">{{ $data['meta']['project_name'] ?? '-' }}</td>
                </tr>
            </tbody>
        </table>

        {{-- Items --}}
        <table class="items-table">
            <thead>
                <tr>
                    <th class="text-center" style="width:28px">#</th>
                    <th class="text-left">Item &amp; Description</th>
                    @if ($pdfSettings['show_pump_charges'] ?? true)
                        <th class="text-left" style="width:110px">Concrete Type</th>
                        <th class="text-right" style="width:80px">Pump Charges</th>
                    @endif
                    @if ($pdfSettings['qty'] ?? true)
                        <th class="text-right" style="width:50px">Qty</th>
                    @endif
                    @if ($pdfSettings['unit'] ?? true)
                        <th class="text-center" style="width:45px">Unit</th>
                    @endif
                    <th class="text-right" style="width:75px">{{ $labels['rate'] ?? 'Rate' }}</th>
                    @if ($pdfSettings['discount'] ?? false)
                        <th class="text-right" style="width:60px">Discount</th>
                    @endif
                    @if ($pdfSettings['tax_rate'] ?? true)
                        <th class="text-right" style="width:50px">Tax %</th>
                    @endif
                    @if ($pdfSettings['tax_amount'] ?? true)
                        <th class="text-right" style="width:65px">Tax Amt</th>
                    @endif
                    <th class="text-right" style="width:75px">{{ $labels['amount'] ?? 'Amount' }}</th>
                </tr>
            </thead>
            <tbody>
                @php
                    $totalCols = 2; // # and Name
                    if ($pdfSettings['show_pump_charges'] ?? true) $totalCols += 2;
                    if ($pdfSettings['qty'] ?? true) $totalCols++;
                    if ($pdfSettings['unit'] ?? true) $totalCols++;
                    $totalCols++; // rate
                    if ($pdfSettings['discount'] ?? false) $totalCols++;
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
                        @if ($pdfSettings['discount'] ?? false)
                            <td class="text-right muted" style="vertical-align: middle; {{ $hasSubRow ? 'border-bottom: none;' : '' }}">
                                {{ !empty($item['discount']) && $item['discount'] > 0 ? number_format($item['discount'], 2) : '-' }}
                            </td>
                        @endif
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
                            <td style="border-top: none; padding-top: 0;"></td>
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

        {{-- Totals — right only --}}
        <div class="totals-block">
            <table class="breakdown-table">
                @if(!empty($data['totals']['sub_total']) && $data['totals']['sub_total'] > 0)
                    <tr>
                        <td class="bt-label">Sub Total</td>
                        <td class="bt-val">
                            {{ $data['meta']['currency_symbol'] ?? '₹' }}{{ number_format($data['totals']['sub_total'], 2) }}
                        </td>
                    </tr>
                @endif
                @php $pumpChg = $data['totals']['pump_charge'] ?? $data['totals']['pump_charges'] ?? $data['totals']['pump_rate'] ?? 0; @endphp
                @if (
                    (($pdfSettings['show_pump_charges'] ?? true) || ($pdfSettings['pump_rates'] ?? true)) &&
                        $pumpChg > 0)
                    <tr>
                        <td class="bt-label">Concrete Pump Charges</td>
                        <td class="bt-val">
                            {{ $data['meta']['currency_symbol'] ?? '₹' }}{{ number_format($pumpChg, 2) }}
                        </td>
                    </tr>
                @endif
                @if (($pdfSettings['discount'] ?? true) && !empty($data['totals']['discount']) && $data['totals']['discount'] > 0)
                    <tr>
                        <td class="bt-label" style="color:#ef4444;">Discount (-)</td>
                        <td class="bt-val" style="color:#ef4444;">
                            -{{ $data['meta']['currency_symbol'] ?? '₹' }}{{ number_format($data['totals']['discount'], 2) }}
                        </td>
                    </tr>
                @endif
                @php $hireChg = $data['totals']['hire_charge'] ?? $data['totals']['transport_expenses'] ?? 0; @endphp
                @if (($pdfSettings['hire_charge'] ?? true) && $hireChg > 0)
                    <tr>
                        <td class="bt-label">Hire Charge</td>
                        <td class="bt-val">
                            {{ $data['meta']['currency_symbol'] ?? '₹' }}{{ number_format($hireChg, 2) }}
                        </td>
                    </tr>
                @endif
                @if (($pdfSettings['pass_amount'] ?? true) && !empty($data['totals']['pass_amount']) && $data['totals']['pass_amount'] > 0)
                    <tr>
                        <td class="bt-label">Pass Amount</td>
                        <td class="bt-val">
                            {{ $data['meta']['currency_symbol'] ?? '₹' }}{{ number_format($data['totals']['pass_amount'], 2) }}
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
                @if (($pdfSettings['shipping'] ?? true) && !empty($data['totals']['shipping']) && $data['totals']['shipping'] > 0)
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
                    <td class="bt-label" style="font-weight:bold;">Total</td>
                    <td class="bt-val" style="font-weight:bold;">
                        {{ $data['meta']['currency_symbol'] ?? '₹' }}{{ number_format($data['totals']['grand_total'], 2) }}
                    </td>
                </tr>
            </table>
            @if ($pdfSettings['total_words'] ?? true)
                <div class="tow-row">
                    <div class="tow-label">Total In Words:</div>
                    <div class="tow-value">
                        {{ $data['meta']['total_words'] ?: ($data['meta']['currency_code'] ?? 'INR') . ' ' . number_format($data['totals']['grand_total'], 2) . ' Only' }}
                    </div>
                </div>
            @endif
        </div>

        @if (($pdfSettings['notes'] ?? true) && ($data['meta']['notes'] ?? false))
            <div class="bottom-section">
                <div class="section-label">Notes</div>
                <div>{{ $data['meta']['notes'] }}</div>
            </div>
        @endif
        @php
            $termsText = trim(
                !empty($pdfSettings['terms_text']) ? $pdfSettings['terms_text'] : $data['meta']['terms_text'] ?? '',
            );
            $termsHtml =
                !empty($termsText) && $termsText === strip_tags($termsText) ? nl2br(e($termsText)) : $termsText;
        @endphp
        @if (($pdfSettings['terms'] ?? true) && !empty($termsText))
            <div class="bottom-section">
                <div class="section-label">Terms &amp; Conditions</div>
                <div class="terms-text-content"
                    style="font-size:10px;text-align:justify;white-space:normal !important;word-break:break-word;">
                    {!! $termsHtml !!}</div>
            </div>
        @endif

        @if ($pdfSettings['signature'] ?? true)
            <div style="min-height:80px;padding:10px 18px;border-top:1px solid #ccc;">
                <table style="width:100%; border-collapse: collapse; border: none; margin: 0; padding: 0;">
                    <tr>
                        <td style="width: 50%; vertical-align: bottom; border: none; text-align: left; padding: 0;">
                            @if (($pdfSettings['upi_qr'] ?? true) && !empty($data['company']['upi_qr_path']))
                                @php
                                    $qrPath = ltrim(
                                        str_replace(
                                            ['public/', 'storage/', '/storage/'],
                                            '',
                                            $data['company']['upi_qr_path'],
                                        ),
                                        '/',
                                    );
                                    $qrUrl =
                                        request()->route('action') !== 'download' && !($is_pdf ?? false)
                                            ? asset('storage/' . $qrPath)
                                            : public_path('storage/' . $qrPath);
                                @endphp
                                <div
                                    style="display: inline-block; text-align: left; vertical-align: top; margin-top: 10px;">
                                    <div
                                        style="font-size: 8px; color: #64748b; font-weight: bold; margin-bottom: 2px;">
                                        Scan to Pay (UPI)</div>
                                    <img src="{{ $qrUrl }}"
                                        style="max-height: 80px; max-width: 80px; object-fit: contain; border: 1px solid #cbd5e1; padding: 2px; background: #fff;" />
                                </div>
                            @endif
                        </td>
                        <td style="width: 50%; vertical-align: bottom; border: none; text-align: right; padding: 0;">
                            <div
                                style="margin-top:20px; display: inline-block; text-align: center; position: relative;">
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
                                            style="max-height: 45px; max-width: 120px; object-fit: contain;" />
                                    </div>
                                @else
                                    <div style="height: 30px;"></div>
                                @endif
                                <span
                                    style="display:inline-block;width:160px;border-top:1px solid #999;padding-top:4px;text-align:center;font-size:10px;color:#64748b">Authorized
                                    Signatory<br><span style="font-size:9px">For
                                        {{ $data['company']['name'] }}</span></span>
                            </div>
                        </td>
                    </tr>
                </table>
            </div>
        @endif

        @include('pdfs.partials._footer')
    </div>
</body>

</html>
