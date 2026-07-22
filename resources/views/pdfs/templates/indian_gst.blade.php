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
        .gst-header { display: table; width: 100%; border-bottom: 3px solid #4f46e5; padding: 0 0 15px 0; margin-bottom: 20px; }
        .gh-left  { display: table-cell; vertical-align: bottom; }
        .gh-right { display: table-cell; vertical-align: bottom; text-align: right; }
        
        .co-name  { font-size: 18px; font-weight: 800; color: #4f46e5; text-transform: uppercase; margin-bottom: 4px; }
        .co-det   { font-size: 10px; color: #64748b; line-height: 1.4; }
        .gst-title { font-size: 22px; font-weight: 900; text-transform: uppercase; color: #1e293b; letter-spacing: -0.02em; }
        .gst-orig  { font-size: 10px; color: #94a3b8; font-weight: 600; text-transform: uppercase; margin-top: 2px; }

        .gst-meta { display: table; width: 100%; border-collapse: collapse; margin-bottom: 20px; background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 8px; }
        .gmt-cell { display: table-cell; padding: 10px 15px; border-right: 1px solid #e2e8f0; font-size: 10px; vertical-align: top; }
        .gmt-cell:last-child { border-right: none; }
        .gmt-key  { color: #64748b; font-size: 9px; font-weight: 600; text-transform: uppercase; margin-bottom: 2px; }
        .gmt-val  { font-weight: 700; color: #1e293b; font-size: 11px; }

        .party-row { display: table; width: 100%; margin-bottom: 25px; gap: 20px; }
        .pr-cell   { display: table-cell; width: 50%; padding: 0 10px 0 0; font-size: 11px; vertical-align: top; }
        .pr-cell:last-child { padding: 0 0 0 10px; }
        .pr-hdr    { font-size: 9px; font-weight: 800; text-transform: uppercase; color: #4f46e5; margin-bottom: 6px; border-bottom: 1px solid #e2e8f0; padding-bottom: 4px; }
        .pr-name   { font-weight: 800; font-size: 13px; color: #1e293b; margin-bottom: 4px; }
        .pr-det    { color: #64748b; line-height: 1.5; }

        .items-table { width: 100%; border-collapse: collapse; margin-bottom: 20px; }
        .items-table th { background: #1e293b; color: #fff; padding: 8px 10px; font-size: 9px; text-transform: uppercase; font-weight: 700; border: 1px solid #1e293b; }
        .items-table td { border: 1px solid #e2e8f0; padding: 8px 10px; font-size: 10px; color: #1e293b; }
        .items-table tr:nth-child(even) { background: #f8fafc; }

        .gst-totals { display: table; width: 100%; margin-top: 10px; }
        .gt-left  { display: table-cell; width: 55%; padding-right: 20px; vertical-align: top; }
        .gt-right { display: table-cell; width: 45%; vertical-align: top; }
        
        .gst-summary { width: 100%; border-collapse: collapse; margin-bottom: 10px; }
        .gst-summary th { background: #f8fafc; padding: 6px 8px; font-size: 9px; font-weight: 700; border: 1px solid #e2e8f0; color: #64748b; }
        .gst-summary td { padding: 6px 8px; border: 1px solid #e2e8f0; font-size: 10px; }
        
        .total-payable { background: #4f46e5; color: #fff; font-weight: 800; font-size: 16px; padding: 12px 15px; border-radius: 6px; text-align: right; margin-top: 5px; }

        .declaration { font-size: 9px; color: #64748b; line-height: 1.6; padding: 15px; background: #f8fafc; border-radius: 6px; margin-bottom: 20px; border-left: 3px solid #cbd5e1; }
        .terms-box { font-size: 9.5px; color: #1e293b; margin-bottom: 25px; }
        .terms-hdr { font-weight: 800; font-size: 10px; text-transform: uppercase; color: #64748b; margin-bottom: 5px; }
        
        .sig-row  { display: table; width: 100%; margin-top: 30px; }
        .sig-left  { display: table-cell; vertical-align: bottom; font-size: 9px; color: #94a3b8; }
        .sig-right { display: table-cell; vertical-align: bottom; text-align: right; }
        .sig-line  { display: inline-block; width: 200px; border-top: 2px solid #1e293b; padding-top: 8px; font-size: 11px; font-weight: 800; color: #1e293b; text-align: center; }
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
                <div class="co-det" style="margin-top:5px; font-weight:700; color:#1e293b">GSTIN: {{ $data['company']['gstin'] }}</div> 
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
        @if(($pdfSettings['due_date'] ?? true) && !empty($data['due_date']) && $data['due_date'] !== 'N/A') <div class="gmt-cell"><div class="gmt-key">Due Date</div><div class="gmt-val">{{ $data['due_date'] }}</div></div> @endif
        <div class="gmt-cell"><div class="gmt-key">State of Supply</div><div class="gmt-val">{{ $data['bill_to']['state'] ?? 'Tamil Nadu' }}</div></div>
        @if(!empty($data['meta']['sales_executive_name'])) <div class="gmt-cell"><div class="gmt-key">Sales Exec</div><div class="gmt-val">{{ $data['meta']['sales_executive_name'] }}</div></div> @endif
        @if(!empty($data['meta']['sales_executive_mobile'])) <div class="gmt-cell"><div class="gmt-key">Contact No</div><div class="gmt-val">{{ $data['meta']['sales_executive_mobile'] }}</div></div> @endif
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
                        <div style="margin-top:5px; color:#1e293b">GSTIN: <strong>{{ $data['bill_to']['gstin'] }}</strong></div> 
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
                        @include('pdfs.partials._pump_rates_table', ['item' => $item])
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
                <div class="terms-text-content" style="line-height:1.5;text-align:justify;white-space:normal !important;word-break:break-word;">{!! $data['meta']['terms_text'] !!}</div>
            </div>
            @endif
        </div>
        <div class="gt-right">
            <table class="gst-summary">
                <tbody>
                    <tr>
                        <td style="font-weight:bold; color:#64748b;">Sub Total</td>
                        <td class="text-right">{{ number_format($data['totals']['sub_total'], 2) }}</td>
                    </tr>
                    @if (($pdfSettings['discount'] ?? true) && $data['totals']['discount'] > 0)
                    <tr>
                        <td style="font-weight:bold; color:#ef4444;">Discount (-)</td>
                        <td class="text-right" style="color:#ef4444;">{{ number_format($data['totals']['discount'], 2) }}</td>
                    </tr>
                    @endif
                    @foreach($data['totals']['tax_lines'] as $tax)
                        @php
                            $showTax = true;
                            if (str_contains($tax['label'], 'CGST') && !($pdfSettings['cgst'] ?? true)) {
                                $showTax = false;
                            }
                            if (str_contains($tax['label'], 'SGST') && !($pdfSettings['sgst'] ?? true)) {
                                $showTax = false;
                            }
                            if (str_contains($tax['label'], 'IGST') && !($pdfSettings['igst'] ?? true)) {
                                $showTax = false;
                            }
                        @endphp
                        @if ($showTax)
                        <tr>
                            <td style="color:#64748b;">{{ $tax['label'] }}</td>
                            <td class="text-right">{{ number_format($tax['amount'], 2) }}</td>
                        </tr>
                        @endif
                    @endforeach
                    @if(($pdfSettings['shipping'] ?? true) && $data['totals']['shipping'] > 0)
                    <tr>
                        <td style="color:#64748b;">Shipping/Freight</td>
                        <td class="text-right">{{ number_format($data['totals']['shipping'], 2) }}</td>
                    </tr>
                    @endif
                    @if (($pdfSettings['adjustment'] ?? true) && ($data['totals']['adjustment'] ?? 0) != 0)
                        <tr>
                            <td style="color:#64748b;">Adjustment</td>
                            <td class="text-right">
                                {{ $data['totals']['adjustment'] > 0 ? '+' : '' }}{{ number_format($data['totals']['adjustment'], 2) }}
                            </td>
                        </tr>
                    @endif
                    @if (($pdfSettings['round_off'] ?? true) && ($data['totals']['round_off'] ?? 0) != 0)
                        <tr>
                            <td style="color:#64748b;">Round Off</td>
                            <td class="text-right">
                                {{ $data['totals']['round_off'] > 0 ? '+' : '' }}{{ number_format($data['totals']['round_off'], 2) }}
                            </td>
                        </tr>
                    @endif
                </tbody>
            </table>
            <div class="total-payable">
                <div style="font-size:10px; font-weight:600; opacity:0.8; margin-bottom:2px">Total Payable</div>
                {{ $data['meta']['currency_symbol'] ?? '₹' }}{{ number_format($data['totals']['grand_total'], 2) }}
            </div>
            <div style="margin-top:10px; font-size:10px; font-weight:700; color:#1e293b; text-align:right">
                {{ $data['meta']['total_words'] }}
            </div>
        </div>
    </div>

    @if($pdfSettings['signature'] ?? true)
    <div class="sig-row">
        <div class="sig-left">
            @if (($pdfSettings['upi_qr'] ?? true) && !empty($data['company']['upi_qr_path']))
                @php
                    $qrPath = ltrim(
                        str_replace(
                            ['public/', 'storage/', '/storage/'],
                            '',
                            $data['company']['upi_qr_path'],
                        ),
                        '/',
                    );
                    $qrUrl = (request()->route('action') !== 'download' && !($is_pdf ?? false))
                        ? asset('storage/' . $qrPath)
                        : public_path('storage/' . $qrPath);
                @endphp
                <div style="display: inline-block; text-align: left; vertical-align: top; margin-bottom: 8px;">
                    <div style="font-size: 8px; color: #64748b; font-weight: bold; margin-bottom: 2px;">Scan to Pay (UPI)</div>
                    <img src="{{ $qrUrl }}" style="max-height: 80px; max-width: 80px; object-fit: contain; border: 1px solid #cbd5e1; padding: 2px; background: #fff;" />
                </div>
                <br>
            @endif
            E. &amp; O.E.<br>
            <span style="font-size:8px">This is a computer generated document.</span>
        </div>
        <div class="sig-right" style="position: relative;">
            @if (!empty($data['company']['seal_sign_path']))
                @php
                    $sealPath = ltrim(
                        str_replace(
                            ['public/', 'storage/', '/storage/'],
                            '',
                            $data['company']['seal_sign_path'],
                        ),
                        '/',
                    );
                    $sealUrl = (request()->route('action') !== 'download' && !($is_pdf ?? false))
                        ? asset('storage/' . $sealPath)
                        : public_path('storage/' . $sealPath);
                @endphp
                <div style="margin-bottom: -20px; text-align: center;">
                    <img src="{{ $sealUrl }}" style="margin-left:150px;max-height: 100px; max-width: 120px; object-fit: contain;" />
                </div>
            @endif
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
