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
        .inv-root { border: 1px solid #cbd5e1; width: 100%; }
        @media screen {
            .inv-root { min-height: 297mm; }
        }

        .inv-header { display: table; width: 100%; border-bottom: 1px solid #cbd5e1; }
        .header-left  { display: table-cell; vertical-align: top; padding: 10px 14px; }
        .header-right { display: table-cell; vertical-align: top; text-align: right; padding: 10px 14px; }
        .co-name   { font-size: 15px; font-weight: 700; }
        .co-detail { font-size: 10px; color: #64748b; line-height: 1.45; }
        .inv-title { font-size: 24px; font-weight: 900; line-height: 1.1; }
        .inv-ref   { font-size: 10.5px; color: #64748b; margin-top: 2px; }

        /* 2-col details bar */
        .details-table { width: 100%; border-collapse: collapse; border-bottom: 1px solid #cbd5e1; }
        .details-cell  { padding: 7px 12px; vertical-align: top; border-right: 1px solid #cbd5e1; width: 50%; }
        .no-right { border-right: none; }
        .kv-table { border-collapse: collapse; width: 100%; }
        .kv-key   { color: #64748b; white-space: nowrap; padding: 1px 0; min-width: 100px; }
        .kv-sep   { padding: 1px 6px; color: #64748b; }
        .kv-val   { color: #1e293b; }

        /* Bill To / Ship To */
        .addr-table { width: 100%; border-collapse: collapse; border-bottom: 1px solid #cbd5e1; }
        .addr-th { background: #f8fafc; border-bottom: 1px solid #cbd5e1; padding: 5px 12px; font-weight: 700; font-size: 11px; text-align: left; width: 50%; }
        .addr-th-left { border-right: 1px solid #cbd5e1; }
        .addr-cell { padding: 7px 12px; vertical-align: top; font-size: 11px; }
        .addr-left { border-right: 1px solid #cbd5e1; }
        .addr-name { font-weight: 700; }
        .addr-line { color: #94a3b8; line-height: 1.5; }

        /* Subject */
        .subject-row { padding: 4px 12px; border-bottom: 1px solid #cbd5e1; font-size: 11px; background: #fafafa; }

        /* Items — dark header */
        .items-table { width: 100%; border-collapse: collapse; border-bottom: 1px solid #cbd5e1; }
        .items-table thead tr { background: #1e293b; color: #fff; }
        .items-table th { padding: 6px 8px; font-weight: 700; font-size: 10px; border: none; }
        .items-table td { padding: 5px 8px; vertical-align: top; border-bottom: 1px solid #e2e8f0; font-size: 11px; }
        .items-table tbody tr:last-child td { border-bottom: 1px solid #ccc; }

        /* Totals */
        .totals-split { display: table; width: 100%; border-bottom: 1px solid #cbd5e1; }
        .totals-left  { display: table-cell; vertical-align: top; padding: 8px 12px; border-right: 1px solid #cbd5e1; width: 55%; }
        .totals-right { display: table-cell; vertical-align: top; }
        .breakdown-table { width: 100%; border-collapse: collapse; }
        .breakdown-table td { padding: 3px 10px; }
        .bt-label { text-align: right; color: #64748b; padding-right: 14px !important; width: 58%; }
        .bt-val   { text-align: right; white-space: nowrap; }
        .bt-total-row   { border-top: 1px solid #cbd5e1; border-bottom: 1px solid #cbd5e1; }
        .bt-balance-row { background: #f1f5f9; }
        .bt-total-row td, .bt-balance-row td { padding-top: 5px !important; padding-bottom: 5px !important; }

        .tow-label { color: #64748b; font-size: 10px; margin-bottom: 2px; }
        .tow-value { font-style: italic; font-weight: 700; font-size: 11px; line-height: 1.5; }
    </style>
</head>
<body>
@include('pdfs.partials._print_actions')
<div class="inv-root">

    @if (($pdfSettings['show_einvoice_details'] ?? true) && (!empty($data['meta']['irn']) || !empty($data['meta']['qr_code'])))
        <div style="display: table; width: 100%; border-bottom: 1px solid #cbd5e1; padding: 6px 12px; font-size: 9.5px; background: #fafafa;">
            <div style="display: table-cell; vertical-align: middle;">
                @if(!empty($data['meta']['irn'])) <div><strong>IRN :</strong> {{ $data['meta']['irn'] }}</div> @endif
                @if(!empty($data['meta']['ack_no'])) <div><strong>Ack No. :</strong> {{ $data['meta']['ack_no'] }}</div> @endif
                @if(!empty($data['meta']['ack_date'])) <div><strong>Ack Date :</strong> {{ $data['meta']['ack_date'] }}</div> @endif
            </div>
            @if(!empty($data['meta']['qr_code']))
                <div style="display: table-cell; vertical-align: middle; text-align: right; width: 70px;">
                    <img src="{{ $data['meta']['qr_code'] }}" style="max-height: 60px; max-width: 60px; object-fit: contain;" />
                </div>
            @endif
        </div>
    @endif

    {{-- HEADER --}}
    <div class="inv-header">
        <div class="header-left">
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
                <div style="margin-bottom: 6px;">
                    <img src="{{ $logoUrl }}" style="max-height: 50px; max-width: 180px; object-fit: contain;" />
                </div>
            @endif
            @if($pdfSettings['company_name'] ?? true) <div class="co-name">{{ $data['company']['name'] }}</div> @endif
            @if($pdfSettings['address'] ?? true)
                <div class="co-detail">{{ $data['company']['address'] }}</div>
                <div class="co-detail">{{ $data['company']['city'] }}, {{ $data['company']['state'] }} {{ $data['company']['pin'] }}</div>
            @endif
            @if(($pdfSettings['gstin'] ?? true) && $data['company']['gstin']) <div class="co-detail">GSTIN: {{ $data['company']['gstin'] }}</div> @endif
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
                    @php
                        $kv1 = ['Date' => $data['doc_date']];
                        if(($pdfSettings['due_date'] ?? true) && !empty($data['due_date']) && $data['due_date'] !== 'N/A') $kv1['Due Date'] = $data['due_date'];
                        $kv1['Delivery'] = $data['delivery_date'];
                        if(!empty($data['meta']['so_no'])) $kv1['SO No'] = $data['meta']['so_no'];
                        $kv1['PO#'] = $data['meta']['po_number'] ?? '';
                        if(($pdfSettings['show_einvoice_details'] ?? true) && !empty($data['meta']['eway_bill_no'])) $kv1['EWayBillNo'] = $data['meta']['eway_bill_no'];
                        if (!empty($data['meta']['sales_executive_name'])) $kv1['Sales Exec'] = $data['meta']['sales_executive_name'];
                    @endphp
                    @foreach($kv1 as $k => $v)
                        @if($v) <tr><td class="kv-key">{{ $k }}</td><td class="kv-sep">:</td><td class="kv-val bold">{{ $v }}</td></tr> @endif
                    @endforeach
                </table>
            </td>
            <td class="details-cell no-right">
                <table class="kv-table">
                    @if(($pdfSettings['show_customer_ref'] ?? true) && (!empty($data['meta']['acc_no']) || !empty($data['meta']['sales_person']) || !empty($data['meta']['pump']) || !empty($data['meta']['design_mix_ref'])))
                        @if(!empty($data['meta']['acc_no'])) <tr><td class="kv-key">Acc No</td><td class="kv-sep">:</td><td class="kv-val bold">{{ $data['meta']['acc_no'] }}</td></tr> @endif
                        @if(!empty($data['meta']['sales_person'])) <tr><td class="kv-key">Sales Person</td><td class="kv-sep">:</td><td class="kv-val bold">{{ $data['meta']['sales_person'] }}</td></tr> @endif
                        @if(!empty($data['meta']['pump'])) <tr><td class="kv-key">Pump</td><td class="kv-sep">:</td><td class="kv-val bold">{{ $data['meta']['pump'] }}</td></tr> @endif
                        @if(!empty($data['meta']['design_mix_ref'])) <tr><td class="kv-key">Design Mix Ref</td><td class="kv-sep">:</td><td class="kv-val bold">{{ $data['meta']['design_mix_ref'] }}</td></tr> @endif
                    @else
                        @if($data['meta']['project_name'] ?? false)
                        <tr><td class="kv-key">Project</td><td class="kv-sep">:</td><td class="kv-val bold">{{ $data['meta']['project_name'] }}</td></tr>
                        @endif
                        <tr><td class="kv-key">Status</td><td class="kv-sep">:</td><td class="kv-val bold">{{ $data['state'] }}</td></tr>
                    @endif
                </table>
            </td>
        </tr>
    </table>

    @if (($pdfSettings['show_carrier_driver'] ?? true) && !empty($data['meta']['carrier_driver']) && $data['meta']['carrier_driver'] !== '-')
        <div style="padding: 5px 12px; border-bottom: 1px solid #cbd5e1; font-size: 10.5px; background: #fafafa;">
            <strong>Carrier - Driver:</strong> {{ $data['meta']['carrier_driver'] }}
        </div>
    @endif

    {{-- BILL TO / SHIP TO --}}
    <table class="addr-table">
        <thead>
            <tr>
                <th class="addr-th addr-th-left">{{ $labels['bill_to'] ?? ($data['doc_title'] === 'PURCHASE ORDER' ? 'Vendor Details' : 'Bill To') }}</th>
                <th class="addr-th">{{ $labels['ship_to'] ?? ($data['doc_title'] === 'PURCHASE ORDER' ? 'Delivery Address' : 'Ship To') }}</th>
            </tr>
        </thead>
        <tbody>
            <tr>
                <td class="addr-cell addr-left">
                    @if($pdfSettings['bill_to'] ?? true)
                        <div class="addr-name">{{ $data['bill_to']['name'] }}</div>
                        <div class="addr-line">{{ $data['bill_to']['address'] }}</div>
                        <div class="addr-line">{{ $data['bill_to']['city'] }}, {{ $data['bill_to']['state'] }} {{ $data['bill_to']['pin'] }}</div>
                        @if(($pdfSettings['gstin'] ?? true) && $data['bill_to']['gstin']) <div class="addr-line small">GSTIN: {{ $data['bill_to']['gstin'] }}</div> @endif
                    @endif
                </td>
                <td class="addr-cell">
                    @if($pdfSettings['ship_to'] ?? true)
                        <div class="addr-name">{{ $data['ship_to']['name'] }}</div>
                        <div class="addr-line">{{ $data['ship_to']['address'] }}</div>
                        <div class="addr-line">{{ $data['ship_to']['city'] }}, {{ $data['ship_to']['state'] }} {{ $data['ship_to']['pin'] }}</div>
                    @endif
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
                    @if ($pdfSettings['show_pump_charges'] ?? true)
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
                        @if ($pdfSettings['show_pump_charges'] ?? true)
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
                                {{ $item['tax_rate'] > 0 || (isset($item['tax_name']) && $item['tax_name'] !== '-') ? ($item['tax_rate'] == floor($item['tax_rate']) ? number_format($item['tax_rate'], 0) : number_format($item['tax_rate'], 2)) . '%' : '-' }}
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

    {{-- TOTALS --}}
    <div class="totals-split">
        <div class="totals-left">
            @if($pdfSettings['total_words'] ?? true)
                <div class="tow-label">Total in Words</div>
                <div class="tow-value">{{ $data['meta']['total_words'] ?: ($data['meta']['currency_code'] ?? 'INR') . ' ' . number_format($data['totals']['grand_total'], 2) . ' Only' }}</div>
            @endif
            @if(($pdfSettings['notes'] ?? true) && ($data['meta']['notes'] ?? false))
                <div class="small muted" style="margin-top:8px;">Notes</div>
                <div style="font-size:11px">{{ $data['meta']['notes'] }}</div>
            @endif
        </div>
        <div class="totals-right">
            <table class="breakdown-table">
            <tr>
                <td class="bt-label">Sub Total</td>
                <td class="bt-val">
                    {{ $data['meta']['currency_symbol'] ?? '₹' }}{{ number_format($data['totals']['sub_total'], 2) }}
                </td>
            </tr>
            @if ((($pdfSettings['show_pump_charges'] ?? true) || ($pdfSettings['pump_rates'] ?? true)) && isset($data['totals']['pump_rate']) && $data['totals']['pump_rate'] > 0)
                <tr>
                    <td class="bt-label">Concrete Pump Charges</td>
                    <td class="bt-val">
                        {{ $data['meta']['currency_symbol'] ?? '₹' }}{{ number_format($data['totals']['pump_rate'], 2) }}
                    </td>
                </tr>
            @endif
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
        </div>
    </div>

    {{-- TERMS --}}
    @php
        $termsText = trim(!empty($pdfSettings['terms_text']) ? $pdfSettings['terms_text'] : ($data['meta']['terms_text'] ?? ''));
        $termsHtml = (!empty($termsText) && $termsText === strip_tags($termsText)) ? nl2br(e($termsText)) : $termsText;
    @endphp
    @if(($pdfSettings['terms'] ?? true) && !empty($termsText))
    <div style="padding:7px 12px;border-bottom:1px solid #cbd5e1;">
        <div class="small muted">Terms &amp; Conditions</div>
        <div class="terms-text-content" style="font-size:10px;color:#94a3b8;margin-top:2px;text-align:justify;white-space:normal !important;word-break:break-word;">{!! $termsHtml !!}</div>
    </div>
    @endif

    {{-- SIGNATURE --}}
    @if($pdfSettings['signature'] ?? true)
    <div style="min-height:90px;padding:10px 12px;border-bottom:1px solid #cbd5e1;position:relative;">
        <table style="width:100%; border-collapse: collapse; border: none; margin: 0; padding: 0;">
            <tr>
                <td style="width: 50%; vertical-align: bottom; border: none; text-align: left; padding: 0;">
                    @if (($pdfSettings['show_bank_details'] ?? true) && !empty($data['company']['bank']['bank_name']))
                        <div style="margin-bottom: 8px; font-size: 9.5px; color: #334155;">
                            <div class="small muted" style="font-weight: bold; text-transform: uppercase; color: #4f46e5; margin-bottom: 2px;">Bank Information</div>
                            <div>Account Name: <strong>{{ $data['company']['bank']['account_name'] }}</strong></div>
                            <div>Account Number: <strong>{{ $data['company']['bank']['account_number'] }}</strong></div>
                            <div>Bank: <strong>{{ $data['company']['bank']['bank_name'] }}</strong> (Branch: {{ $data['company']['bank']['branch'] }})</div>
                            <div>IFSC Code: <strong>{{ $data['company']['bank']['ifsc_code'] }}</strong></div>
                        </div>
                    @endif
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
                        <div style="display: inline-block; text-align: left; vertical-align: top; margin-top: 10px;">
                            <div style="font-size: 8px; color: #64748b; font-weight: bold; margin-bottom: 2px;">Scan to Pay (UPI)</div>
                            <img src="{{ $qrUrl }}" style="max-height: 80px; max-width: 80px; object-fit: contain; border: 1px solid #cbd5e1; padding: 2px; background: #fff;" />
                        </div>
                    @endif
                </td>
                <td style="width: 50%; vertical-align: bottom; border: none; text-align: right; padding: 0;">
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
                        <span style="display:inline-block;width:160px;border-top:1px solid #999;padding-top:4px;text-align:center;font-size:10.5px;color:#64748b">
                            Authorized Signatory<br><span style="font-size:9px">For {{ $data['company']['name'] }}</span>
                        </span>
                    </div>
                </td>
            </tr>
        </table>
    </div>
    @endif

    @include('pdfs.partials._footer')
</div>
</body>
</html>
