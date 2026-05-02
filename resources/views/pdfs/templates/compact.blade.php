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
        .inv-root { border: 1px solid var(--color-border); width: 100%; min-height: 297mm; }
        .compact-header { display: table; width: 100%; border-bottom: 1px solid var(--color-border); padding: 5px 8px; }
        .ch-left  { display: table-cell; vertical-align: middle; }
        .ch-right { display: table-cell; vertical-align: middle; text-align: right; }
        .co-name  { font-size: 11px; font-weight: 700; }
        .co-det   { font-size: 8.5px; color: var(--color-muted); }
        .inv-title{ font-size: 16px; font-weight: 900; }
        .inv-ref  { font-size: 9px; color: var(--color-muted); }
        .meta-strip { display: table; width: 100%; border-bottom: 1px solid var(--color-border); background: #f7f7f7; }
        .ms-cell  { display: table-cell; padding: 2px 6px; border-right: 1px solid var(--color-border); font-size: 9px; white-space: nowrap; }
        .ms-cell:last-child { border-right: none; }
        .ms-key   { color: #888; }
        .ms-val   { font-weight: 700; }
        .addr-split { display: table; width: 100%; border-bottom: 1px solid var(--color-border); }
        .as-cell  { display: table-cell; padding: 5px 8px; width: 50%; border-right: 1px solid var(--color-border); font-size: 9.5px; vertical-align: top; }
        .as-cell:last-child { border-right: none; }
        .as-hdr   { font-size: 8px; font-weight: 700; color: #888; text-transform: uppercase; margin-bottom: 1px; }
        .as-name  { font-weight: 700; }
        .items-table { width: 100%; border-collapse: collapse; border-bottom: 1px solid var(--color-border); }
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
            if($pdfSettings['due_date'] ?? true) $metaFields['Due'] = $data['due_date'];
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
        <thead><tr>
            <th style="width:20px" class="text-center">#</th>
            <th class="text-left">Item</th>
            @if($pdfSettings['hsn_code'] ?? true) <th style="width:50px" class="text-right">HSN</th> @endif
            <th style="width:50px" class="text-right">Qty</th>
            @if($pdfSettings['unit'] ?? true) <th style="width:40px" class="text-center">Unit</th> @endif
            <th style="width:70px" class="text-right">{{ $labels['rate'] ?? 'Rate' }}</th>
            <th style="width:75px" class="text-right">Total</th>
        </tr></thead>
        <tbody>
            @foreach($data['items'] as $item)
            <tr>
                <td class="text-center">{{ $item['no'] }}</td>
                <td>
                    <span class="item-name">{{ $item['name'] }}</span>
                    @if(($pdfSettings['description'] ?? true) && $item['description']) <span class="item-sub"> — {{ $item['description'] }}</span>@endif
                </td>
                @if($pdfSettings['hsn_code'] ?? true) <td class="text-right">{{ $item['hsn'] }}</td> @endif
                <td class="text-right">{{ number_format($item['qty'], 2) }}</td>
                @if($pdfSettings['unit'] ?? true) <td class="text-center">{{ $item['unit'] }}</td> @endif
                <td class="text-right">{{ number_format($item['unit_price'], 2) }}</td>
                <td class="text-right bold">{{ $data['meta']['currency_symbol'] ?? '₹' }}{{ number_format($item['total'], 2) }}</td>
            </tr>
            @endforeach
        </tbody>
    </table>

    <div style="padding:4px 8px;text-align:right;">
        <table class="totals-compact">
            <tr><td class="tc-label">Sub Total</td><td class="tc-val">{{ number_format($data['totals']['sub_total'], 2) }}</td></tr>
            @foreach($data['totals']['tax_lines'] as $tl) 
                @php
                    $showTax = true;
                    if($tl['label'] === 'CGST' && !($pdfSettings['cgst'] ?? true)) $showTax = false;
                    if($tl['label'] === 'SGST' && !($pdfSettings['sgst'] ?? true)) $showTax = false;
                    if($tl['label'] === 'IGST' && !($pdfSettings['igst'] ?? true)) $showTax = false;
                @endphp
                @if($showTax) <tr><td class="tc-label">{{ $tl['label'] }}</td><td class="tc-val">{{ number_format($tl['amount'], 2) }}</td></tr> @endif
            @endforeach
            @if(($pdfSettings['shipping'] ?? true) && $data['totals']['shipping'] > 0)
                <tr><td class="tc-label">Shipping</td><td class="tc-val">{{ number_format($data['totals']['shipping'], 2) }}</td></tr>
            @endif
            <tr class="tc-grand"><td class="tc-label" style="color:#fff">Total</td><td class="tc-val" style="color:#fff">{{ $data['meta']['currency_symbol'] ?? '₹' }}{{ number_format($data['totals']['grand_total'], 2) }}</td></tr>
        </table>
    </div>

    @if(($pdfSettings['terms'] ?? true) && ($data['meta']['terms_text'] ?? ''))
    <div style="padding:5px 8px;font-size:8.5px;border-top:1px solid #ccc;color:#666;">{!! nl2br(e($data['meta']['terms_text'])) !!}</div>
    @endif

    @if($pdfSettings['signature'] ?? true)
        <div style="text-align:right;padding:5px 10px;border-top:1px solid #ccc;font-size:9px;color:#aaa;min-height:40px">Authorized Signatory — {{ $data['company']['name'] }}</div>
    @endif

    @include('pdfs.partials._footer')
</div>
</body>
</html>
