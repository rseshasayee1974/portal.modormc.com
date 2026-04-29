<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>{{ $data['doc_title'] }} - {{ $data['doc_no'] }}</title>
    @include('pdfs.partials._common_styles')
    <style>
        .inv-root { border: 1px solid var(--color-border); width: 100%; min-height: 297mm; }

        .inv-header { display: table; width: 100%; border-bottom: 1px solid var(--color-border); padding: 10px 14px; }
        .header-left  { display: table-cell; vertical-align: top; }
        .header-right { display: table-cell; vertical-align: top; text-align: right; }
        .co-name   { font-size: 15px; font-weight: 700; }
        .co-detail { font-size: 10px; color: var(--color-muted); line-height: 1.45; }
        .inv-title { font-size: var(--size-title); font-weight: 900; line-height: 1.1; }
        .inv-ref   { font-size: 10.5px; color: var(--color-muted); margin-top: 2px; }

        /* 2-col details bar */
        .details-table { width: 100%; border-collapse: collapse; border-bottom: 1px solid var(--color-border); }
        .details-cell  { padding: 7px 12px; vertical-align: top; border-right: 1px solid var(--color-border); width: 50%; }
        .no-right { border-right: none; }
        .kv-table { border-collapse: collapse; width: 100%; }
        .kv-key   { color: var(--color-muted); white-space: nowrap; padding: 1px 0; min-width: 100px; }
        .kv-sep   { padding: 1px 6px; color: var(--color-muted); }
        .kv-val   { color: var(--color-ink); }

        /* Bill To / Ship To */
        .addr-table { width: 100%; border-collapse: collapse; border-bottom: 1px solid var(--color-border); }
        .addr-th { background: var(--color-alt-bg); border-bottom: 1px solid var(--color-border); padding: 5px 12px; font-weight: 700; font-size: var(--size-base); text-align: left; width: 50%; }
        .addr-th-left { border-right: 1px solid var(--color-border); }
        .addr-cell { padding: 7px 12px; vertical-align: top; font-size: var(--size-base); }
        .addr-left { border-right: 1px solid var(--color-border); }
        .addr-name { font-weight: 700; }
        .addr-line { color: var(--color-light); line-height: 1.5; }

        /* Subject */
        .subject-row { padding: 4px 12px; border-bottom: 1px solid var(--color-border); font-size: var(--size-base); background: #fafafa; }

        /* Items — dark header */
        .items-table { width: 100%; border-collapse: collapse; border-bottom: 1px solid var(--color-border); }
        .items-table thead tr { background: var(--color-header-bg); color: #fff; }
        .items-table th { padding: 6px 8px; font-weight: 700; font-size: 10px; border: none; }
        .items-table td { padding: 5px 8px; vertical-align: top; border-bottom: 1px solid var(--color-border-light); font-size: var(--size-base); }
        .items-table tbody tr:last-child td { border-bottom: 1px solid #ccc; }

        /* Totals */
        .totals-split { display: table; width: 100%; border-bottom: 1px solid var(--color-border); }
        .totals-left  { display: table-cell; vertical-align: top; padding: 8px 12px; border-right: 1px solid var(--color-border); width: 55%; }
        .totals-right { display: table-cell; vertical-align: top; }
        .breakdown-table { width: 100%; border-collapse: collapse; }
        .breakdown-table td { padding: 3px 10px; }
        .bt-label { text-align: right; color: var(--color-muted); padding-right: 14px !important; width: 58%; }
        .bt-val   { text-align: right; white-space: nowrap; }
        .bt-total-row   { border-top: 1px solid var(--color-border); border-bottom: 1px solid var(--color-border); }
        .bt-balance-row { background: var(--color-balance-bg); }
        .bt-total-row td, .bt-balance-row td { padding-top: 5px !important; padding-bottom: 5px !important; }

        .tow-label { color: var(--color-muted); font-size: 10px; margin-bottom: 2px; }
        .tow-value { font-style: italic; font-weight: 700; font-size: var(--size-base); line-height: 1.5; }
    </style>
</head>
<body>
@include('pdfs.partials._print_actions')
<div class="inv-root">
    {{-- HEADER --}}
    <div class="inv-header">
        <div class="header-left">
            <div class="co-name">{{ $data['company']['name'] }}</div>
            <div class="co-detail">{{ $data['company']['city'] }}, {{ $data['company']['state'] }}</div>
            @if($data['company']['email']) <div class="co-detail">{{ $data['company']['email'] }}</div> @endif
        </div>
        <div class="header-right">
            <div class="inv-title">{{ $data['doc_title'] }}</div>
            <div class="inv-ref">Ref# <strong>{{ $data['doc_no'] }}</strong></div>
        </div>
    </div>

    {{-- 2-COL DETAILS BAR --}}
    <table class="details-table">
        <tr>
            <td class="details-cell">
                <table class="kv-table">
                    @foreach([
                        'Date'     => $data['doc_date'],
                        'Due Date' => $data['due_date'],
                        'Delivery' => $data['delivery_date'],
                        'PO#'      => $data['meta']['po_number'] ?? '',
                    ] as $k => $v)
                        @if($v) <tr><td class="kv-key">{{ $k }}</td><td class="kv-sep">:</td><td class="kv-val bold">{{ $v }}</td></tr> @endif
                    @endforeach
                </table>
            </td>
            <td class="details-cell no-right">
                <table class="kv-table">
                    @if($data['meta']['project_name'] ?? false)
                    <tr><td class="kv-key">Project</td><td class="kv-sep">:</td><td class="kv-val bold">{{ $data['meta']['project_name'] }}</td></tr>
                    @endif
                    <tr><td class="kv-key">Status</td><td class="kv-sep">:</td><td class="kv-val bold">{{ $data['state'] }}</td></tr>
                </table>
            </td>
        </tr>
    </table>

    {{-- BILL TO / SHIP TO --}}
    <table class="addr-table">
        <thead>
            <tr>
                <th class="addr-th addr-th-left">{{ $data['doc_title'] === 'PURCHASE ORDER' ? 'Vendor Details' : 'Bill To' }}</th>
                <th class="addr-th">{{ $data['doc_title'] === 'PURCHASE ORDER' ? 'Delivery Address' : 'Ship To' }}</th>
            </tr>
        </thead>
        <tbody>
            <tr>
                <td class="addr-cell addr-left">
                    <div class="addr-name">{{ $data['bill_to']['name'] }}</div>
                    <div class="addr-line">{{ $data['bill_to']['address'] }}</div>
                    <div class="addr-line">{{ $data['bill_to']['city'] }}, {{ $data['bill_to']['state'] }} {{ $data['bill_to']['pin'] }}</div>
                    @if($data['bill_to']['gstin']) <div class="addr-line small">GSTIN: {{ $data['bill_to']['gstin'] }}</div> @endif
                </td>
                <td class="addr-cell">
                    <div class="addr-name">{{ $data['ship_to']['name'] }}</div>
                    <div class="addr-line">{{ $data['ship_to']['address'] }}</div>
                    <div class="addr-line">{{ $data['ship_to']['city'] }}, {{ $data['ship_to']['state'] }} {{ $data['ship_to']['pin'] }}</div>
                </td>
            </tr>
        </tbody>
    </table>

    {{-- SUBJECT --}}
    <div class="subject-row">&nbsp;&nbsp;Subject : {{ $data['meta']['project_name'] ?? 'Description' }}</div>

    {{-- ITEMS --}}
    <table class="items-table">
        <thead>
            <tr>
                <th class="text-center" style="width:28px">#</th>
                <th class="text-left">Item &amp; Description</th>
                <th class="text-right" style="width:55px">Qty</th>
                @if(collect($data['items'])->where('received_qty', '>', 0)->count() || true)
                <th class="text-right" style="width:55px">Recd</th>
                @endif
                <th class="text-center" style="width:45px">Unit</th>
                <th class="text-right" style="width:80px">Rate</th>
                <th class="text-center" style="width:80px">Tax</th>
                <th class="text-right" style="width:85px">Total</th>
            </tr>
        </thead>
        <tbody>
            @foreach($data['items'] as $item)
            <tr>
                <td class="text-center">{{ $item['no'] }}</td>
                <td>
                    <div class="item-name">{{ $item['name'] }}</div>
                    @if($item['description']) <div class="item-sub">{{ $item['description'] }}</div> @endif
                </td>
                <td class="text-right bold">{{ number_format($item['qty'], 2) }}</td>
                <td class="text-right">
                    @if(($item['received_qty'] ?? 0) > 0)
                        <span class="{{ $item['received_qty'] >= $item['qty'] ? 'badge-done' : 'badge-pending' }}">
                            {{ number_format($item['received_qty'], 2) }}
                        </span>
                    @else - @endif
                </td>
                <td class="text-center">{{ $item['unit'] }}</td>
                <td class="text-right">{{ number_format($item['unit_price'], 2) }}</td>
                <td class="text-center small">
                    @if($item['tax_group'] === 'GST')
                        {{ $item['tax_rate'] / 2 }}% CGST<br>{{ $item['tax_rate'] / 2 }}% SGST
                    @elseif($item['tax_name'] && $item['tax_name'] !== '-')
                        {{ $item['tax_name'] }} {{ $item['tax_rate'] }}%
                    @else -
                    @endif
                </td>
                <td class="text-right bold">{{ $data['meta']['currency_symbol'] ?? '₹' }}{{ number_format($item['total'], 2) }}</td>
            </tr>
            @endforeach
        </tbody>
    </table>

    {{-- TOTALS --}}
    <div class="totals-split">
        <div class="totals-left">
            <div class="tow-label">Total in Words</div>
            <div class="tow-value">{{ $data['meta']['total_words'] ?: ($data['meta']['currency_code'] ?? 'INR') . ' ' . number_format($data['totals']['grand_total'], 2) . ' Only' }}</div>
            @if($data['meta']['notes'] ?? false)
            <div class="small muted" style="margin-top:8px;">Notes</div>
            <div style="font-size:var(--size-base)">{{ $data['meta']['notes'] }}</div>
            @endif
        </div>
        <div class="totals-right">
            <table class="breakdown-table">
                <tr><td class="bt-label">Sub Total</td><td class="bt-val">{{ $data['meta']['currency_symbol'] ?? '₹' }}{{ number_format($data['totals']['sub_total'], 2) }}</td></tr>
                @if($data['totals']['discount'] > 0) <tr><td class="bt-label red">Discount (-)</td><td class="bt-val red">{{ number_format($data['totals']['discount'], 2) }}</td></tr> @endif
                @foreach($data['totals']['tax_lines'] as $tl) <tr><td class="bt-label">{{ $tl['label'] }}</td><td class="bt-val">{{ number_format($tl['amount'], 2) }}</td></tr> @endforeach
                @if($data['totals']['shipping'] > 0) <tr><td class="bt-label">Freight (+)</td><td class="bt-val">{{ number_format($data['totals']['shipping'], 2) }}</td></tr> @endif
                <tr class="bt-total-row"><td class="bt-label bold">Total ({{ $data['meta']['currency_code'] ?? 'INR' }})</td><td class="bt-val bold">{{ $data['meta']['currency_symbol'] ?? '₹' }}{{ number_format($data['totals']['grand_total'], 2) }}</td></tr>
                <tr class="bt-balance-row"><td class="bt-label bold">Net Payable</td><td class="bt-val bold">{{ $data['meta']['currency_symbol'] ?? '₹' }}{{ number_format($data['totals']['grand_total'], 2) }}</td></tr>
            </table>
        </div>
    </div>

    {{-- TERMS --}}
    @if($data['meta']['terms_text'] ?? false)
    <div style="padding:7px 12px;border-bottom:1px solid var(--color-border);">
        <div class="small muted">Terms &amp; Conditions</div>
        <div style="font-size:10px;color:var(--color-light);margin-top:2px">{!! nl2br(e($data['meta']['terms_text'])) !!}</div>
    </div>
    @endif

    {{-- SIGNATURE --}}
    <div style="min-height:90px;padding:10px 12px;border-bottom:1px solid var(--color-border);position:relative;text-align:right;">
        <div style="margin-top:60px;">
            <span style="display:inline-block;width:160px;border-top:1px solid #999;padding-top:4px;text-align:center;font-size:10.5px;color:var(--color-muted)">
                Authorized Signatory<br><span style="font-size:9px">For {{ $data['company']['name'] }}</span>
            </span>
        </div>
    </div>

    @include('pdfs.partials._footer')
</div>
</body>
</html>
