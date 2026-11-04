<!--
Author: Cipher
Created: 2026-11-04 15:00:20 Asia/Calcutta (UTC+05:30)
-->
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>{{ $data['doc_title'] }} · {{ $data['doc_no'] }}</title>
    <style>
        @page { margin: 18mm 12mm; }
        body { font-family: DejaVu Sans, sans-serif; font-size: 10px; color: #172033; margin: 0; }
        .document { max-width: 190mm; margin: auto; }
        h1 { font-size: 21px; margin: 14px 0 10px; }
        h2 { font-size: 11px; margin: 0 0 6px; }
        table { width: 100%; border-collapse: collapse; }
        td, th { padding: 7px; vertical-align: top; }
        .details td { width: 50%; border: 1px solid #d8dee8; line-height: 1.7; }
        .items { margin-top: 18px; table-layout: fixed; }
        .items th { background: #eef2f7; font-size: 9px; text-align: left; }
        .items td, .items th { border: 1px solid #d8dee8; overflow-wrap: anywhere; }
        thead { display: table-header-group; }
        tr { page-break-inside: avoid; }
        .amount { text-align: right !important; }
        .muted { color: #64748b; font-size: 9px; }
        .summary { margin-top: 14px; }
        .summary td { line-height: 1.5; }
        .total { font-size: 12px; font-weight: bold; border-top: 1px solid #d8dee8; }
        .reason { white-space: pre-line; word-wrap: break-word; }
        .signature { margin-top: 32px; text-align: right; page-break-inside: avoid; }
        @if(empty($is_pdf))
        @media screen { body { background: #f1f5f9; padding: 24px; } .document { background: white; padding: 24px; } }
        @endif
    </style>
</head>
<body>
<main class="document">
    @include('pdfs.partials._brand_header')
    <h1>{{ $data['doc_title'] }}</h1>
    <table class="details">
        <tr><td><strong>Note No:</strong> {{ $data['doc_no'] }}<br><strong>Date:</strong> {{ $data['doc_date'] }}<br><strong>Status:</strong> {{ $data['state'] }}</td>
            <td><strong>Original {{ $data['source_document']['type'] }}:</strong> {{ $data['source_document']['number'] }}<br><strong>Original Date:</strong> {{ $data['source_document']['date'] }}</td></tr>
        <tr><td><h2>Party Details</h2><strong>{{ $data['bill_to']['name'] }}</strong><br>
            {{ $data['bill_to']['address'] }} {{ $data['bill_to']['city'] }} {{ $data['bill_to']['pin'] }}<br>
            @if(!empty($data['bill_to']['state']))State: {{ $data['bill_to']['state'] }}<br>@endif
            @if(!empty($data['bill_to']['gstin']))GSTIN: {{ $data['bill_to']['gstin'] }}<br>@endif
            @if(!empty($data['bill_to']['phone']))Phone: {{ $data['bill_to']['phone'] }}@endif</td>
            <td><h2>Reason / Remarks</h2><div class="reason">{{ $data['meta']['notes'] ?: '-' }}</div></td></tr>
    </table>
    <table class="items">
        <thead><tr><th style="width:4%">#</th><th style="width:24%">Description / HSN</th><th style="width:10%" class="amount">Quantity</th><th style="width:11%" class="amount">Rate</th><th style="width:10%" class="amount">Discount</th><th style="width:13%" class="amount">Taxable</th><th style="width:13%" class="amount">Tax</th><th style="width:15%" class="amount">Total</th></tr></thead>
        <tbody>@forelse($data['items'] as $item)
            <tr><td>{{ $item['no'] }}</td><td>{{ $item['name'] }}<br><span class="muted">HSN: {{ $item['hsn'] }}</span></td>
                <td class="amount">{{ rtrim(rtrim(number_format($item['qty'], 4, '.', ''), '0'), '.') }}<br>{{ $item['unit'] }}</td>
                @foreach(['unit_price', 'discount', 'taxable_amount'] as $key)<td class="amount">{{ number_format($item[$key], 2) }}</td>@endforeach
                <td class="amount">{{ number_format($item['tax_amount'], 2) }}<br><span class="muted">{{ $item['tax_label'] }}</span></td>
                <td class="amount">{{ number_format($item['total'], 2) }}</td></tr>
        @empty<tr><td colspan="8">No line items.</td></tr>@endforelse</tbody>
    </table>
    <table class="summary"><tr><td style="width:52%"><strong>Amount in words</strong><br>{{ $data['meta']['total_words'] }}<br><span class="muted">All amounts in INR.</span></td>
        <td><table>
            <tr><td>Taxable Amount</td><td class="amount">{{ number_format($data['totals']['sub_total'], 2) }}</td></tr>
            @if($data['totals']['discount'] != 0)<tr><td>Document Discount</td><td class="amount">{{ number_format($data['totals']['discount'], 2) }}</td></tr>@endif
            @foreach($data['totals']['tax_lines'] as $tax)<tr><td>{{ $tax['label'] }}</td><td class="amount">{{ number_format($tax['amount'], 2) }}</td></tr>@endforeach
            <tr><td>Total Tax</td><td class="amount">{{ number_format($data['totals']['tax_amount'], 2) }}</td></tr>
            @foreach(['shipping' => 'Shipping Charges', 'adjustment' => 'Adjustment', 'round_off' => 'Round Off'] as $key => $label)
                @if($data['totals'][$key] != 0)<tr><td>{{ $label }}</td><td class="amount">{{ number_format($data['totals'][$key], 2) }}</td></tr>@endif
            @endforeach
            <tr class="total"><td>Note Total</td><td class="amount">INR {{ number_format($data['totals']['grand_total'], 2) }}</td></tr>
        </table></td></tr></table>
    <div class="signature">For {{ $data['company']['name'] }}<br><br><br>Authorised Signatory</div>
</main>
@include('pdfs.partials._print_actions')
</body>
</html>
