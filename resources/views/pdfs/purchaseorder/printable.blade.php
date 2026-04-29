<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Purchase Order - {{ $order->ref_no }}</title>
    <style>
        /* ═══════════════════════════════════════════
           BASE — Elite Template (matching Elite.vue)
        ═══════════════════════════════════════════ */
        @page { margin: 0; size: A4; }

        * { box-sizing: border-box; margin: 0; padding: 0; }

        body {
            font-family: Arial, Helvetica, sans-serif;
            font-size: 11px;
            color: #111;
            background: #fff;
            line-height: 1.5;
        }

        .inv-root {
            border: 1px solid #aaa;
            width: 100%;
            min-height: 297mm;
            display: flex;
            flex-direction: column;
        }

        /* ── HEADER ── */
        .inv-header {
            display: table;
            width: 100%;
            border-bottom: 1px solid #aaa;
            padding: 10px 14px;
        }

        .header-left  { display: table-cell; vertical-align: top; }
        .header-right { display: table-cell; vertical-align: top; text-align: right; }

        .co-name {
            font-size: 15px;
            font-weight: 700;
            color: #111;
            margin-bottom: 1px;
        }

        .co-detail {
            font-size: 10px;
            color: #666;
            line-height: 1.45;
        }

        .inv-title {
            font-size: 26px;
            font-weight: 900;
            color: #111;
            line-height: 1.1;
        }

        .inv-ref {
            font-size: 10.5px;
            color: #666;
            margin-top: 2px;
        }

        /* ── DETAILS BAR (2-col) ── */
        .details-table {
            width: 100%;
            border-collapse: collapse;
            border-bottom: 1px solid #aaa;
        }

        .details-cell {
            padding: 7px 12px;
            vertical-align: top;
            border-right: 1px solid #aaa;
            width: 50%;
        }

        .no-right-border { border-right: none; }

        .kv-table  { border-collapse: collapse; width: 100%; }
        .kv-key    { color: #555; white-space: nowrap; padding: 1px 0; font-size: 11px; min-width: 110px; }
        .kv-sep    { padding: 1px 6px; color: #555; }
        .kv-val    { color: #111; font-size: 11px; }
        .kv-val.bold { font-weight: 700; }

        /* ── BILL TO / SHIP TO ── */
        .addr-table {
            width: 100%;
            border-collapse: collapse;
            border-bottom: 1px solid #aaa;
        }

        .addr-th {
            background: #f0f0f0;
            border-bottom: 1px solid #aaa;
            padding: 5px 12px;
            font-weight: 700;
            font-size: 11px;
            color: #111;
            text-align: left;
            width: 50%;
        }

        .addr-th-left  { border-right: 1px solid #aaa; }

        .addr-cell  { padding: 7px 12px; vertical-align: top; font-size: 11px; }
        .addr-left  { border-right: 1px solid #aaa; width: 50%; }

        .addr-name { font-weight: 700; color: #111; margin-bottom: 1px; }
        .addr-line { color: #444; line-height: 1.5; }
        .addr-sub  { color: #888; font-size: 10px; margin-top: 2px; }

        /* ── SUBJECT ROW ── */
        .subject-row {
            padding: 4px 12px;
            border-bottom: 1px solid #aaa;
            font-size: 11px;
            background: #fafafa;
            color: #444;
        }

        /* ── ITEMS TABLE ── */
        .items-table {
            width: 100%;
            border-collapse: collapse;
            border-bottom: 1px solid #aaa;
            font-size: 10.5px;
        }

        .items-table thead tr {
            background: #3a3a3a;
            color: #fff;
        }

        .items-table th {
            padding: 6px 8px;
            font-weight: 700;
            font-size: 10px;
            border: none;
        }

        .items-table td {
            padding: 5px 8px;
            vertical-align: top;
            border-bottom: 1px solid #e0e0e0;
            color: #333;
        }

        .items-table tbody tr:last-child td { border-bottom: 1px solid #ccc; }

        .item-name { font-weight: 700; color: #111; }
        .item-sub  { font-size: 9.5px; color: #888; margin-top: 1px; }
        .badge-done    { color: #16a34a; font-weight: 700; }
        .badge-pending { color: #d97706; font-weight: 700; }

        .text-center { text-align: center; }
        .text-right  { text-align: right; }
        .text-left   { text-align: left; }

        /* ── TOTALS BLOCK ── */
        .totals-split { display: table; width: 100%; border-bottom: 1px solid #aaa; min-height: 100px; }

        .totals-left {
            display: table-cell;
            vertical-align: top;
            padding: 8px 12px;
            border-right: 1px solid #aaa;
            font-size: 11px;
            width: 55%;
        }

        .tow-label { color: #555; font-size: 10.5px; margin-bottom: 3px; }
        .tow-value { font-style: italic; font-weight: 700; color: #111; line-height: 1.5; margin-bottom: 8px; }

        .totals-right { display: table-cell; vertical-align: top; width: 45%; }

        .breakdown-table { width: 100%; border-collapse: collapse; font-size: 11px; }
        .breakdown-table td { padding: 3px 10px; vertical-align: middle; }

        .bt-label { text-align: right; color: #444; padding-right: 16px !important; width: 58%; }
        .bt-val   { text-align: right; color: #333; white-space: nowrap; }

        .bt-total-row { border-top: 1px solid #aaa; border-bottom: 1px solid #aaa; }
        .bt-total-row td { padding-top: 5px !important; padding-bottom: 5px !important; }

        .bt-balance-row { background: #f2f2f2; }
        .bt-balance-row td { padding-top: 5px !important; padding-bottom: 5px !important; }

        .bold { font-weight: 700; color: #111; }
        .red  { color: #cc0000; }

        /* ── NOTES / TERMS ── */
        .bottom-section { padding: 10px 12px; border-bottom: 1px solid #aaa; font-size: 11px; }
        .section-label  { color: #888; font-size: 10.5px; margin-bottom: 2px; }
        .section-text   { color: #444; line-height: 1.5; }

        /* ── SIGNATURE ── */
        .signature-area { min-height: 80px; padding: 10px 12px; border-bottom: 1px solid #aaa; position: relative; }
        .sig-right { text-align: right; margin-top: 60px; }
        .sig-line  { display: inline-block; width: 160px; border-top: 1px solid #999; padding-top: 4px; text-align: center; font-size: 10.5px; color: #555; }

        /* ── FOOTER ── */
        .inv-footer {
            display: table;
            width: 100%;
            padding: 5px 12px;
            margin-top: auto;
        }

        .footer-left  { display: table-cell; vertical-align: middle; font-size: 9px; color: #999; text-transform: uppercase; letter-spacing: 0.05em; }
        .footer-right { display: table-cell; vertical-align: middle; text-align: right; font-size: 10px; color: #888; }
        .footer-brand { font-size: 10px; font-weight: 700; color: #555; }
    </style>
</head>
<body>
<div class="inv-root">

    <!-- ═══════════════════════════════════════
         HEADER
    ════════════════════════════════════════ -->
    <div class="inv-header">
        <div class="header-left">
            <div class="co-name">{{ $order->plant->entity->entity_name ?? $order->plant->name }}</div>
            <div class="co-detail">{{ $order->plant->address }}</div>
            <div class="co-detail">{{ $order->plant->city }}, {{ $order->plant->state }} - {{ $order->plant->pincode }}</div>
            <div class="co-detail">GSTIN: {{ $order->plant->gstin ?? 'N/A' }}</div>
        </div>
        <div class="header-right">
            <div class="inv-title">PURCHASE ORDER</div>
            <div class="inv-ref">PO# <strong>{{ $order->ref_no }}</strong></div>
        </div>
    </div>

    <!-- ═══════════════════════════════════════
         DETAILS BAR (2-col: PO Details | Delivery Info)
    ════════════════════════════════════════ -->
    <table class="details-table">
        <tbody>
            <tr>
                <td class="details-cell">
                    <table class="kv-table">
                        <tr>
                            <td class="kv-key">PO Date</td>
                            <td class="kv-sep">:</td>
                            <td class="kv-val bold">{{ $order->date_order?->format('d/m/Y') ?? 'N/A' }}</td>
                        </tr>
                        <tr>
                            <td class="kv-key">Delivery Date</td>
                            <td class="kv-sep">:</td>
                            <td class="kv-val bold">{{ $order->date_planned?->format('d/m/Y') ?? 'N/A' }}</td>
                        </tr>
                        <tr>
                            <td class="kv-key">Due Date</td>
                            <td class="kv-sep">:</td>
                            <td class="kv-val bold">{{ $order->due_date?->format('d/m/Y') ?? 'N/A' }}</td>
                        </tr>
                        <tr>
                            <td class="kv-key">Currency</td>
                            <td class="kv-sep">:</td>
                            <td class="kv-val bold">{{ $order->currency->currency_code ?? 'INR' }}</td>
                        </tr>
                    </table>
                </td>
                <td class="details-cell no-right-border">
                    <table class="kv-table">
                        <tr>
                            <td class="kv-key">PO Number</td>
                            <td class="kv-sep">:</td>
                            <td class="kv-val bold">{{ $order->po_number }}</td>
                        </tr>
                        <tr>
                            <td class="kv-key">Project / Site</td>
                            <td class="kv-sep">:</td>
                            <td class="kv-val bold">{{ $order->plant->name }}</td>
                        </tr>
                        <tr>
                            <td class="kv-key">Status</td>
                            <td class="kv-sep">:</td>
                            <td class="kv-val bold">{{ strtoupper($order->state ?? 'DRAFT') }}</td>
                        </tr>
                        <tr>
                            <td class="kv-key">Receipt Status</td>
                            <td class="kv-sep">:</td>
                            <td class="kv-val bold">{{ $order->receipt_status > 0 ? 'Partially Received' : 'Pending' }}</td>
                        </tr>
                    </table>
                </td>
            </tr>
        </tbody>
    </table>

    <!-- ═══════════════════════════════════════
         VENDOR (Bill To) / DELIVERY (Ship To)
    ════════════════════════════════════════ -->
    <table class="addr-table">
        <thead>
            <tr>
                <th class="addr-th addr-th-left">Vendor Details</th>
                <th class="addr-th">Delivery Address</th>
            </tr>
        </thead>
        <tbody>
            <tr>
                <td class="addr-cell addr-left">
                    <div class="addr-name">{{ $order->vendor->legal_name }}</div>
                    <div class="addr-line">{{ $order->vendor->address_line1 }}</div>
                    <div class="addr-line">{{ $order->vendor->city }}, {{ $order->vendor->state }}</div>
                    @if($order->vendor->gstin)
                        <div class="addr-sub">GSTIN: {{ $order->vendor->gstin }}</div>
                    @endif
                    @if($order->vendor->phone)
                        <div class="addr-sub">Phone: {{ $order->vendor->phone }}</div>
                    @endif
                </td>
                <td class="addr-cell">
                    <div class="addr-name">{{ $order->plant->entity->entity_name ?? $order->plant->name }}</div>
                    <div class="addr-line">{{ $order->plant->address }}</div>
                    <div class="addr-line">{{ $order->plant->city }}, {{ $order->plant->state }} - {{ $order->plant->pincode }}</div>
                    @if($order->plant->site_incharge)
                        <div class="addr-sub">Site Incharge: {{ $order->plant->site_incharge }}</div>
                    @endif
                    @if($order->plant->contact_no)
                        <div class="addr-sub">Contact: {{ $order->plant->contact_no }}</div>
                    @endif
                </td>
            </tr>
        </tbody>
    </table>

    <!-- SUBJECT ROW -->
    <div class="subject-row">
        &nbsp;&nbsp;Subject : Purchase of materials as per specification below
    </div>

    <!-- ═══════════════════════════════════════
         ITEMS TABLE — Dark header, real PO data
    ════════════════════════════════════════ -->
    <table class="items-table">
        <thead>
            <tr>
                <th class="text-center" style="width:28px;">#</th>
                <th class="text-left"  style="width:auto;">Product &amp; Description</th>
                <th class="text-center" style="width:60px;">HSN</th>
                <th class="text-right"  style="width:55px;">Qty</th>
                <th class="text-right"  style="width:55px;">Recd</th>
                <th class="text-center" style="width:45px;">Unit</th>
                <th class="text-right"  style="width:80px;">Rate ({{ $order->currency->currency_symbol ?? '₹' }})</th>
                <th class="text-center" style="width:80px;">Tax</th>
                <th class="text-right"  style="width:80px;">Tax Amt</th>
                <th class="text-right"  style="width:85px;">Total ({{ $order->currency->currency_symbol ?? '₹' }})</th>
            </tr>
        </thead>
        <tbody>
            @foreach($order->items as $idx => $item)
            <tr>
                <td class="text-center">{{ $idx + 1 }}</td>
                <td>
                    <div class="item-name">{{ $item->product->title }}</div>
                    @if($item->description)
                        <div class="item-sub">{{ $item->description }}</div>
                    @endif
                </td>
                <td class="text-center">{{ $item->product->hsn_code ?? '-' }}</td>
                <td class="text-right bold">{{ number_format($item->product_quantity, 2) }}</td>
                <td class="text-right">
                    <span class="{{ $item->received_quantity >= $item->product_quantity ? 'badge-done' : 'badge-pending' }}">
                        {{ number_format($item->received_quantity ?? 0, 2) }}
                    </span>
                </td>
                <td class="text-center">{{ $item->uom->unit_code }}</td>
                <td class="text-right">{{ number_format($item->unit_price, 2) }}</td>
                <td class="text-center" style="font-size:9.5px;">
                    @if($item->tax && $item->tax->tax_group === 'GST')
                        {{ (float)$item->tax->tax_rate / 2 }}% CGST<br>
                        {{ (float)$item->tax->tax_rate / 2 }}% SGST
                    @elseif($item->tax)
                        {{ $item->tax->tax_name }} {{ (float)$item->tax->tax_rate }}%
                    @else
                        -
                    @endif
                </td>
                <td class="text-right" style="font-size:9.5px;">
                    @if($item->tax && $item->tax->tax_group === 'GST')
                        {{ number_format($item->price_tax / 2, 2) }}<br>
                        {{ number_format($item->price_tax / 2, 2) }}
                    @else
                        {{ number_format($item->price_tax ?? 0, 2) }}
                    @endif
                </td>
                <td class="text-right bold">{{ number_format($item->price_total, 2) }}</td>
            </tr>
            @endforeach
        </tbody>
    </table>

    <!-- ═══════════════════════════════════════
         TOTALS (split: left = words, right = breakdown)
    ════════════════════════════════════════ -->
    <div class="totals-split">
        <!-- LEFT: Total in Words -->
        <div class="totals-left">
            <div class="tow-label">Total in Words</div>
            <div class="tow-value">
                {{ $order->currency->currency_name ?? 'Indian Rupee' }} —
                @php
                    // Simple number-to-words (a helper or just display the amount)
                    echo number_format($order->amount_total, 2) . ' ' . ($order->currency->currency_code ?? 'INR') . ' Only';
                @endphp
            </div>
        </div>

        <!-- RIGHT: Breakdown -->
        <div class="totals-right">
            <table class="breakdown-table">
                <tr>
                    <td class="bt-label">Untaxed Sub Total</td>
                    <td class="bt-val">{{ $order->currency->currency_symbol ?? '₹' }} {{ number_format($order->amount_untaxed, 2) }}</td>
                </tr>

                @php
                    $taxGroups = [];
                    foreach($order->items as $item) {
                        if (!$item->tax) continue;
                        $g = $item->tax->tax_group;
                        $taxGroups[$g] = ($taxGroups[$g] ?? 0) + $item->price_tax;
                    }
                @endphp

                @foreach($taxGroups as $group => $amt)
                    @if($group === 'GST')
                        <tr>
                            <td class="bt-label">CGST</td>
                            <td class="bt-val">{{ $order->currency->currency_symbol ?? '₹' }} {{ number_format($amt / 2, 2) }}</td>
                        </tr>
                        <tr>
                            <td class="bt-label">SGST</td>
                            <td class="bt-val">{{ $order->currency->currency_symbol ?? '₹' }} {{ number_format($amt / 2, 2) }}</td>
                        </tr>
                    @else
                        <tr>
                            <td class="bt-label">{{ $group }}</td>
                            <td class="bt-val">{{ $order->currency->currency_symbol ?? '₹' }} {{ number_format($amt, 2) }}</td>
                        </tr>
                    @endif
                @endforeach

                @if($order->shipping_charges > 0)
                    <tr>
                        <td class="bt-label">Shipping / Freight (+)</td>
                        <td class="bt-val">{{ $order->currency->currency_symbol ?? '₹' }} {{ number_format($order->shipping_charges, 2) }}</td>
                    </tr>
                @endif
                @if($order->discount_amount > 0)
                    <tr>
                        <td class="bt-label red">Discount (-)</td>
                        <td class="bt-val red">{{ $order->currency->currency_symbol ?? '₹' }} {{ number_format($order->discount_amount, 2) }}</td>
                    </tr>
                @endif

                <tr class="bt-total-row">
                    <td class="bt-label bold">Total ({{ $order->currency->currency_code ?? 'INR' }})</td>
                    <td class="bt-val bold">{{ $order->currency->currency_symbol ?? '₹' }} {{ number_format($order->amount_total, 2) }}</td>
                </tr>
                <tr class="bt-balance-row">
                    <td class="bt-label bold">Net Payable</td>
                    <td class="bt-val bold">{{ $order->currency->currency_symbol ?? '₹' }} {{ number_format($order->amount_total, 2) }}</td>
                </tr>
            </table>
        </div>
    </div>

    <!-- ═══════════════════════════════════════
         NOTES
    ════════════════════════════════════════ -->
    <div class="bottom-section">
        <div class="section-label">Notes</div>
        <div class="section-text">{{ $order->notes ?? 'Please ensure delivery as per the agreed specifications and timelines.' }}</div>
    </div>

    <!-- ═══════════════════════════════════════
         TERMS & CONDITIONS
    ════════════════════════════════════════ -->
    <div class="bottom-section">
        <div class="section-label">Terms &amp; Conditions</div>
        <div class="section-text" style="font-size:10px;">
            {!! nl2br(e($order->terms_conditions ?? 'Standard business terms apply. Goods must be delivered in perfect condition. Payment within 30 days of invoice.')) !!}
        </div>
    </div>

    <!-- ═══════════════════════════════════════
         SIGNATURE AREA
    ════════════════════════════════════════ -->
    <div class="signature-area">
        <div class="sig-right">
            <div class="sig-line">Authorized Signatory<br><small style="color:#aaa;font-size:9px;">For {{ $order->plant->entity->entity_name ?? $order->plant->name }}</small></div>
        </div>
    </div>

    <!-- ═══════════════════════════════════════
         FOOTER
    ════════════════════════════════════════ -->
    <div class="inv-footer">
        <div class="footer-left">
            POWERED BY &nbsp;<span class="footer-brand">onemodo.com</span>
            &nbsp;&bull;&nbsp; Generated: {{ now()->format('d/m/Y H:i') }}
        </div>
        <div class="footer-right">1</div>
    </div>

</div>
</body>
</html>
