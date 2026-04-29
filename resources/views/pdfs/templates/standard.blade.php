<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>{{ $data['doc_title'] }} - {{ $data['doc_no'] }}</title>
    @include('pdfs.partials._common_styles')
    <style>
        .inv-root { border: 1px solid var(--color-border); width: 100%; min-height: 297mm; display: flex; flex-direction: column; }

        /* HEADER */
        .inv-header { display: table; width: 100%; border-bottom: 1px solid var(--color-border); padding: 10px 14px; }
        .header-left  { display: table-cell; vertical-align: top; }
        .header-right { display: table-cell; vertical-align: top; text-align: right; }
        .co-name   { font-size: 15px; font-weight: 700; }
        .co-detail { font-size: 10px; color: var(--color-muted); line-height: 1.45; }
        .inv-title { font-size: var(--size-title); font-weight: 900; line-height: 1.1; }
        .inv-ref   { font-size: 10.5px; color: var(--color-muted); margin-top: 2px; }

        /* INFO GRID (3 columns) */
        .info-table { width: 100%; border-collapse: collapse; border-bottom: 1px solid var(--color-border); }
        .info-cell  { padding: 7px 12px; vertical-align: top; border-right: 1px solid var(--color-border); font-size: var(--size-base); }
        .no-right { border-right: none; }

        .kv-table { border-collapse: collapse; width: 100%; }
        .kv-key   { color: var(--color-muted); white-space: nowrap; padding: 1px 0; min-width: 80px; }
        .kv-sep   { padding: 1px 5px; color: var(--color-muted); }
        .kv-val   { color: var(--color-ink); }

        .addr-hdr  { font-size: 9px; font-weight: 700; color: #4f46e5; text-transform: uppercase; letter-spacing: 0.15em; margin-bottom: 3px; }
        .addr-name { font-weight: 700; }
        .addr-line { color: var(--color-light); line-height: 1.5; }

        /* SUBJECT */
        .subject-row { padding: 4px 12px; border-bottom: 1px solid var(--color-border); background: #fafafa; font-size: var(--size-base); }

        /* ITEMS TABLE */
        .items-table { width: 100%; border-collapse: collapse; border-bottom: 1px solid var(--color-border); }
        .items-table thead tr { background: #fff; }
        .items-table th { border-top: 1.5px solid var(--color-ink); border-bottom: 1.5px solid var(--color-ink); padding: 5px 8px; font-size: 10px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.12em; }
        .items-table td { padding: 6px 8px; vertical-align: top; border-bottom: 1px solid var(--color-border-light); font-size: var(--size-base); }

        /* TOTALS + FOOTER BLOCK */
        .totals-split { display: table; width: 100%; border-bottom: 1px solid var(--color-border); }
        .totals-left  { display: table-cell; vertical-align: top; padding: 8px 12px; border-right: 1px solid var(--color-border); font-size: var(--size-base); width: 55%; }
        .totals-right { display: table-cell; vertical-align: top; }
        .breakdown-table { width: 100%; border-collapse: collapse; }
        .breakdown-table td { padding: 3px 10px; vertical-align: middle; }
        .bt-label { text-align: right; color: var(--color-muted); padding-right: 14px !important; width: 58%; font-size: var(--size-base); }
        .bt-val   { text-align: right; white-space: nowrap; font-size: var(--size-base); }
        .bt-total-row   { border-top: 1px solid var(--color-border); border-bottom: 1px solid var(--color-border); }
        .bt-balance-row { background: var(--color-balance-bg); }
        .bt-total-row td, .bt-balance-row td { padding-top: 5px !important; padding-bottom: 5px !important; }

        .tow-label { color: var(--color-muted); font-size: 10px; margin-bottom: 2px; }
        .tow-value { font-style: italic; font-weight: 700; font-size: var(--size-base); line-height: 1.5; }

        .sig-section { display: table; width: 100%; padding: 8px 12px; border-bottom: 1px solid var(--color-border); }
        .sig-left    { display: table-cell; vertical-align: bottom; font-size: var(--size-base); width: 60%; }
        .sig-right   { display: table-cell; vertical-align: bottom; text-align: right; width: 40%; }
        .sig-line    { display: inline-block; width: 160px; border-top: 1px solid #999; padding-top: 4px; text-align: center; font-size: 10.5px; color: var(--color-muted); }
    </style>
</head>
<body>
@include('pdfs.partials._print_actions')
<div class="inv-root">

    {{-- HEADER --}}
    <div class="inv-header">
        <div class="header-left">
            <div class="co-name">{{ $data['company']['name'] }}</div>
            <div class="co-detail">{{ $data['company']['address'] }}</div>
            <div class="co-detail">{{ $data['company']['city'] }}, {{ $data['company']['state'] }} - {{ $data['company']['pin'] }}</div>
            @if($data['company']['gstin']) <div class="co-detail">GSTIN: {{ $data['company']['gstin'] }}</div> @endif
        </div>
        <div class="header-right">
            <div class="inv-title">{{ $data['doc_title'] }}</div>
            <div class="inv-ref">{{ str_contains($data['doc_title'], 'INVOICE') ? 'Invoice#' : ($data['doc_title'] === 'PURCHASE ORDER' ? 'PO#' : 'Ref#') }} <strong>{{ $data['doc_no'] }}</strong></div>
        </div>
    </div>

    {{-- INFO GRID (3 col: details | bill_to | ship_to) --}}
    <table class="info-table">
        <tr>
            <td class="info-cell" style="width:33%">
                <table class="kv-table">
                    @foreach([
                        'Date'     => $data['doc_date'],
                        'Due Date' => $data['due_date'],
                        'Delivery' => $data['delivery_date'],
                        'PO#'      => $data['meta']['po_number'] ?? '',
                        'Status'   => $data['state'],
                    ] as $label => $val)
                        @if($val)
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
                <div class="addr-hdr">Bill To</div>
                <div class="addr-name">{{ $data['bill_to']['name'] }}</div>
                <div class="addr-line">{{ $data['bill_to']['address'] }}</div>
                <div class="addr-line">{{ $data['bill_to']['city'] }}, {{ $data['bill_to']['state'] }} {{ $data['bill_to']['pin'] }}</div>
                @if($data['bill_to']['gstin']) <div class="addr-line small">GSTIN: {{ $data['bill_to']['gstin'] }}</div> @endif
            </td>
            <td class="info-cell no-right" style="width:33%">
                <div class="addr-hdr">Ship To</div>
                <div class="addr-name">{{ $data['ship_to']['name'] }}</div>
                <div class="addr-line">{{ $data['ship_to']['address'] }}</div>
                <div class="addr-line">{{ $data['ship_to']['city'] }}, {{ $data['ship_to']['state'] }} {{ $data['ship_to']['pin'] }}</div>
            </td>
        </tr>
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
                <th class="text-center" style="width:50px">Unit</th>
                <th class="text-right" style="width:80px">Rate</th>
                <th class="text-right" style="width:80px">Amount</th>
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
                <td class="text-center">{{ $item['unit'] }}</td>
                <td class="text-right">{{ number_format($item['unit_price'], 2) }}</td>
                <td class="text-right bold">{{ $data['meta']['currency_symbol'] ?? '₹' }}{{ number_format($item['total'], 2) }}</td>
            </tr>
            @endforeach
        </tbody>
    </table>

    {{-- TOTALS SPLIT --}}
    <div class="totals-split">
        <div class="totals-left">
            @if($data['meta']['notes'] ?? false)
                <div class="small muted" style="margin-bottom:6px">Notes</div>
                <div style="margin-bottom:8px;font-size:var(--size-base)">{{ $data['meta']['notes'] }}</div>
            @endif
            <div class="tow-label">Total In Words</div>
            <div class="tow-value">{{ $data['meta']['total_words'] ?: ($data['meta']['currency_code'] ?? 'INR') . ' ' . number_format($data['totals']['grand_total'], 2) . ' Only' }}</div>
        </div>
        <div class="totals-right">
            <table class="breakdown-table">
                <tr><td class="bt-label">Sub Total</td><td class="bt-val">{{ $data['meta']['currency_symbol'] ?? '₹' }}{{ number_format($data['totals']['sub_total'], 2) }}</td></tr>
                @if($data['totals']['discount'] > 0)
                <tr><td class="bt-label red">Discount (-)</td><td class="bt-val red">{{ $data['meta']['currency_symbol'] ?? '₹' }}{{ number_format($data['totals']['discount'], 2) }}</td></tr>
                @endif
                @foreach($data['totals']['tax_lines'] as $tl)
                <tr><td class="bt-label">{{ $tl['label'] }}</td><td class="bt-val">{{ $data['meta']['currency_symbol'] ?? '₹' }}{{ number_format($tl['amount'], 2) }}</td></tr>
                @endforeach
                @if($data['totals']['shipping'] > 0)
                <tr><td class="bt-label">Shipping</td><td class="bt-val">{{ $data['meta']['currency_symbol'] ?? '₹' }}{{ number_format($data['totals']['shipping'], 2) }}</td></tr>
                @endif
                <tr class="bt-total-row"><td class="bt-label bold">Total</td><td class="bt-val bold">{{ $data['meta']['currency_symbol'] ?? '₹' }}{{ number_format($data['totals']['grand_total'], 2) }}</td></tr>
                <tr class="bt-balance-row"><td class="bt-label bold">Balance Due</td><td class="bt-val bold">{{ $data['meta']['currency_symbol'] ?? '₹' }}{{ number_format($data['totals']['grand_total'], 2) }}</td></tr>
            </table>
        </div>
    </div>

    {{-- TERMS --}}
    @if($data['meta']['terms_text'] ?? false)
    <div style="padding:7px 12px;border-bottom:1px solid var(--color-border);font-size:var(--size-base)">
        <div class="small muted" style="margin-bottom:2px">Terms &amp; Conditions</div>
        <div style="font-size:10px;color:var(--color-light)">{!! nl2br(e($data['meta']['terms_text'])) !!}</div>
    </div>
    @endif

    {{-- SIGNATURE --}}
    <div class="sig-section" style="min-height:80px">
        <div class="sig-left"></div>
        <div class="sig-right" style="padding-bottom:10px">
            <div class="sig-line">Authorized Signatory<br><span class="small muted">For {{ $data['company']['name'] }}</span></div>
        </div>
    </div>

    @include('pdfs.partials._footer')
</div>
</body>
</html>
