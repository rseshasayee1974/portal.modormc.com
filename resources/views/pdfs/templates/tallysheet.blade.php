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
        .ledger-header { display: table; width: 100%; border-bottom: 2px solid #111; }
        .lh-left  { display: table-cell; vertical-align: bottom; padding: 8px 0 8px 12px; }
        .lh-right { display: table-cell; vertical-align: bottom; text-align: right; padding: 8px 12px 8px 0; }
        .co-name  { font-size: 14px; font-weight: 700; }
        .co-det   { font-size: 9.5px; color: #64748b; }
        .doc-badge { display: inline-block; background: #111; color: #fff; font-size: 12px; font-weight: 700; padding: 4px 16px; }
        .doc-no    { font-size: 10px; color: #64748b; }

        .meta-bar { display: table; width: 100%; border-bottom: 1px solid #cbd5e1; background: #f7f7f7; }
        .mb-cell  { display: table-cell; padding: 5px 10px; border-right: 1px solid #cbd5e1; font-size: 10px; vertical-align: top; }
        .mb-cell:last-child { border-right: none; }
        .mb-key   { font-size: 8.5px; color: #888; text-transform: uppercase; display: block; margin-bottom: 1px; }
        .mb-val   { font-weight: 700; font-size: 10.5px; }

        /* Ledger items table */
        .ledger-table { width: 100%; border-collapse: collapse; border-bottom: 1px solid #cbd5e1; }
        .ledger-table th { background: #111; color: #fff; padding: 5px 8px; font-size: 9.5px; border: 1px solid #333; }
        .ledger-table td { border: 1px solid #ddd; padding: 4px 8px; font-size: 10.5px; vertical-align: middle; }
        .ledger-table .row-sub { background: #fafafa; }

        .totals-ledger { border: 1px solid #cbd5e1; margin: 0; }
        .tl-row { display: table; width: 100%; border-bottom: 1px solid #e2e8f0; }
        .tl-label { display: table-cell; width: 78%; text-align: right; padding: 4px 14px; font-size: 10.5px; border-right: 1px solid #cbd5e1; }
        .tl-val   { display: table-cell; width: 22%; text-align: right; padding: 4px 12px; font-size: 10.5px; }
        .tl-final { background: #111; color: #fff; }
        .tl-final .tl-label, .tl-final .tl-val { font-weight: 700; font-size: 12px; }
        .sig-row { display: table; width: 100%; min-height: 80px; }
        .sig-left  { display: table-cell; vertical-align: bottom; font-size: 10px; color: #64748b; width: 60%; padding: 8px 0 8px 12px; }
        .sig-right { display: table-cell; vertical-align: bottom; text-align: right; padding: 8px 12px 8px 0; }
        .sig-line  { display: inline-block; width: 160px; border-top: 1px solid #999; padding-top: 4px; font-size: 10px; color: #64748b; text-align: center; }
    </style>
</head>
<body>
@include('pdfs.partials._print_actions')
<div class="inv-root">
    @if (($pdfSettings['show_einvoice_details'] ?? true) && (!empty($data['meta']['irn']) || !empty($data['meta']['qr_code'])))
        <div style="display: table; width: 100%; border-bottom: 2px solid #111; padding: 6px 12px; font-size: 9.5px; background: #f7f7f7;">
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

    <div class="ledger-header">
        <div class="lh-left">
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
                    <img src="{{ $logoUrl }}" style="max-height: 45px; max-width: 160px; object-fit: contain;" />
                </div>
            @endif
            <div class="co-name">{{ $data['company']['name'] }}</div>
            <div class="co-det">{{ $data['company']['address'] }}, {{ $data['company']['city'] }} | GSTIN: {{ $data['company']['gstin'] ?? 'N/A' }}</div>
        </div>
        <div class="lh-right">
            <div class="doc-badge">{{ $data['doc_title'] }}</div>
            <div class="doc-no">{{ $data['doc_no'] }}</div>
        </div>
    </div>

    <div class="meta-bar">
        @php
            $metaBarFields = [
                'Date' => $data['doc_date'],
                'Due Date' => ($data['due_date'] !== 'N/A' ? $data['due_date'] : ''),
                'Delivery' => $data['delivery_date'],
                'Party' => $data['bill_to']['name'],
                'Project' => ($data['meta']['project_name'] ?? '')
            ];
            if(!empty($data['meta']['so_no'])) $metaBarFields['SO#'] = $data['meta']['so_no'];
            $metaBarFields['PO#'] = ($data['meta']['po_number'] ?? '');
            if(($pdfSettings['show_einvoice_details'] ?? true) && !empty($data['meta']['eway_bill_no'])) $metaBarFields['EWayBill'] = $data['meta']['eway_bill_no'];
            if (!empty($data['meta']['sales_executive_name'])) {
                $metaBarFields['Sales Exec'] = $data['meta']['sales_executive_name'];
            }
        @endphp
        @foreach($metaBarFields as $k=>$v)
            @if($v) <div class="mb-cell"><span class="mb-key">{{ $k }}</span><span class="mb-val">{{ $v }}</span></div> @endif
        @endforeach
    </div>

    @if(($pdfSettings['show_customer_ref'] ?? true) && (!empty($data['meta']['acc_no']) || !empty($data['meta']['sales_person']) || !empty($data['meta']['pump']) || !empty($data['meta']['design_mix_ref'])))
        <div style="padding: 5px 10px; border-bottom: 1px solid #cbd5e1; font-size: 10px; background: #fff;">
            <strong>Customer Ref:</strong>
            @if(!empty($data['meta']['acc_no'])) Acc No: <strong>{{ $data['meta']['acc_no'] }}</strong> &bull; @endif
            @if(!empty($data['meta']['sales_person'])) Sales Person: <strong>{{ $data['meta']['sales_person'] }}</strong> &bull; @endif
            @if(!empty($data['meta']['pump'])) Pump: <strong>{{ $data['meta']['pump'] }}</strong> &bull; @endif
            @if(!empty($data['meta']['design_mix_ref'])) Design Mix Ref: <strong>{{ $data['meta']['design_mix_ref'] }}</strong> @endif
        </div>
    @endif

    @if (($pdfSettings['show_carrier_driver'] ?? true) && !empty($data['meta']['carrier_driver']) && $data['meta']['carrier_driver'] !== '-')
        <div style="padding: 5px 10px; border-bottom: 1px solid #cbd5e1; font-size: 10px; background: #f7f7f7;">
            <strong>Carrier - Driver:</strong> {{ $data['meta']['carrier_driver'] }}
        </div>
    @endif

    <table class="ledger-table">
        <thead>
            <tr>
                <th class="text-center" style="width:28px">#</th>
                <th class="text-left">Description</th>
                @if ($pdfSettings['show_pump_charges'] ?? true)
                    <th class="text-left" style="width:120px">Operation Type</th>
                    <th class="text-right" style="width:90px">Pump Charges</th>
                @endif
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
                <td>
                    <div class="item-name">{{ $item['name'] }}</div>
                    @if($item['description'])
                        <div class="item-sub">{{ $item['description'] }}</div>
                    @endif
                    @include('pdfs.partials._pump_rates_table', ['item' => $item])
                </td>
                @if ($pdfSettings['show_pump_charges'] ?? true)
                    <td class="text-left">{{ $item['operation_type'] ?? '-' }}</td>
                    <td class="text-right">{{ isset($item['pump_charge']) && $item['pump_charge'] > 0 ? number_format($item['pump_charge'], 2) : '-' }}</td>
                @endif
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
        @if ((($pdfSettings['show_pump_charges'] ?? true) || ($pdfSettings['pump_rates'] ?? true)) && isset($data['totals']['pump_rate']) && $data['totals']['pump_rate'] > 0)
        <div class="tl-row"><div class="tl-label">Concrete Pump Charges</div><div class="tl-val">{{ number_format($data['totals']['pump_rate'], 2) }}</div></div>
        @endif
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
    <div class="terms-text-content" style="padding:8px 12px;font-size:10px;border-top:1px solid #ccc;text-align:justify;white-space:normal !important;word-break:break-word;"><strong>Terms &amp; Conditions:</strong> {!! $data['meta']['terms_text'] !!}</div>
    @endif

    <div class="sig-row" style="margin-top:auto">
        <div class="sig-left">
            @if (($pdfSettings['show_bank_details'] ?? true) && !empty($data['company']['bank']['bank_name']))
                <div style="margin-bottom: 8px; font-size: 9.5px; color: #334155;">
                    <div style="font-weight: bold; text-transform: uppercase; color: #4f46e5; margin-bottom: 2px;">Bank Information</div>
                    <div>Account Name: <strong>{{ $data['company']['bank']['account_name'] }}</strong></div>
                    <div>Account Number: <strong>{{ $data['company']['bank']['account_number'] }}</strong></div>
                    <div>Bank: <strong>{{ $data['company']['bank']['bank_name'] }}</strong> (Branch: {{ $data['company']['bank']['branch'] }})</div>
                    <div>IFSC Code: <strong>{{ $data['company']['bank']['ifsc_code'] }}</strong></div>
                </div>
            @endif
        </div>
        <div class="sig-right" style="padding-bottom:10px">
            @if (($pdfSettings['show_seal_signature'] ?? true) && !empty($data['company']['seal_sign_path']))
                @php
                    $sealPath = ltrim(str_replace(['public/', 'storage/', '/storage/'], '', $data['company']['seal_sign_path']), '/');
                    $sealUrl = (request()->route('action') !== 'download' && !($is_pdf ?? false)) ? asset('storage/' . $sealPath) : public_path('storage/' . $sealPath);
                @endphp
                <div style="text-align: center; margin-bottom: 2px;">
                    <img src="{{ $sealUrl }}" style="max-height: 50px; max-width: 100px; object-fit: contain;" />
                </div>
            @endif
            <div class="sig-line">Authorized Signatory<br><span style="font-size:9px">For {{ $data['company']['name'] }}</span></div>
        </div>
    </div>

    @include('pdfs.partials._footer')
</div>
</body>
</html>
