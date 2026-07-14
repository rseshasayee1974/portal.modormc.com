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
        .inv-root { width: 100%; /* NO border — borderless design */ }
        @media screen {
            .inv-root { min-height: 297mm; }
        }

        .inv-header { display: table; width: 100%; }
        .header-left  { display: table-cell; vertical-align: top; padding: 18px 0 14px 18px; }
        .header-right { display: table-cell; vertical-align: top; text-align: right; padding: 18px 18px 14px 0; }
        .co-name   { font-size: 13px; font-weight: 700; }
        .co-detail { font-size: 10px; color: #64748b; line-height: 1.5; }
        .inv-title { font-size: 30px; font-weight: 900; line-height: 1.0; }
        .inv-ref   { font-size: 10.5px; color: #64748b; margin-top: 3px; }

        .addr-section  { display: table; width: 100%; }
        .addr-col      { display: table-cell; width: 50%; padding: 12px 10px 8px 10px; vertical-align: top; }
        .addr-col:first-child { padding-left: 18px; }
        .addr-col:last-child { padding-right: 18px; }
        .addr-label    { color: #888; font-size: 10.5px; margin-bottom: 3px; }
        .addr-name     { font-weight: 700; }
        .addr-line     { color: #94a3b8; line-height: 1.5; }

        .subject-block { padding: 8px 18px 14px; }
        .subject-label { color: #888; margin-bottom: 2px; }
        .subject-value { font-size: 11px; }

        /* Dark-header span tables */
        .details-bar { width: 100%; border-collapse: collapse; margin-bottom: 6px; }
        .dbar-th     { padding: 7px 12px; color: #fff; background: #1e293b; font-size: 10px; font-weight: 700; text-align: left; }
        .dbar-td     { padding: 6px 12px; font-size: 11px; color: #333; border-bottom: 1px solid #ddd; text-align: left; }

        .items-table { width: 100%; border-collapse: collapse; }
        .items-table thead tr { background: #1e293b; color: #fff; }
        .items-table th { padding: 7px 8px; font-size: 10px; font-weight: 700; }
        .items-table td { padding: 5px 8px; vertical-align: top; border-bottom: 1px solid #e2e8f0; font-size: 11px; }
        .items-table tbody tr:last-child td { border-bottom: 1px solid #ccc; }

        .totals-block { text-align: right; padding: 2px 0; }
        .breakdown-table { width: 300px; border-collapse: collapse; margin-left: auto; }
        .breakdown-table td { padding: 4px 10px; font-size: 11px; }
        .bt-label { text-align: right; color: #64748b; padding-right: 18px !important; width: 55%; }
        .bt-val   { text-align: right; white-space: nowrap; }
        .bt-total-row   { border-top: 1px solid #ccc; border-bottom: 1px solid #ccc; }
        .bt-total-row td, .bt-balance-row td { padding-top: 5px !important; padding-bottom: 5px !important; }
        .bt-balance-row { background: #f1f5f9; }

        .tow-row  { width: 300px; margin-left: auto; padding: 6px 10px; }
        .tow-label{ font-size: 10.5px; color: #64748b; margin-bottom: 2px; }
        .tow-value{ font-style: italic; font-weight: 700; text-align: right; font-size: 11px; line-height: 1.5; }

        .bottom-section { padding: 10px 18px; border-top: 1px solid #ccc; font-size: 11px; }
        .section-label   { color: #888; font-size: 10.5px; margin-bottom: 2px; }
    </style>
</head>
<body>
@include('pdfs.partials._print_actions')
<div class="inv-root">
    <div class="inv-header">
        <div class="header-left">
            @if($pdfSettings['company_name'] ?? true) <div class="co-name">{{ $data['company']['name'] }}</div> @endif
            @if($pdfSettings['address'] ?? true)
                <div class="co-detail">{{ $data['company']['address'] }}</div>
                <div class="co-detail">{{ $data['company']['city'] }}, {{ $data['company']['state'] }}</div>
            @endif
            @if(($pdfSettings['gstin'] ?? true) && $data['company']['gstin']) <div class="co-detail">GSTIN: {{ $data['company']['gstin'] }}</div> @endif
        </div>
        <div class="header-right">
            <div class="inv-title">{{ $data['doc_title'] }}</div>
            <div class="inv-ref">Ref# <strong>{{ $data['doc_no'] }}</strong></div>
        </div>
    </div>

    {{-- Bill To / Ship To — borderless --}}
    <div class="addr-section">
        <div class="addr-col">
            @if($pdfSettings['bill_to'] ?? true)
                <div class="addr-label">{{ $labels['bill_to'] ?? ($data['doc_title'] === 'PURCHASE ORDER' ? 'Vendor' : 'Bill To') }}</div>
                <div class="addr-name">{{ $data['bill_to']['name'] }}</div>
                <div class="addr-line">{{ $data['bill_to']['address'] }}, {{ $data['bill_to']['city'] }}</div>
                @if(($pdfSettings['gstin'] ?? true) && $data['bill_to']['gstin']) <div class="addr-line small">GSTIN: {{ $data['bill_to']['gstin'] }}</div> @endif
            @endif
        </div>
        <div class="addr-col">
            @if($pdfSettings['ship_to'] ?? true)
                <div class="addr-label">{{ $labels['ship_to'] ?? 'Ship To / Delivery' }}</div>
                <div class="addr-name">{{ $data['ship_to']['name'] }}</div>
                <div class="addr-line">{{ $data['ship_to']['address'] }}, {{ $data['ship_to']['city'] }}</div>
            @endif
        </div>
    </div>

    <div class="subject-block">
        <div class="subject-label">Subject :</div>
        <div class="subject-value">{{ $data['meta']['project_name'] ?? 'Description' }}</div>
    </div>

    {{-- Details bar --}}
    <table class="details-bar">
        <thead>
            <tr>
                <th class="dbar-th">Date</th>
                @if(($pdfSettings['due_date'] ?? true) && !empty($data['due_date']) && $data['due_date'] !== 'N/A') <th class="dbar-th">Due Date</th> @endif
                <th class="dbar-th">Delivery</th>
                @if(!empty($data['meta']['po_number']) && $data['meta']['po_number'] !== '-') <th class="dbar-th">PO#</th> @endif
                @if(!empty($data['meta']['sales_executive_name'])) <th class="dbar-th">Sales Exec</th> @endif
                @if(!empty($data['meta']['sales_executive_mobile'])) <th class="dbar-th">Contact No</th> @endif
                <th class="dbar-th">Project</th>
            </tr>
        </thead>
        <tbody>
            <tr>
                <td class="dbar-td">{{ $data['doc_date'] }}</td>
                @if(($pdfSettings['due_date'] ?? true) && !empty($data['due_date']) && $data['due_date'] !== 'N/A') <td class="dbar-td">{{ $data['due_date'] }}</td> @endif
                <td class="dbar-td">{{ $data['delivery_date'] }}</td>
                @if(!empty($data['meta']['po_number']) && $data['meta']['po_number'] !== '-') <td class="dbar-td">{{ $data['meta']['po_number'] }}</td> @endif
                @if(!empty($data['meta']['sales_executive_name'])) <td class="dbar-td">{{ $data['meta']['sales_executive_name'] }}</td> @endif
                @if(!empty($data['meta']['sales_executive_mobile'])) <td class="dbar-td">{{ $data['meta']['sales_executive_mobile'] }}</td> @endif
                <td class="dbar-td">{{ $data['meta']['project_name'] ?? '-' }}</td>
            </tr>
        </tbody>
    </table>

    {{-- Items --}}
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

    {{-- Totals — right only --}}
    <div class="totals-block">
        <table class="breakdown-table">
            <tr>
                <td class="bt-label">Sub Total</td>
                <td class="bt-val">
                    {{ $data['meta']['currency_symbol'] ?? '₹' }}{{ number_format($data['totals']['sub_total'], 2) }}
                </td>
            </tr>
            @if (($pdfSettings['discount'] ?? true) && $data['totals']['discount'] > 0)
                <tr>
                    <td class="bt-label" style="color:#ef4444;">Discount (-)</td>
                    <td class="bt-val" style="color:#ef4444;">
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
                        <td class="bt-label">{{ $tl['label'] }}</td>
                        <td class="bt-val">
                            {{ $data['meta']['currency_symbol'] ?? '₹' }}{{ number_format($tl['amount'], 2) }}
                        </td>
                    </tr>
                @endif
            @endforeach
            @if (($pdfSettings['shipping'] ?? true) && $data['totals']['shipping'] > 0)
                <tr>
                    <td class="bt-label">Shipping</td>
                    <td class="bt-val">
                        {{ $data['meta']['currency_symbol'] ?? '₹' }}{{ number_format($data['totals']['shipping'], 2) }}
                    </td>
                </tr>
            @endif
            @if (($pdfSettings['adjustment'] ?? true) && ($data['totals']['adjustment'] ?? 0) != 0)
                <tr>
                    <td class="bt-label">Adjustment</td>
                    <td class="bt-val">
                        {{ $data['totals']['adjustment'] > 0 ? '+' : '' }}{{ $data['meta']['currency_symbol'] ?? '₹' }}{{ number_format($data['totals']['adjustment'], 2) }}
                    </td>
                </tr>
            @endif
            @if (($pdfSettings['round_off'] ?? true) && ($data['totals']['round_off'] ?? 0) != 0)
                <tr>
                    <td class="bt-label">Round Off</td>
                    <td class="bt-val">
                        {{ $data['totals']['round_off'] > 0 ? '+' : '' }}{{ $data['meta']['currency_symbol'] ?? '₹' }}{{ number_format($data['totals']['round_off'], 2) }}
                    </td>
                </tr>
            @endif
            <tr class="bt-total-row">
                <td class="bt-label" style="font-weight:bold;">Total</td>
                <td class="bt-val" style="font-weight:bold;">
                    {{ $data['meta']['currency_symbol'] ?? '₹' }}{{ number_format($data['totals']['grand_total'], 2) }}
                </td>
            </tr>
        </table>
        @if($pdfSettings['total_words'] ?? true)
            <div class="tow-row">
                <div class="tow-label">Total In Words:</div>
                <div class="tow-value">{{ $data['meta']['total_words'] ?: ($data['meta']['currency_code'] ?? 'INR') . ' ' . number_format($data['totals']['grand_total'], 2) . ' Only' }}</div>
            </div>
        @endif
    </div>

    @if(($pdfSettings['notes'] ?? true) && ($data['meta']['notes'] ?? false))
    <div class="bottom-section"><div class="section-label">Notes</div><div>{{ $data['meta']['notes'] }}</div></div>
    @endif
    @if(($pdfSettings['terms'] ?? true) && ($data['meta']['terms_text'] ?? false))
    <div class="bottom-section"><div class="section-label">Terms &amp; Conditions</div><div class="terms-text-content" style="font-size:10px;text-align:justify;white-space:normal !important;word-break:break-word;">{!! $data['meta']['terms_text'] !!}</div></div>
    @endif

    @if($pdfSettings['signature'] ?? true)
    <div style="min-height:80px;padding:10px 18px;border-top:1px solid #ccc;text-align:right;">
        <div style="margin-top:20px; display: inline-block; text-align: center; position: relative;">
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
                <div style="margin-bottom: -15px; text-align: center;">
                    <img src="{{ $sealUrl }}" style="max-height: 45px; max-width: 120px; object-fit: contain;" />
                </div>
            @else
                <div style="height: 30px;"></div>
            @endif
            <span style="display:inline-block;width:160px;border-top:1px solid #999;padding-top:4px;text-align:center;font-size:10px;color:#64748b">Authorized Signatory<br><span style="font-size:9px">For {{ $data['company']['name'] }}</span></span>
        </div>
    </div>
    @endif

    @include('pdfs.partials._footer')
</div>
</body>
</html>
