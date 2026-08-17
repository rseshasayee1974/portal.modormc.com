<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>{{ $data['doc_title'] }} - {{ $data['doc_no'] }}</title>
    @include('pdfs.partials._common_styles')
    <style>
        .inv-root { border: 4px solid #111; width: 100%; }
        @media screen {
            .inv-root { min-height: 297mm; }
        }
        .title-bar { display: table; width: 100%; border-bottom: 2px solid #111; background: #111; color: #fff; }
        .title-left  { display: table-cell; vertical-align: middle; font-size: 14px; font-weight: 700; padding: 10px 0 10px 14px; }
        .title-right { display: table-cell; vertical-align: middle; text-align: right; font-size: 22px; font-weight: 900; text-transform: uppercase; letter-spacing: 0.03em; padding: 10px 14px 10px 0; }
        .doc-ref     { display: table; width: 100%; background: #f5f5f5; border-bottom: 1px solid #ccc; }
        .dr-cell     { display: table-cell; padding: 8px 8px; font-size: 10px; border-right: 1px solid #ccc; }
        .dr-cell:first-child { padding-left: 14px; }
        .dr-cell:last-child { padding-right: 14px; }
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
    @if (($pdfSettings['show_einvoice_details'] ?? true) && (!empty($data['meta']['irn']) || !empty($data['meta']['qr_code'])))
        <div style="display: table; width: 100%; border-bottom: 2px solid #111; padding: 6px 12px; font-size: 9.5px; background: #f5f5f5;">
            <div style="display: table-cell; vertical-align: middle;">
                @if(!empty($data['meta']['irn'])) <div><strong>IRN :</strong> {{ $data['meta']['irn'] }}</div> @endif
                @if(!empty($data['meta']['ack_no'])) <div><strong>Ack No. :</strong> {{ $data['meta']['ack_no'] }}</div> @endif
                @if(!empty($data['meta']['ack_date'])) <div><strong>Ack Date :</strong> {{ $data['meta']['ack_date'] }}</div> @endif
            </div>
            @if(!empty($data['meta']['qr_code']))
                <div style="display: table-cell; vertical-align: middle; text-align: right; width: 70px;">
                    <img src="{{ $data['meta']['qr_code'] }}" style="max-height: 50px; max-width: 50px; object-fit: contain;" />
                </div>
            @endif
        </div>
    @endif

    <div class="title-bar">
        <div class="title-left">
            @if (($pdfSettings['logo'] ?? true) && !empty($data['company']['logo_path']))
                @php
                    $cleanLogoPath = ltrim(
                        str_replace(['public/', 'storage/', '/storage/'], '', $data['company']['logo_path']),
                        '/',
                    );
                    $logoUrl = (request()->route('action') !== 'download' && !($is_pdf ?? false))
                        ? asset('storage/' . $cleanLogoPath)
                        : public_path('storage/' . $cleanLogoPath);
                @endphp
                <div style="margin-bottom: 4px;">
                    <img src="{{ $logoUrl }}" style="max-height: 45px; max-width: 160px; object-fit: contain; background: #fff; padding: 2px; border-radius: 4px;" />
                </div>
            @endif
            {{ $data['company']['name'] }}<br><span style="font-size:10px;color:#ccc">{{ $data['company']['city'] }}, {{ $data['company']['state'] }}</span>
        </div>
        <div class="title-right">{{ $data['doc_title'] }}<br><span style="font-size:11px;font-weight:400;opacity:0.7">{{ $data['doc_no'] }}</span></div>
    </div>
    <div class="doc-ref">
        @php
            $docRefFields = [
                'Date' => $data['doc_date'],
                'Delivery' => $data['delivery_date'],
                'Due Date' => ($data['due_date'] !== 'N/A' ? $data['due_date'] : ''),
            ];
            if(!empty($data['meta']['so_no'])) $docRefFields['SO#'] = $data['meta']['so_no'];
            $docRefFields['PO#'] = ($data['meta']['po_number'] ?? '');
            if(($pdfSettings['show_einvoice_details'] ?? true) && !empty($data['meta']['eway_bill_no'])) $docRefFields['EWayBill'] = $data['meta']['eway_bill_no'];
            $docRefFields['Status'] = $data['state'];
            if (!empty($data['meta']['sales_executive_name'])) {
                $docRefFields['Sales Exec'] = $data['meta']['sales_executive_name'];
            }
        @endphp
        @foreach($docRefFields as $k=>$v)
            @if($v) <div class="dr-cell"><span class="dr-key">{{ $k }}</span><span class="dr-val">{{ $v }}</span></div> @endif
        @endforeach
    </div>
    <table class="gr-table">
        <tr>
            <td class="gr-cell"><div class="gr-hdr">{{ $data['doc_title'] === 'PURCHASE ORDER' ? 'Vendor' : 'Bill To' }}</div><div class="gr-name">{{ $data['bill_to']['name'] }}</div><div>{{ $data['bill_to']['address'] }}, {{ $data['bill_to']['city'] }}</div>@if($data['bill_to']['gstin'])<div class="small">GSTIN: {{ $data['bill_to']['gstin'] }}</div>@endif</td>
            <td class="gr-cell"><div class="gr-hdr">Delivery Address</div><div class="gr-name">{{ $data['ship_to']['name'] }}</div><div>{{ $data['ship_to']['address'] }}, {{ $data['ship_to']['city'] }}</div></td>
            @if(($pdfSettings['show_customer_ref'] ?? true) && (!empty($data['meta']['acc_no']) || !empty($data['meta']['sales_person']) || !empty($data['meta']['pump']) || !empty($data['meta']['design_mix_ref'])))
                <td class="gr-cell">
                    <div class="gr-hdr">Customer Ref</div>
                    <div style="font-size: 10px;">
                        @if(!empty($data['meta']['acc_no'])) <div>Acc No: <strong>{{ $data['meta']['acc_no'] }}</strong></div> @endif
                        @if(!empty($data['meta']['sales_person'])) <div>Sales Person: <strong>{{ $data['meta']['sales_person'] }}</strong></div> @endif
                        @if(!empty($data['meta']['pump'])) <div>Pump: <strong>{{ $data['meta']['pump'] }}</strong></div> @endif
                    </div>
                </td>
            @endif
        </tr>
    </table>

    @if (($pdfSettings['show_carrier_driver'] ?? true) && !empty($data['meta']['carrier_driver']) && $data['meta']['carrier_driver'] !== '-')
        <div style="padding: 6px 12px; font-size: 10.5px; background: #f5f5f5; border-bottom: 1px solid #ccc;">
            <strong>Carrier - Driver:</strong> {{ $data['meta']['carrier_driver'] }}
        </div>
    @endif
    <table class="items-table">
            <thead>
                <tr>
                    <th class="text-center" style="width:28px">#</th>
                    <th class="text-left">Item &amp; Description</th>
                    @if (($pdfSettings['show_pump_charges'] ?? true) && !($data['totals']['add_pouring_rates_to_total'] ?? false))
                        <th class="text-left" style="width:120px">Operation Type</th>
                        <th class="text-right" style="width:90px">Pump Charges</th>
                    @endif
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
                        @if (($pdfSettings['show_pump_charges'] ?? true) && !($data['totals']['add_pouring_rates_to_total'] ?? false))
                            <td class="text-left">{{ $item['operation_type'] ?? '-' }}</td>
                            <td class="text-right">{{ isset($item['pump_charge']) && $item['pump_charge'] > 0 ? number_format($item['pump_charge'], 2) : '-' }}</td>
                        @endif
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
    <div style="padding:8px 12px;">
        <table class="bt-table">
            <tr>
                <td class="btt-lbl">Sub Total</td>
                <td class="btt-val">
                    {{ $data['meta']['currency_symbol'] ?? '₹' }}{{ number_format($data['totals']['sub_total'], 2) }}
                </td>
            </tr>
            @if ((($pdfSettings['show_pump_charges'] ?? true) || ($pdfSettings['pump_rates'] ?? true)) && isset($data['totals']['pump_rate']) && $data['totals']['pump_rate'] > 0)
                <tr>
                    <td class="btt-lbl">Concrete Pump Charges</td>
                    <td class="btt-val">
                        {{ $data['meta']['currency_symbol'] ?? '₹' }}{{ number_format($data['totals']['pump_rate'], 2) }}
                    </td>
                </tr>
            @endif
            @if (($pdfSettings['discount'] ?? true) && $data['totals']['discount'] > 0)
                <tr>
                    <td class="btt-lbl" style="color:#ef4444;">Discount (-)</td>
                    <td class="btt-val" style="color:#ef4444;">
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
                        <td class="btt-lbl">{{ $tl['label'] }}</td>
                        <td class="btt-val">
                            {{ $data['meta']['currency_symbol'] ?? '₹' }}{{ number_format($tl['amount'], 2) }}
                        </td>
                    </tr>
                @endif
            @endforeach
            @if (($pdfSettings['shipping'] ?? true) && $data['totals']['shipping'] > 0)
                <tr>
                    <td class="btt-lbl">Shipping</td>
                    <td class="btt-val">
                        {{ $data['meta']['currency_symbol'] ?? '₹' }}{{ number_format($data['totals']['shipping'], 2) }}
                    </td>
                </tr>
            @endif
            @if (($pdfSettings['adjustment'] ?? true) && ($data['totals']['adjustment'] ?? 0) != 0)
                <tr>
                    <td class="btt-lbl">Adjustment</td>
                    <td class="btt-val">
                        {{ $data['totals']['adjustment'] > 0 ? '+' : '' }}{{ $data['meta']['currency_symbol'] ?? '₹' }}{{ number_format($data['totals']['adjustment'], 2) }}
                    </td>
                </tr>
            @endif
            @if (($pdfSettings['round_off'] ?? true) && ($data['totals']['round_off'] ?? 0) != 0)
                <tr>
                    <td class="btt-lbl">Round Off</td>
                    <td class="btt-val">
                        {{ $data['totals']['round_off'] > 0 ? '+' : '' }}{{ $data['meta']['currency_symbol'] ?? '₹' }}{{ number_format($data['totals']['round_off'], 2) }}
                    </td>
                </tr>
            @endif
            <tr class="btt-grand">
                <td class="btt-lbl" style="font-weight:bold;">Total</td>
                <td class="btt-val" style="font-weight:bold;">
                    {{ $data['meta']['currency_symbol'] ?? '₹' }}{{ number_format($data['totals']['grand_total'], 2) }}
                </td>
            </tr>
        </table>
    </div>
    @if($data['meta']['terms_text'] ?? false)
    <div class="terms-text-content" style="padding:8px 12px;font-size:10px;border-top:1px solid #ccc;text-align:justify;white-space:normal !important;word-break:break-word;"><strong>Terms:</strong> {!! $data['meta']['terms_text'] !!}</div>
    @endif
    @if (($pdfSettings['show_bank_details'] ?? true) && !empty($data['company']['bank']['bank_name']))
        <div style="padding:6px 12px;font-size:10px;border-top:1px solid #ccc;color:#334155;background:#f5f5f5;">
            <strong>Bank Info:</strong> {{ $data['company']['bank']['bank_name'] }} &bull; <strong>A/C:</strong> {{ $data['company']['bank']['account_number'] }} &bull; <strong>IFSC:</strong> {{ $data['company']['bank']['ifsc_code'] }} &bull; <strong>Branch:</strong> {{ $data['company']['bank']['branch'] }}
        </div>
    @endif
    <div style="text-align:right;padding:8px 14px;border-top:1px solid #ccc;font-size:10px;min-height:60px;color:#aaa">Authorized Signatory — {{ $data['company']['name'] }}</div>
    @include('pdfs.partials._footer')
</div>
</body>
</html>
