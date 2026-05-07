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
        .inv-root { width: 100%; }
        .gst-header { display: table; width: 100%; border-bottom: 3px solid var(--color-accent); padding: 0 0 15px 0; margin-bottom: 20px; }
        .gh-left  { display: table-cell; vertical-align: bottom; }
        .gh-right { display: table-cell; vertical-align: bottom; text-align: right; }
        
        .co-name  { font-size: 18px; font-weight: 800; color: var(--color-accent); text-transform: uppercase; margin-bottom: 4px; }
        .co-det   { font-size: 10px; color: var(--color-muted); line-height: 1.4; }
        .gst-title { font-size: 22px; font-weight: 900; text-transform: uppercase; color: var(--color-ink); letter-spacing: -0.02em; }
        .gst-orig  { font-size: 10px; color: var(--color-light); font-weight: 600; text-transform: uppercase; margin-top: 2px; }

        .gst-meta { display: table; width: 100%; border-collapse: collapse; margin-bottom: 20px; background: var(--color-alt-bg); border: 1px solid var(--color-border-light); border-radius: 8px; }
        .gmt-cell { display: table-cell; padding: 10px 15px; border-right: 1px solid var(--color-border-light); font-size: 10px; vertical-align: top; }
        .gmt-cell:last-child { border-right: none; }
        .gmt-key  { color: var(--color-muted); font-size: 9px; font-weight: 600; text-transform: uppercase; margin-bottom: 2px; }
        .gmt-val  { font-weight: 700; color: var(--color-ink); font-size: 11px; }

        .party-row { display: table; width: 100%; margin-bottom: 25px; gap: 20px; }
        .pr-cell   { display: table-cell; width: 50%; padding: 0 10px 0 0; font-size: 11px; vertical-align: top; }
        .pr-cell:last-child { padding: 0 0 0 10px; }
        .pr-hdr    { font-size: 9px; font-weight: 800; text-transform: uppercase; color: var(--color-accent); margin-bottom: 6px; border-bottom: 1px solid var(--color-border-light); padding-bottom: 4px; }
        .pr-name   { font-weight: 800; font-size: 13px; color: var(--color-ink); margin-bottom: 4px; }
        .pr-det    { color: var(--color-muted); line-height: 1.5; }

        .items-table { width: 100%; border-collapse: collapse; margin-bottom: 20px; }
        .items-table th { background: var(--color-header-bg); color: #fff; padding: 8px 10px; font-size: 9px; text-transform: uppercase; font-weight: 700; border: 1px solid var(--color-header-bg); }
        .items-table td { border: 1px solid var(--color-border-light); padding: 8px 10px; font-size: 10px; color: var(--color-ink); }
        .items-table tr:nth-child(even) { background: var(--color-alt-bg); }

        .gst-totals { display: table; width: 100%; margin-top: 10px; }
        .gt-left  { display: table-cell; width: 55%; padding-right: 20px; vertical-align: top; }
        .gt-right { display: table-cell; width: 45%; vertical-align: top; }
        
        .gst-summary { width: 100%; border-collapse: collapse; margin-bottom: 10px; }
        .gst-summary th { background: var(--color-alt-bg); padding: 6px 8px; font-size: 9px; font-weight: 700; border: 1px solid var(--color-border-light); color: var(--color-muted); }
        .gst-summary td { padding: 6px 8px; border: 1px solid var(--color-border-light); font-size: 10px; }
        
        .total-payable { background: var(--color-accent); color: #fff; font-weight: 800; font-size: 16px; padding: 12px 15px; border-radius: 6px; text-align: right; margin-top: 5px; }

        .declaration { font-size: 9px; color: var(--color-muted); line-height: 1.6; padding: 15px; background: var(--color-alt-bg); border-radius: 6px; margin-bottom: 20px; border-left: 3px solid var(--color-border); }
        .terms-box { font-size: 9.5px; color: var(--color-ink); margin-bottom: 25px; }
        .terms-hdr { font-weight: 800; font-size: 10px; text-transform: uppercase; color: var(--color-muted); margin-bottom: 5px; }
        
        .sig-row  { display: table; width: 100%; margin-top: 30px; }
        .sig-left  { display: table-cell; vertical-align: bottom; font-size: 9px; color: var(--color-light); }
        .sig-right { display: table-cell; vertical-align: bottom; text-align: right; }
        .sig-line  { display: inline-block; width: 200px; border-top: 2px solid var(--color-ink); padding-top: 8px; font-size: 11px; font-weight: 800; color: var(--color-ink); text-align: center; }
    </style>
</head>
<body>
@include('pdfs.partials._print_actions')
<div class="inv-root">

    {{-- GST HEADER (3-col: company | doc title | brand) --}}
    <div class="gst-header">
        <div class="gh-left">
            @if($pdfSettings['company_name'] ?? true) <div class="co-name">{{ $data['company']['name'] }}</div> @endif
            @if($pdfSettings['address'] ?? true)
                <div class="co-det">
                    {{ $data['company']['address'] }}<br>
                    {{ $data['company']['city'] }}, {{ $data['company']['state'] }} - {{ $data['company']['pin'] }}<br>
                    @if($data['company']['phone']) Phone: {{ $data['company']['phone'] }} &bull; @endif
                    @if($data['company']['email']) Email: {{ $data['company']['email'] }} @endif
                </div>
            @endif
            @if(($pdfSettings['gstin'] ?? true) && $data['company']['gstin']) 
                <div class="co-det" style="margin-top:5px; font-weight:700; color:var(--color-ink)">GSTIN: {{ $data['company']['gstin'] }}</div> 
            @endif
        </div>
        <div class="gh-right">
            <div class="gst-title">{{ $data['doc_title'] }}</div>
            <div class="gst-orig">Original for Recipient</div>
            <div class="co-det" style="margin-top:5px">PAN: {{ $data['meta']['pan'] ?? 'XXXXX0000X' }}</div>
        </div>
    </div>

    {{-- DOC META STRIP --}}
    <div class="gst-meta">
        <div class="gmt-cell"><div class="gmt-key">Invoice No.</div><div class="gmt-val">{{ $data['doc_no'] }}</div></div>
        <div class="gmt-cell"><div class="gmt-key">Date</div><div class="gmt-val">{{ $data['doc_date'] }}</div></div>
        @if($pdfSettings['due_date'] ?? true) <div class="gmt-cell"><div class="gmt-key">Due Date</div><div class="gmt-val">{{ $data['due_date'] }}</div></div> @endif
        <div class="gmt-cell"><div class="gmt-key">State of Supply</div><div class="gmt-val">{{ $data['bill_to']['state'] ?? 'Tamil Nadu' }}</div></div>
        <div class="gmt-cell"><div class="gmt-key">Project</div><div class="gmt-val">{{ $data['meta']['project_name'] ?: '---' }}</div></div>
    </div>

    {{-- PARTY (Bill To | Ship To) --}}
    <div class="party-row">
        <div class="pr-cell">
            @if($pdfSettings['bill_to'] ?? true)
                <div class="pr-hdr">{{ $labels['bill_to'] ?? 'Bill To (Customer)' }}</div>
                <div class="pr-name">{{ $data['bill_to']['name'] }}</div>
                <div class="pr-det">
                    {{ $data['bill_to']['address'] }}, {{ $data['bill_to']['city'] }}<br>
                    {{ $data['bill_to']['state'] }} - {{ $data['bill_to']['pin'] }}
                    @if(($pdfSettings['gstin'] ?? true) && $data['bill_to']['gstin']) 
                        <div style="margin-top:5px; color:var(--color-ink)">GSTIN: <strong>{{ $data['bill_to']['gstin'] }}</strong></div> 
                    @endif
                </div>
            @endif
        </div>
        <div class="pr-cell">
            @if($pdfSettings['ship_to'] ?? true)
                <div class="pr-hdr">{{ $labels['ship_to'] ?? 'Ship To (Site Location)' }}</div>
                <div class="pr-name">{{ $data['ship_to']['name'] }}</div>
                <div class="pr-det">
                    {{ $data['ship_to']['address'] }}, {{ $data['ship_to']['city'] }}<br>
                    {{ $data['ship_to']['state'] }} - {{ $data['ship_to']['pin'] }}
                </div>
            @endif
        </div>
    </div>

    {{-- ITEMS TABLE --}}
    <table class="items-table">
        <thead>
            <tr>
                <th style="width:30px">#</th>
                <th style="text-align:left">Description</th>
                @if($pdfSettings['hsn_code'] ?? true) <th style="width:60px">HSN</th> @endif
                <th style="width:50px">Qty</th>
                <th style="width:70px">Rate</th>
                <th style="width:80px">Taxable</th>
                <th style="width:120px">Tax Split</th>
                <th style="width:80px">Total</th>
            </tr>
        </thead>
        <tbody>
            @foreach($data['items'] as $item)
            @php
                $taxableVal = $item['unit_price'] * $item['qty'];
            @endphp
            <tr>
                <td class="text-center">{{ $item['no'] }}</td>
                <td>
                    <div class="item-name">{{ $item['name'] }}</div>
                    @if(($pdfSettings['description'] ?? true) && $item['description']) <div class="item-sub">{{ $item['description'] }}</div> @endif
                </td>
                @if($pdfSettings['hsn_code'] ?? true) <td class="text-center">{{ $item['hsn'] }}</td> @endif
                <td class="text-center">{{ number_format($item['qty'], 2) }} <span style="font-size:8px; color:#999">{{ $item['unit'] }}</span></td>
                <td class="text-right">{{ number_format($item['unit_price'], 2) }}</td>
                <td class="text-right">{{ number_format($taxableVal, 2) }}</td>
                <td style="padding: 0;">
                    <table style="width:100%; height:100%; border-collapse:collapse;">
                        <tr>
                            <td style="border:none; padding: 4px; font-size:8px; width:50%; border-right:1px solid #eee">CGST: {{ number_format($item['tax_amount']/2, 2) }}</td>
                            <td style="border:none; padding: 4px; font-size:8px; width:50%">SGST: {{ number_format($item['tax_amount']/2, 2) }}</td>
                        </tr>
                    </table>
                </td>
                <td class="text-right bold">{{ number_format($item['total'], 2) }}</td>
            </tr>
            @endforeach
        </tbody>
    </table>

    {{-- GST TOTALS --}}
    <div class="gst-totals">
        <div class="gt-left">
            <div class="declaration">
                <strong>Declaration:</strong><br>
                I/We hereby certify that my/our registration under the GST Act has not been cancelled and my/our registration is in force as on the date on which the supply of goods/services as detailed above is made by me/us and that the transaction of supply covered under this tax invoice shall be accounted for in the turnover and the due tax, if any, payable on such supply has been paid or shall be paid.
            </div>
            
            @if(($pdfSettings['terms'] ?? true) && ($data['meta']['terms_text'] ?? ''))
            <div class="terms-box">
                <div class="terms-hdr">Terms &amp; Conditions</div>
                <div style="line-height:1.5">{!! nl2br(e($data['meta']['terms_text'])) !!}</div>
            </div>
            @endif
        </div>
        <div class="gt-right">
            <table class="gst-summary">
                <thead><tr><th>Tax Breakup</th><th>Rate</th><th>Amount</th></tr></thead>
                <tbody>
                    @foreach($data['totals']['tax_lines'] as $tax)
                    <tr>
                        <td>{{ $tax['label'] }}</td>
                        <td class="text-center">---</td>
                        <td class="text-right">{{ number_format($tax['amount'], 2) }}</td>
                    </tr>
                    @endforeach
                    @if($data['totals']['shipping'] > 0)
                    <tr>
                        <td>Shipping/Freight</td>
                        <td class="text-center">---</td>
                        <td class="text-right">{{ number_format($data['totals']['shipping'], 2) }}</td>
                    </tr>
                    @endif
                </tbody>
            </table>
            <div class="total-payable">
                <div style="font-size:10px; font-weight:600; opacity:0.8; margin-bottom:2px">Total Payable</div>
                {{ $data['meta']['currency_symbol'] ?? '₹' }}{{ number_format($data['totals']['grand_total'], 2) }}
            </div>
            <div style="margin-top:10px; font-size:10px; font-weight:700; color:var(--color-ink); text-align:right">
                {{ $data['meta']['total_words'] }}
            </div>
        </div>
    </div>

    @if($pdfSettings['signature'] ?? true)
    <div class="sig-row">
        <div class="sig-left">
            E. &amp; O.E.<br>
            <span style="font-size:8px">This is a computer generated document.</span>
        </div>
        <div class="sig-right">
            <div class="sig-line">
                Authorized Signatory<br>
                <span style="font-size:9px; font-weight:normal">For {{ $data['company']['name'] }}</span>
            </div>
        </div>
    </div>
    @endif

    @include('pdfs.partials._footer')
</div>
</body>
</html>
