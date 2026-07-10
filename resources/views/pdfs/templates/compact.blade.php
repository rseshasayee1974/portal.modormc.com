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
        body { font-size: 9.5px; }
        .inv-root { border: 1px solid #cbd5e1; width: 100%; }
        @media screen {
            .inv-root { min-height: 297mm; }
        }
        .compact-header { display: table; width: 100%; border-bottom: 1px solid #cbd5e1; }
        .ch-left  { display: table-cell; vertical-align: middle; padding: 5px 0 5px 8px; }
        .ch-right { display: table-cell; vertical-align: middle; text-align: right; padding: 5px 8px 5px 0; }
        .co-name  { font-size: 11px; font-weight: 700; }
        .co-det   { font-size: 8.5px; color: #64748b; }
        .inv-title{ font-size: 16px; font-weight: 900; }
        .inv-ref  { font-size: 9px; color: #64748b; }
        .meta-strip { display: table; width: 100%; border-bottom: 1px solid #cbd5e1; background: #f7f7f7; }
        .ms-cell  { display: table-cell; padding: 2px 6px; border-right: 1px solid #cbd5e1; font-size: 9px; white-space: nowrap; }
        .ms-cell:last-child { border-right: none; }
        .ms-key   { color: #888; }
        .ms-val   { font-weight: 700; }
        .addr-split { display: table; width: 100%; border-bottom: 1px solid #cbd5e1; }
        .as-cell  { display: table-cell; padding: 5px 8px; width: 50%; border-right: 1px solid #cbd5e1; font-size: 9.5px; vertical-align: top; }
        .as-cell:last-child { border-right: none; }
        .as-hdr   { font-size: 8px; font-weight: 700; color: #888; text-transform: uppercase; margin-bottom: 1px; }
        .as-name  { font-weight: 700; }
        .items-table { width: 100%; border-collapse: collapse; border-bottom: 1px solid #cbd5e1; }
        .items-table th { background: #555; color: #fff; padding: 3px 5px; font-size: 8.5px; font-weight: 700; }
        .items-table td { padding: 3px 5px; vertical-align: top; border-bottom: 1px solid #eee; font-size: 9.5px; }
        .items-table tr:nth-child(even) td { background: #fafafa; }
        .totals-compact { width: 220px; margin-left: auto; border-collapse: collapse; }
        .tc-label { text-align: right; padding: 2px 8px; color: #555; font-size: 9.5px; }
        .tc-val   { text-align: right; padding: 2px 8px; font-size: 9.5px; }
        .tc-grand td { font-weight: 700; background: #555; color: #fff; padding: 3px 8px; }
    </style>
</head>
<body>
@include('pdfs.partials._print_actions')
<div class="inv-root">
    <div class="compact-header">
        <div class="ch-left">
            @if($pdfSettings['company_name'] ?? true) <div class="co-name">{{ $data['company']['name'] }}</div> @endif
            @if($pdfSettings['address'] ?? true)
                <div class="co-det">{{ $data['company']['address'] }}, {{ $data['company']['city'] }}</div>
            @endif
            @if(($pdfSettings['gstin'] ?? true) && $data['company']['gstin']) <div class="co-det">GSTIN: {{ $data['company']['gstin'] }}</div> @endif
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
            $metaFields['PO#'] = ($data['meta']['po_number'] ?? '');
            $metaFields['Status'] = $data['state'];
        @endphp
        @foreach($metaFields as $k => $v)
            @if($v) <div class="ms-cell"><span class="ms-key">{{ $k }}: </span><span class="ms-val">{{ $v }}</span></div> @endif
        @endforeach
    </div>

    <div class="addr-split">
        <div class="as-cell">
            @if($pdfSettings['bill_to'] ?? true)
                <div class="as-hdr">{{ $labels['bill_to'] ?? ($data['doc_title'] === 'PURCHASE ORDER' ? 'Vendor' : 'Bill To') }}</div>
                <div class="as-name">{{ $data['bill_to']['name'] }}</div>
                <div>{{ $data['bill_to']['address'] }}, {{ $data['bill_to']['city'] }}</div>
            @endif
        </div>
        <div class="as-cell">
            @if($pdfSettings['ship_to'] ?? true)
                <div class="as-hdr">{{ $labels['ship_to'] ?? 'Delivery' }}</div>
                <div class="as-name">{{ $data['ship_to']['name'] }}</div>
                <div>{{ $data['ship_to']['address'] }}, {{ $data['ship_to']['city'] }}</div>
            @endif
        </div>
    </div>

    <table class="items-table">
            <thead>
                <tr>
                    <th class="text-center" style="width:28px">#</th>
                    <th class="text-left">Item &amp; Description</th>
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
                            @if (($pdfSettings['hsn_code'] ?? true) && ($item['hsn'] ?? false))
                                <div class="small muted">HSN: {{ $item['hsn'] }}</div>
                            @endif
                        </td>
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

    @if($pdfSettings['signature'] ?? true)
        <div style="text-align:right;padding:5px 10px;border-top:1px solid #ccc;font-size:9px;color:#aaa;min-height:40px">Authorized Signatory — {{ $data['company']['name'] }}</div>
    @endif

    @include('pdfs.partials._footer')
</div>
</body>
</html>
