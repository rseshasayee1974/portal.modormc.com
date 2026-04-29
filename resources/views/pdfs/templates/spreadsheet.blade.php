<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>{{ $data['doc_title'] }} - {{ $data['doc_no'] }}</title>
    @include('pdfs.partials._common_styles')
    <style>
        .inv-root { border: 4px solid #111; width: 100%; min-height: 297mm; }
        .title-bar { display: table; width: 100%; border-bottom: 2px solid #111; background: #111; color: #fff; padding: 10px 14px; }
        .title-left  { display: table-cell; vertical-align: middle; font-size: 14px; font-weight: 700; }
        .title-right { display: table-cell; vertical-align: middle; text-align: right; font-size: 22px; font-weight: 900; text-transform: uppercase; letter-spacing: 0.03em; }
        .doc-ref     { display: table; width: 100%; padding: 5px 14px; background: #f5f5f5; border-bottom: 1px solid #ccc; }
        .dr-cell     { display: table-cell; padding: 3px 8px; font-size: 10px; border-right: 1px solid #ccc; }
        .dr-cell:last-child { border-right: none; }
        .dr-key      { color: #888; display: block; font-size: 9px; text-transform: uppercase; }
        .dr-val      { font-weight: 700; font-size: 11px; }
        .gr-table    { width: 100%; border-collapse: collapse; border-bottom: 1px solid #ccc; }
        .gr-cell     { padding: 8px 12px; vertical-align: top; border-right: 1px solid #ccc; font-size: 11px; width: 50%; }
        .gr-cell:last-child { border-right: none; }
        .gr-hdr      { font-size: 9px; font-weight: 700; color: #666; text-transform: uppercase; letter-spacing: 0.1em; margin-bottom: 3px; }
        .gr-name     { font-weight: 700; font-size: 12px; }
        .items-table { width: 100%; border-collapse: collapse; border-bottom: 1px solid #ccc; }
        .items-table th { background: #222; color: #fff; padding: 6px 8px; font-size: 10px; font-weight: 700; border: 1px solid #333; }
        .items-table td { border: 1px solid #ddd; padding: 5px 8px; font-size: 11px; vertical-align: top; }
        .items-table tr:nth-child(even) td { background: #fafafa; }
        .totals-right { display: block; text-align: right; }
        .bt-table  { width: 280px; margin-left: auto; border-collapse: collapse; }
        .bt-table td { padding: 3px 8px; font-size: 11px; }
        .btt-lbl { text-align: right; color: #555; width: 55%; padding-right: 12px !important; border-bottom: 1px solid #eee; }
        .btt-val { text-align: right; border-bottom: 1px solid #eee; }
        .btt-grand td { font-weight: 700; background: #111; color: #fff; padding: 5px 8px !important; }
    </style>
</head>
<body>
@include('pdfs.partials._print_actions')
<div class="inv-root">
    <div class="title-bar">
        <div class="title-left">{{ $data['company']['name'] }}<br><span style="font-size:10px;color:#ccc">{{ $data['company']['city'] }}, {{ $data['company']['state'] }}</span></div>
        <div class="title-right">{{ $data['doc_title'] }}<br><span style="font-size:11px;font-weight:400;opacity:0.7">{{ $data['doc_no'] }}</span></div>
    </div>
    <div class="doc-ref">
        @foreach(['Date' => $data['doc_date'], 'Delivery' => $data['delivery_date'], 'Due Date' => $data['due_date'], 'PO#' => ($data['meta']['po_number'] ?? ''), 'Status' => $data['state']] as $k=>$v)
            @if($v) <div class="dr-cell"><span class="dr-key">{{ $k }}</span><span class="dr-val">{{ $v }}</span></div> @endif
        @endforeach
    </div>
    <table class="gr-table">
        <tr>
            <td class="gr-cell"><div class="gr-hdr">{{ $data['doc_title'] === 'PURCHASE ORDER' ? 'Vendor' : 'Bill To' }}</div><div class="gr-name">{{ $data['bill_to']['name'] }}</div><div>{{ $data['bill_to']['address'] }}, {{ $data['bill_to']['city'] }}</div>@if($data['bill_to']['gstin'])<div class="small">GSTIN: {{ $data['bill_to']['gstin'] }}</div>@endif</td>
            <td class="gr-cell"><div class="gr-hdr">Delivery Address</div><div class="gr-name">{{ $data['ship_to']['name'] }}</div><div>{{ $data['ship_to']['address'] }}, {{ $data['ship_to']['city'] }}</div></td>
        </tr>
    </table>
    <table class="items-table">
        <thead><tr>
            <th class="text-center" style="width:28px">#</th>
            <th class="text-left">Product &amp; Description</th>
            <th class="text-center" style="width:55px">Qty</th>
            <th class="text-center" style="width:45px">Unit</th>
            <th class="text-right" style="width:80px">Rate</th>
            <th class="text-right" style="width:85px">Total</th>
        </tr></thead>
        <tbody>
            @foreach($data['items'] as $item)
            <tr>
                <td class="text-center">{{ $item['no'] }}</td>
                <td><div class="item-name">{{ $item['name'] }}</div>@if($item['description'])<div class="item-sub">{{ $item['description'] }}</div>@endif</td>
                <td class="text-right">{{ number_format($item['qty'], 2) }}<br><span class="small muted">{{ $item['unit'] }}</span></td>
                <td class="text-center">{{ $item['unit'] }}</td>
                <td class="text-right">{{ number_format($item['unit_price'], 2) }}</td>
                <td class="text-right bold">{{ $data['meta']['currency_symbol'] ?? '₹' }}{{ number_format($item['total'], 2) }}</td>
            </tr>
            @endforeach
        </tbody>
    </table>
    <div style="padding:8px 12px;">
        <table class="bt-table">
            <tr><td class="btt-lbl">Sub Total</td><td class="btt-val">{{ number_format($data['totals']['sub_total'], 2) }}</td></tr>
            @foreach($data['totals']['tax_lines'] as $tl) <tr><td class="btt-lbl">{{ $tl['label'] }}</td><td class="btt-val">{{ number_format($tl['amount'], 2) }}</td></tr> @endforeach
            @if($data['totals']['shipping'] > 0) <tr><td class="btt-lbl">Shipping</td><td class="btt-val">{{ number_format($data['totals']['shipping'], 2) }}</td></tr> @endif
            <tr class="btt-grand"><td class="btt-lbl" style="color:#fff">Total Payable</td><td class="btt-val" style="color:#fff">{{ $data['meta']['currency_symbol'] ?? '₹' }}{{ number_format($data['totals']['grand_total'], 2) }}</td></tr>
        </table>
    </div>
    @if($data['meta']['terms_text'] ?? false)
    <div style="padding:8px 12px;font-size:10px;border-top:1px solid #ccc;"><strong>Terms:</strong> {!! nl2br(e($data['meta']['terms_text'])) !!}</div>
    @endif
    <div style="text-align:right;padding:8px 14px;border-top:1px solid #ccc;font-size:10px;min-height:60px;color:#aaa">Authorized Signatory — {{ $data['company']['name'] }}</div>
    @include('pdfs.partials._footer')
</div>
</body>
</html>
