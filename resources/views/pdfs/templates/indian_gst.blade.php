<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>{{ $data['doc_title'] }} - {{ $data['doc_no'] }}</title>
    @include('pdfs.partials._common_styles')
    <style>
        .inv-root { border: 1px solid var(--color-border); width: 100%; min-height: 297mm; }
        .gst-header { display: table; width: 100%; border-bottom: 2px solid #111; padding: 8px 12px; }
        .gh-left  { display: table-cell; vertical-align: top; }
        .gh-right { display: table-cell; vertical-align: top; text-align: center; }
        .gh-brand { display: table-cell; vertical-align: top; text-align: right; }
        .co-name  { font-size: 13px; font-weight: 700; }
        .co-det   { font-size: 10px; color: var(--color-muted); line-height: 1.4; }
        .gst-title { font-size: 16px; font-weight: 900; text-transform: uppercase; border-bottom: 1px solid #ccc; padding-bottom: 2px; margin-bottom: 2px; }
        .gst-orig  { font-size: 9px; color: #888; }

        .gst-meta { display: table; width: 100%; border-collapse: collapse; border-bottom: 1px solid var(--color-border); }
        .gmt-cell { display: table-cell; border-right: 1px solid var(--color-border); padding: 4px 8px; font-size: 10px; vertical-align: top; }
        .gmt-cell:last-child { border-right: none; }
        .gmt-key  { color: #888; font-size: 9px; }
        .gmt-val  { font-weight: 700; }

        .party-row { display: table; width: 100%; border-bottom: 1px solid var(--color-border); }
        .pr-cell   { display: table-cell; padding: 6px 10px; width: 50%; border-right: 1px solid var(--color-border); font-size: 10.5px; vertical-align: top; }
        .pr-cell:last-child { border-right: none; }
        .pr-hdr    { font-size: 9px; font-weight: 700; text-transform: uppercase; color: #666; margin-bottom: 2px; }
        .pr-name   { font-weight: 700; font-size: 11px; }

        .items-table { width: 100%; border-collapse: collapse; border-bottom: 1px solid var(--color-border); }
        .items-table th { background: #222; color: #fff; padding: 4px 5px; font-size: 9px; text-align: center; border: 1px solid #444; }
        .items-table td { border: 1px solid #ddd; padding: 4px 5px; font-size: 10px; vertical-align: middle; }

        .gst-totals { display: table; width: 100%; border-bottom: 1px solid var(--color-border); }
        .gt-left  { display: table-cell; width: 50%; border-right: 1px solid var(--color-border); padding: 8px 10px; vertical-align: top; }
        .gt-right { display: table-cell; width: 50%; padding: 0; vertical-align: top; }
        .gst-summary { width: 100%; border-collapse: collapse; font-size: 10.5px; }
        .gst-summary th { background: #f0f0f0; padding: 3px 8px; font-size: 9px; border: 1px solid var(--color-border); }
        .gst-summary td { padding: 3px 8px; border: 1px solid #ddd; }
        .total-payable { background: #111; color: #fff; font-weight: 700; font-size: 12px; padding: 6px 10px; text-align: right; }

        .declaration { font-size: 9px; color: #555; font-style: italic; padding: 6px 10px; border-bottom: 1px solid var(--color-border); }
        .sig-row  { display: table; width: 100%; padding: 8px 10px; min-height: 70px; }
        .sig-left  { display: table-cell; vertical-align: bottom; font-size: 10px; }
        .sig-right { display: table-cell; vertical-align: bottom; text-align: right; }
        .sig-line  { display: inline-block; width: 160px; border-top: 1px solid #999; padding-top: 4px; font-size: 10px; color: var(--color-muted); text-align: center; }
    </style>
</head>
<body>
@include('pdfs.partials._print_actions')
<div class="inv-root">

    {{-- GST HEADER (3-col: company | doc title | brand) --}}
    <div class="gst-header">
        <div class="gh-left">
            <div class="co-name">{{ $data['company']['name'] }}</div>
            <div class="co-det">{{ $data['company']['address'] }}<br>{{ $data['company']['city'] }}, {{ $data['company']['state'] }} - {{ $data['company']['pin'] }}<br>GSTIN: {{ $data['company']['gstin'] ?? 'N/A' }}</div>
        </div>
        <div class="gh-right">
            <div class="gst-title">Tax Invoice</div>
            <div class="gst-orig">Original for Recipient</div>
        </div>
        <div class="gh-brand">
            <div class="co-det">PAN: {{ $data['meta']['pan'] ?? 'XXXXX0000X' }}<br>State: {{ $data['company']['state'] }}</div>
        </div>
    </div>

    {{-- DOC META STRIP --}}
    <div class="gst-meta">
        <div class="gmt-cell"><div class="gmt-key">Invoice No.</div><div class="gmt-val">{{ $data['doc_no'] }}</div></div>
        <div class="gmt-cell"><div class="gmt-key">Invoice Date</div><div class="gmt-val">{{ $data['doc_date'] }}</div></div>
        <div class="gmt-cell"><div class="gmt-key">Due Date</div><div class="gmt-val">{{ $data['due_date'] }}</div></div>
        <div class="gmt-cell"><div class="gmt-key">Place of Supply</div><div class="gmt-val">{{ $data['bill_to']['state'] ?? $data['meta']['state_of_supply'] ?? 'Tamil Nadu' }}</div></div>
        <div class="gmt-cell"><div class="gmt-key">Currency</div><div class="gmt-val">{{ $data['meta']['currency_code'] ?? 'INR' }}</div></div>
    </div>

    {{-- PARTY (Bill To | Ship To) --}}
    <div class="party-row">
        <div class="pr-cell">
            <div class="pr-hdr">Billed To</div>
            <div class="pr-name">{{ $data['bill_to']['name'] }}</div>
            <div>{{ $data['bill_to']['address'] }}, {{ $data['bill_to']['city'] }}</div>
            <div>{{ $data['bill_to']['state'] }} - {{ $data['bill_to']['pin'] }}</div>
            @if($data['bill_to']['gstin']) <div style="margin-top:2px;font-size:9.5px">GSTIN: <strong>{{ $data['bill_to']['gstin'] }}</strong></div> @endif
        </div>
        <div class="pr-cell">
            <div class="pr-hdr">Shipped To</div>
            <div class="pr-name">{{ $data['ship_to']['name'] }}</div>
            <div>{{ $data['ship_to']['address'] }}, {{ $data['ship_to']['city'] }}</div>
            <div>{{ $data['ship_to']['state'] }} - {{ $data['ship_to']['pin'] }}</div>
        </div>
    </div>

    {{-- ITEMS TABLE (GST columns) --}}
    <table class="items-table">
        <thead>
            <tr>
                <th style="width:22px">#</th>
                <th style="text-align:left;min-width:120px">Product Description</th>
                <th style="width:60px">HSN/SAC</th>
                <th style="width:50px">Qty</th>
                <th style="width:40px">Unit</th>
                <th style="width:70px">Unit Price</th>
                <th style="width:55px">Taxable Value</th>
                <th style="width:55px">CGST %</th>
                <th style="width:55px">CGST Rs</th>
                <th style="width:55px">SGST %</th>
                <th style="width:55px">SGST Rs</th>
                <th style="width:70px">Total</th>
            </tr>
        </thead>
        <tbody>
            @foreach($data['items'] as $item)
            @php
                $taxableVal = $item['unit_price'] * $item['qty'];
                $cgstRate = $item['tax_group'] === 'GST' ? $item['tax_rate'] / 2 : 0;
                $sgstRate = $cgstRate;
                $cgstAmt  = $item['tax_group'] === 'GST' ? $item['tax_amount'] / 2 : 0;
                $sgstAmt  = $cgstAmt;
            @endphp
            <tr>
                <td class="text-center">{{ $item['no'] }}</td>
                <td><div class="item-name">{{ $item['name'] }}</div>@if($item['description'])<div class="item-sub">{{ $item['description'] }}</div>@endif</td>
                <td class="text-center">{{ $item['hsn'] }}</td>
                <td class="text-right">{{ number_format($item['qty'], 2) }}</td>
                <td class="text-center">{{ $item['unit'] }}</td>
                <td class="text-right">{{ number_format($item['unit_price'], 2) }}</td>
                <td class="text-right">{{ number_format($taxableVal, 2) }}</td>
                <td class="text-center">{{ $cgstRate }}%</td>
                <td class="text-right">{{ number_format($cgstAmt, 2) }}</td>
                <td class="text-center">{{ $sgstRate }}%</td>
                <td class="text-right">{{ number_format($sgstAmt, 2) }}</td>
                <td class="text-right bold">{{ number_format($item['total'], 2) }}</td>
            </tr>
            @endforeach
        </tbody>
    </table>

    {{-- GST TOTALS --}}
    <div class="gst-totals">
        <div class="gt-left">
            <div style="font-size:9px;color:#888;margin-bottom:2px">Amount in Words</div>
            <div style="font-style:italic;font-weight:700;font-size:11px;line-height:1.5">{{ $data['meta']['total_words'] ?: ($data['meta']['currency_code'] ?? 'INR') . ' ' . number_format($data['totals']['grand_total'], 2) . ' Only' }}</div>
        </div>
        <div class="gt-right">
            <table class="gst-summary">
                <thead><tr><th>Taxable Amt</th><th>CGST</th><th>SGST</th><th>IGST</th><th>Total Tax</th></tr></thead>
                <tbody>
                    @php
                        $totalTaxable = collect($data['items'])->sum(fn($i) => $i['unit_price'] * $i['qty']);
                        $totalCGST = collect($data['totals']['tax_lines'])->where('label', 'CGST')->sum('amount');
                        $totalSGST = collect($data['totals']['tax_lines'])->where('label', 'SGST')->sum('amount');
                        $totalIGST = collect($data['totals']['tax_lines'])->where('label', 'IGST')->sum('amount');
                        $totalTax  = $totalCGST + $totalSGST + $totalIGST;
                    @endphp
                    <tr>
                        <td class="text-right">{{ number_format($totalTaxable, 2) }}</td>
                        <td class="text-right">{{ number_format($totalCGST, 2) }}</td>
                        <td class="text-right">{{ number_format($totalSGST, 2) }}</td>
                        <td class="text-right">{{ number_format($totalIGST, 2) }}</td>
                        <td class="text-right bold">{{ number_format($totalTax, 2) }}</td>
                    </tr>
                </tbody>
            </table>
            <div class="total-payable">Total Payable: {{ $data['meta']['currency_symbol'] ?? '₹' }}{{ number_format($data['totals']['grand_total'], 2) }}</div>
        </div>
    </div>

    {{-- DECLARATION --}}
    <div class="declaration">
        I/We hereby certify that my/our registration under the GST Act has not been cancelled and my/our registration is in force as on the date on which the supply of goods/services as detailed above is made by me/us and that the transaction of supply covered under this tax invoice shall be accounted for in the turnover and the due tax, if any, payable on such supply has been paid or shall be paid.
    </div>

    @if($data['meta']['terms_text'] ?? '')
    <div style="padding:6px 10px;font-size:9.5px;border-bottom:1px solid #ccc;"><strong>Terms &amp; Conditions:</strong> {!! nl2br(e($data['meta']['terms_text'])) !!}</div>
    @endif

    <div class="sig-row">
        <div class="sig-left"><span style="font-size:9px;color:#aaa">E. &amp; O.E.</span></div>
        <div class="sig-right" style="padding-bottom:8px"><div class="sig-line">Authorized Signatory<br><span style="font-size:9px">For {{ $data['company']['name'] }}</span></div></div>
    </div>

    @include('pdfs.partials._footer')
</div>
</body>
</html>
