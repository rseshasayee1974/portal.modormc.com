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
        body { font-size: 9px; }
        .inv-root { border: 1px solid #cbd5e1; width: 100%; box-sizing: border-box; }
        .compact-header { display: table; width: 100%; border-bottom: 1px solid #cbd5e1; margin-bottom: 8px; }
        .ch-left  { display: table-cell; vertical-align: middle; padding: 6px 8px; }
        .ch-right { display: table-cell; vertical-align: middle; text-align: right; padding: 6px 8px; }
        .co-name  { font-size: 12px; font-weight: 800; color: #1e293b; text-transform: uppercase; }
        .co-det   { font-size: 8.5px; color: #64748b; line-height: 1.3; }
        .inv-title{ font-size: 18px; font-weight: 900; color: #0f172a; text-transform: uppercase; }
        .inv-ref  { font-size: 9px; color: #64748b; margin-top: 2px; }

        .meta-strip { display: table; width: 100%; border-collapse: collapse; border-bottom: 1px solid #cbd5e1; background: #f8fafc; margin-bottom: 8px; }
        .ms-cell  { display: table-cell; padding: 4px 8px; border-right: 1px solid #e2e8f0; font-size: 9px; vertical-align: top; }
        .ms-cell:last-child { border-right: none; }
        .ms-key   { color: #64748b; font-size: 8px; font-weight: 600; text-transform: uppercase; }
        .ms-val   { font-weight: 700; color: #1e293b; font-size: 9.5px; }

        .addr-table { width: 100%; border-collapse: collapse; border-bottom: 1px solid #cbd5e1; margin-bottom: 8px; }
        .addr-table td { vertical-align: top; padding: 6px 8px; border-right: 1px solid #cbd5e1; font-size: 9.5px; }
        .addr-table td:last-child { border-right: none; }
        .as-hdr   { font-size: 8px; font-weight: 800; color: #64748b; text-transform: uppercase; margin-bottom: 3px; border-bottom: 1px solid #f1f5f9; padding-bottom: 1px; }
        .as-name  { font-weight: 800; font-size: 11px; color: #1e293b; margin-bottom: 2px; }
        .as-det   { color: #64748b; font-size: 9px; line-height: 1.4; }

        .items-table { width: 100%; border-collapse: collapse; border-bottom: 1px solid #cbd5e1; margin-bottom: 8px; }
        .items-table th { background: #334155; color: #fff; padding: 5px 6px; font-size: 8.5px; text-transform: uppercase; font-weight: 700; border: 1px solid #334155; }
        .items-table td { padding: 4px 6px; vertical-align: top; border-bottom: 1px solid #e2e8f0; border-right: 1px solid #f1f5f9; font-size: 9.5px; color: #1e293b; }
        .items-table tr:nth-child(even) td { background: #f8fafc; }

        .totals-compact { width: 240px; margin-left: auto; border-collapse: collapse; margin-bottom: 8px; }
        .tc-label { text-align: right; padding: 3px 8px; color: #64748b; font-size: 9px; }
        .tc-val   { text-align: right; padding: 3px 8px; font-size: 9.5px; font-weight: 600; color: #1e293b; }
        .tc-grand td { font-weight: 800; background: #1e293b; color: #fff; padding: 4px 8px; font-size: 10.5px; }
    </style>
</head>
<body>
@include('pdfs.partials._print_actions')
<div class="inv-root">
    @if (($pdfSettings['show_einvoice_details'] ?? true) && (!empty($data['meta']['irn']) || !empty($data['meta']['qr_code'])))
        <div style="display: table; width: 100%; border-bottom: 1px solid #cbd5e1; padding: 4px 8px; font-size: 8.5px; background: #fafafa; margin-bottom: 6px;">
            <div style="display: table-cell; vertical-align: middle;">
                @if(!empty($data['meta']['irn'])) <div><strong>IRN:</strong> {{ $data['meta']['irn'] }}</div> @endif
                @if(!empty($data['meta']['ack_no'])) <div><strong>Ack No:</strong> {{ $data['meta']['ack_no'] }}</div> @endif
            </div>
            @if(!empty($data['meta']['qr_code']))
                <div style="display: table-cell; vertical-align: middle; text-align: right; width: 50px;">
                    <img src="{{ $data['meta']['qr_code'] }}" style="max-height: 45px; max-width: 45px; object-fit: contain;" />
                </div>
            @endif
        </div>
    @endif

    <div class="compact-header">
        <div class="ch-left">
            @if (($pdfSettings['logo'] ?? true) && !empty($data['company']['logo_path']))
                @php
                    $cleanLogoPath = ltrim(
                        str_replace(['public/', 'storage/', '/storage/'], '', $data['company']['logo_path']),
                        '/',
                    );
                    $logoUrl = (request()->route('action') !== 'download' && !($is_pdf ?? false))
                        ? asset('storage/' . $cleanLogoPath)
                        : public_path('storage/' . $cleanLogoPath);
                @endphp
                <div style="margin-bottom: 4px;">
                    <img src="{{ $logoUrl }}" style="max-height: 40px; max-width: 140px; object-fit: contain;" />
                </div>
            @endif
            @if($pdfSettings['company_name'] ?? true) <div class="co-name">{{ $data['company']['name'] }}</div> @endif
            @if($pdfSettings['address'] ?? true)
                <div class="co-det">{{ $data['company']['address'] }}, {{ $data['company']['city'] }}</div>
            @endif
            @if(($pdfSettings['gstin'] ?? true) && $data['company']['gstin']) <div class="co-det" style="font-weight:700; color:#1e293b;">GSTIN: {{ $data['company']['gstin'] }}</div> @endif
        </div>
        <div class="ch-right">
            <div class="inv-title">{{ $data['doc_title'] }}</div>
            <div class="inv-ref">{{ $data['doc_no'] }} &bull; {{ $data['doc_date'] }}</div>
        </div>
    </div>

    <div class="meta-strip">
        @php
            $metaFields = [];
            if(($pdfSettings['due_date'] ?? true) && !empty($data['due_date']) && $data['due_date'] !== 'N/A') $metaFields['Due'] = $data['due_date'];
            $metaFields['Delivery'] = $data['delivery_date'];
            if(!empty($data['meta']['so_no'])) $metaFields['SO#'] = $data['meta']['so_no'];
            $metaFields['PO#'] = ($data['meta']['po_number'] ?? '');
            if(($pdfSettings['show_einvoice_details'] ?? true) && !empty($data['meta']['eway_bill_no'])) $metaFields['EWayBill'] = $data['meta']['eway_bill_no'];
            $metaFields['Status'] = $data['state'];
            if (!empty($data['meta']['sales_executive_name'])) $metaFields['Sales Exec'] = $data['meta']['sales_executive_name'];
        @endphp
        @foreach($metaFields as $k => $v)
            @if($v) <div class="ms-cell"><span class="ms-key">{{ $k }}: </span><span class="ms-val">{{ $v }}</span></div> @endif
        @endforeach
    </div>

    @php
        $showCustRef = ($pdfSettings['show_customer_ref'] ?? true) && (!empty($data['meta']['acc_no']) || !empty($data['meta']['sales_person']) || !empty($data['meta']['pump']) || !empty($data['meta']['design_mix_ref']));
        $colWidth = $showCustRef ? '33.33%' : '50%';
    @endphp
    <table class="addr-table">
        <tr>
            <td style="width: {{ $colWidth }};">
                @if($pdfSettings['bill_to'] ?? true)
                    <div class="as-hdr">{{ $labels['bill_to'] ?? ($data['doc_title'] === 'PURCHASE ORDER' ? 'Vendor' : 'Bill To') }}</div>
                    <div class="as-name">{{ $data['bill_to']['name'] }}</div>
                    <div class="as-det">
                        @if(!empty($data['bill_to']['address'])) {{ $data['bill_to']['address'] }}<br> @endif
                        @if(!empty($data['bill_to']['city']) || !empty($data['bill_to']['state'])) {{ $data['bill_to']['city'] }}, {{ $data['bill_to']['state'] }} @endif
                    </div>
                @endif
            </td>
            <td style="width: {{ $colWidth }};">
                @if($pdfSettings['ship_to'] ?? true)
                    <div class="as-hdr">{{ $labels['ship_to'] ?? 'Delivery' }}</div>
                    <div class="as-name">{{ $data['ship_to']['name'] }}</div>
                    <div class="as-det">
                        @if(!empty($data['ship_to']['address'])) {{ $data['ship_to']['address'] }}<br> @endif
                        @if(!empty($data['ship_to']['city']) || !empty($data['ship_to']['state'])) {{ $data['ship_to']['city'] }}, {{ $data['ship_to']['state'] }} @endif
                    </div>
                @endif
            </td>
            @if($showCustRef)
                <td style="width: 33.33%;">
                    <div class="as-hdr">Customer Ref</div>
                    <div class="as-det" style="font-size: 8.5px; color: #1e293b;">
                        @if(!empty($data['meta']['acc_no'])) <div>Acc No: <strong>{{ $data['meta']['acc_no'] }}</strong></div> @endif
                        @if(!empty($data['meta']['po_number'])) <div>PO: <strong>{{ $data['meta']['po_number'] }}</strong></div> @endif
                        @if(!empty($data['meta']['sales_person'])) <div>Sales: <strong>{{ $data['meta']['sales_person'] }}</strong></div> @endif
                        @if(!empty($data['meta']['pump'])) <div>Pump: <strong>{{ $data['meta']['pump'] }}</strong></div> @endif
                    </div>
                </td>
            @endif
        </tr>
    </table>

    @if (($pdfSettings['show_carrier_driver'] ?? true) && !empty($data['meta']['carrier_driver']) && $data['meta']['carrier_driver'] !== '-')
        <div style="padding: 4px 8px; font-size: 8.5px; background: #f8fafc; border-bottom: 1px solid #cbd5e1; margin-bottom: 8px;">
            <strong>Carrier - Driver:</strong> {{ $data['meta']['carrier_driver'] }}
        </div>
    @endif

    <table class="items-table">
            <thead>
                <tr>
                    <th class="text-center" style="width:28px">#</th>
                    <th class="text-left">Item &amp; Description</th>
                    @if ($pdfSettings['show_pump_charges'] ?? true)
                        <th class="text-left" style="width:120px">Operation Type</th>
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
                @foreach ($data['items'] as $item)
                    <tr>
                        <td class="text-center">{{ $item['no'] }}</td>
                        <td>
                            <div class="item-name">{{ $item['name'] }}</div>
                            @if (($pdfSettings['description'] ?? true) && $item['description'])
                                <div class="item-sub">{{ $item['description'] }}</div>
                            @endif
                            @include('pdfs.partials._pump_rates_table', ['item' => $item])
                            @if (($pdfSettings['hsn_code'] ?? true) && ($item['hsn'] ?? false))
                                <div class="small muted">HSN: {{ $item['hsn'] }}</div>
                            @endif
                        </td>
                        @if ($pdfSettings['show_pump_charges'] ?? true)
                            <td class="text-left">{{ $item['operation_type'] ?? '-' }}</td>
                            <td class="text-right">{{ isset($item['pump_charge']) && $item['pump_charge'] > 0 ? number_format($item['pump_charge'], 2) : '-' }}</td>
                        @endif
                        @if ($pdfSettings['qty'] ?? true)
                            <td class="text-right bold">{{ number_format($item['qty'], 2) }}</td>
                        @endif
                        @if ($pdfSettings['unit'] ?? true)
                            <td class="text-center">{{ $item['unit'] }}</td>
                        @endif
                        <td class="text-right">{{ number_format($item['unit_price'], 2) }}</td>
                        @if ($pdfSettings['tax_rate'] ?? true)
                            <td class="text-right muted">
                                {{ $item['tax_rate'] > 0 || (isset($item['tax_name']) && $item['tax_name'] !== '-') ? number_format($item['tax_rate'], 0) . '%' : '-' }}
                            </td>
                        @endif
                        @if ($pdfSettings['tax_amount'] ?? true)
                            <td class="text-right muted">
                                {{ $item['tax_amount'] > 0 || (isset($item['tax_name']) && $item['tax_name'] !== '-') ? number_format($item['tax_amount'], 2) : '-' }}
                            </td>
                        @endif
                        <td class="text-right bold">
                            {{ $data['meta']['currency_symbol'] ?? '₹' }}{{ number_format($item['total'], 2) }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>

    <div style="padding:4px 8px;text-align:right;">
        <table class="totals-compact">
            <tr>
                <td class="tc-label">Sub Total</td>
                <td class="tc-val">
                    {{ $data['meta']['currency_symbol'] ?? '₹' }}{{ number_format($data['totals']['sub_total'], 2) }}
                </td>
            </tr>
            @if ((($pdfSettings['show_pump_charges'] ?? true) || ($pdfSettings['pump_rates'] ?? true)) && isset($data['totals']['pump_rate']) && $data['totals']['pump_rate'] > 0)
                <tr>
                    <td class="tc-label">Concrete Pump Charges</td>
                    <td class="tc-val">
                        {{ $data['meta']['currency_symbol'] ?? '₹' }}{{ number_format($data['totals']['pump_rate'], 2) }}
                    </td>
                </tr>
            @endif
            @if (($pdfSettings['discount'] ?? true) && $data['totals']['discount'] > 0)
                <tr>
                    <td class="tc-label" style="color:#ef4444;">Discount (-)</td>
                    <td class="tc-val" style="color:#ef4444;">
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
                        <td class="tc-label">{{ $tl['label'] }}</td>
                        <td class="tc-val">
                            {{ $data['meta']['currency_symbol'] ?? '₹' }}{{ number_format($tl['amount'], 2) }}
                        </td>
                    </tr>
                @endif
            @endforeach
            @if (($pdfSettings['shipping'] ?? true) && $data['totals']['shipping'] > 0)
                <tr>
                    <td class="tc-label">Shipping</td>
                    <td class="tc-val">
                        {{ $data['meta']['currency_symbol'] ?? '₹' }}{{ number_format($data['totals']['shipping'], 2) }}
                    </td>
                </tr>
            @endif
            @if (($pdfSettings['adjustment'] ?? true) && ($data['totals']['adjustment'] ?? 0) != 0)
                <tr>
                    <td class="tc-label">Adjustment</td>
                    <td class="tc-val">
                        {{ $data['totals']['adjustment'] > 0 ? '+' : '' }}{{ $data['meta']['currency_symbol'] ?? '₹' }}{{ number_format($data['totals']['adjustment'], 2) }}
                    </td>
                </tr>
            @endif
            @if (($pdfSettings['round_off'] ?? true) && ($data['totals']['round_off'] ?? 0) != 0)
                <tr>
                    <td class="tc-label">Round Off</td>
                    <td class="tc-val">
                        {{ $data['totals']['round_off'] > 0 ? '+' : '' }}{{ $data['meta']['currency_symbol'] ?? '₹' }}{{ number_format($data['totals']['round_off'], 2) }}
                    </td>
                </tr>
            @endif
            <tr class="tc-grand">
                <td class="tc-label" style="font-weight:bold;">Total</td>
                <td class="tc-val" style="font-weight:bold;">
                    {{ $data['meta']['currency_symbol'] ?? '₹' }}{{ number_format($data['totals']['grand_total'], 2) }}
                </td>
            </tr>
        </table>
    </div>

    @if(($pdfSettings['terms'] ?? true) && ($data['meta']['terms_text'] ?? ''))
    <div class="terms-text-content" style="padding:5px 8px;font-size:8.5px;border-top:1px solid #ccc;color:#666;text-align:justify;white-space:normal !important;word-break:break-word;">{!! $data['meta']['terms_text'] !!}</div>
    @endif

    @if (($pdfSettings['show_bank_details'] ?? true) && !empty($data['company']['bank']['bank_name']))
        <div style="padding:4px 8px;font-size:8.5px;border-top:1px solid #ccc;color:#334155;">
            <strong>Bank:</strong> {{ $data['company']['bank']['bank_name'] }} &bull; <strong>A/C:</strong> {{ $data['company']['bank']['account_number'] }} &bull; <strong>IFSC:</strong> {{ $data['company']['bank']['ifsc_code'] }}
        </div>
    @endif

    @if($pdfSettings['signature'] ?? true)
        <div style="text-align:right;padding:5px 10px;border-top:1px solid #ccc;font-size:9px;color:#333;min-height:40px">
            @if (($pdfSettings['show_seal_signature'] ?? true) && !empty($data['company']['seal_sign_path']))
                @php
                    $sealPath = ltrim(str_replace(['public/', 'storage/', '/storage/'], '', $data['company']['seal_sign_path']), '/');
                    $sealUrl = (request()->route('action') !== 'download' && !($is_pdf ?? false)) ? asset('storage/' . $sealPath) : public_path('storage/' . $sealPath);
                @endphp
                <div style="margin-bottom: 2px;">
                    <img src="{{ $sealUrl }}" style="max-height: 45px; max-width: 90px; object-fit: contain;" />
                </div>
            @endif
            Authorized Signatory — {{ $data['company']['name'] }}
        </div>
    @endif

    @include('pdfs.partials._footer')
</div>
</body>
</html>
