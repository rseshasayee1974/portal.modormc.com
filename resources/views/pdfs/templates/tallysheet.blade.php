<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>{{ $data['doc_title'] }} - {{ $data['doc_no'] }}</title>
    @include('pdfs.partials._common_styles')
    <style>
        .inv-root { border: 1px solid var(--color-border); width: 100%; min-height: 297mm; }
        .ledger-header { display: table; width: 100%; border-bottom: 2px solid #111; padding: 8px 12px; }
        .lh-left  { display: table-cell; vertical-align: bottom; }
        .lh-right { display: table-cell; vertical-align: bottom; text-align: right; }
        .co-name  { font-size: 14px; font-weight: 700; }
        .co-det   { font-size: 9.5px; color: var(--color-muted); }
        .doc-badge { display: inline-block; background: #111; color: #fff; font-size: 12px; font-weight: 700; padding: 4px 16px; }
        .doc-no    { font-size: 10px; color: var(--color-muted); }

        .meta-bar { display: table; width: 100%; border-bottom: 1px solid var(--color-border); background: #f7f7f7; }
        .mb-cell  { display: table-cell; padding: 5px 10px; border-right: 1px solid var(--color-border); font-size: 10px; vertical-align: top; }
        .mb-cell:last-child { border-right: none; }
        .mb-key   { font-size: 8.5px; color: #888; text-transform: uppercase; display: block; margin-bottom: 1px; }
        .mb-val   { font-weight: 700; font-size: 10.5px; }

        /* Ledger items table */
        .ledger-table { width: 100%; border-collapse: collapse; border-bottom: 1px solid var(--color-border); }
        .ledger-table th { background: #111; color: #fff; padding: 5px 8px; font-size: 9.5px; border: 1px solid #333; }
        .ledger-table td { border: 1px solid #ddd; padding: 4px 8px; font-size: 10.5px; vertical-align: middle; }
        .ledger-table .row-sub { background: #fafafa; }

        .totals-ledger { border: 1px solid var(--color-border); margin: 0; }
        .tl-row { display: table; width: 100%; border-bottom: 1px solid var(--color-border-light); }
        .tl-label { display: table-cell; width: 78%; text-align: right; padding: 4px 14px; font-size: 10.5px; border-right: 1px solid var(--color-border); }
        .tl-val   { display: table-cell; width: 22%; text-align: right; padding: 4px 12px; font-size: 10.5px; }
        .tl-final { background: #111; color: #fff; }
        .tl-final .tl-label, .tl-final .tl-val { font-weight: 700; font-size: 12px; }
        .sig-row { display: table; width: 100%; padding: 8px 12px; min-height: 80px; }
        .sig-left  { display: table-cell; vertical-align: bottom; font-size: 10px; color: var(--color-muted); width: 60%; }
        .sig-right { display: table-cell; vertical-align: bottom; text-align: right; }
        .sig-line  { display: inline-block; width: 160px; border-top: 1px solid #999; padding-top: 4px; font-size: 10px; color: var(--color-muted); text-align: center; }
    </style>
</head>
<body>
@include('pdfs.partials._print_actions')
<div class="inv-root">
    <div class="ledger-header">
        <div class="lh-left">
            <div class="co-name">{{ $data['company']['name'] }}</div>
            <div class="co-det">{{ $data['company']['address'] }}, {{ $data['company']['city'] }} | GSTIN: {{ $data['company']['gstin'] ?? 'N/A' }}</div>
        </div>
        <div class="lh-right">
            <div class="doc-badge">{{ $data['doc_title'] }}</div>
            <div class="doc-no">{{ $data['doc_no'] }}</div>
        </div>
    </div>

    <div class="meta-bar">
        @foreach(['Date'=>$data['doc_date'], 'Due Date'=>$data['due_date'], 'Delivery'=>$data['delivery_date'], 'Party'=>$data['bill_to']['name'], 'Project'=>($data['meta']['project_name']??'')] as $k=>$v)
        @if($v) <div class="mb-cell"><span class="mb-key">{{ $k }}</span><span class="mb-val">{{ $v }}</span></div> @endif
        @endforeach
    </div>

    <table class="ledger-table">
        <thead>
            <tr>
                <th class="text-center" style="width:28px">#</th>
                <th class="text-left">Description</th>
                <th class="text-center" style="width:55px">HSN</th>
                <th class="text-right" style="width:55px">Qty</th>
                <th class="text-center" style="width:45px">Unit</th>
                <th class="text-right" style="width:80px">Rate</th>
                <th class="text-center" style="width:75px">Tax</th>
                <th class="text-right" style="width:85px">Net Total</th>
            </tr>
        </thead>
        <tbody>
            @foreach($data['items'] as $item)
            <tr>
                <td class="text-center">{{ $item['no'] }}</td>
                <td><div class="item-name">{{ $item['name'] }}</div>@if($item['description'])<div class="item-sub">{{ $item['description'] }}</div>@endif</td>
                <td class="text-center">{{ $item['hsn'] }}</td>
                <td class="text-right bold">{{ number_format($item['qty'], 2) }}</td>
                <td class="text-center">{{ $item['unit'] }}</td>
                <td class="text-right">{{ number_format($item['unit_price'], 2) }}</td>
                <td class="text-center small">
                    @if($item['tax_group']==='GST') {{ $item['tax_rate']/2 }}% C+S @else {{ $item['tax_name'] }} @endif
                </td>
                <td class="text-right bold">{{ $data['meta']['currency_symbol'] ?? '₹' }}{{ number_format($item['total'], 2) }}</td>
            </tr>
            @endforeach
        </tbody>
    </table>

    <div class="totals-ledger">
        <div class="tl-row"><div class="tl-label">Subtotal</div><div class="tl-val">{{ number_format($data['totals']['sub_total'], 2) }}</div></div>
        @foreach($data['totals']['tax_lines'] as $tl)
        <div class="tl-row"><div class="tl-label">{{ $tl['label'] }}</div><div class="tl-val">{{ number_format($tl['amount'], 2) }}</div></div>
        @endforeach
        @if($data['totals']['shipping'] > 0)
        <div class="tl-row"><div class="tl-label">Freight</div><div class="tl-val">{{ number_format($data['totals']['shipping'], 2) }}</div></div>
        @endif
        @if($data['totals']['discount'] > 0)
        <div class="tl-row red"><div class="tl-label">Discount (-)</div><div class="tl-val">{{ number_format($data['totals']['discount'], 2) }}</div></div>
        @endif
        <div class="tl-row tl-final"><div class="tl-label">TOTAL PAYABLE ({{ $data['meta']['currency_code'] ?? 'INR' }})</div><div class="tl-val">{{ $data['meta']['currency_symbol'] ?? '₹' }}{{ number_format($data['totals']['grand_total'], 2) }}</div></div>
    </div>

    @if($data['meta']['terms_text'] ?? '')
    <div style="padding:8px 12px;font-size:10px;border-top:1px solid #ccc;"><strong>Terms &amp; Conditions:</strong> {!! nl2br(e($data['meta']['terms_text'])) !!}</div>
    @endif

    <div class="sig-row" style="margin-top:auto">
        <div class="sig-left"></div>
        <div class="sig-right" style="padding-bottom:10px"><div class="sig-line">Authorized Signatory<br><span style="font-size:9px">For {{ $data['company']['name'] }}</span></div></div>
    </div>

    @include('pdfs.partials._footer')
</div>
</body>
</html>
