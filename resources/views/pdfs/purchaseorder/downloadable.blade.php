<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Purchase Order - {{ $order->po_number }}</title>
    <style>
        @page { margin: 15px; }
        body { font-family: 'Helvetica', 'Arial', sans-serif; font-size: 11px; line-height: 1.3; color: #333; margin: 0; padding: 0; }
        
        .premium-border { border: 2px solid #ccc; width: 100%; border-radius: 8px; overflow: hidden; background: white; }
        
        .header-strip { background: linear-gradient(to right, #2c3e50, #4ca1af); height: 50px; position: relative; }
        .po-title-block { position: absolute; right: 0; top: 0; background: #1e3a5f; color: white; padding: 12px 30px; font-weight: 900; font-size: 18px; text-transform: uppercase; letter-spacing: 1px; }
        
        .grid-row { display: table; width: 100%; border-bottom: 1px solid #ccc; }
        .grid-col { display: table-cell; width: 50%; vertical-align: top; padding: 15px; border-right: 1px solid #ccc; }
        .grid-col:last-child { border-right: none; }
        
        .header-label { color: #2c3e50; font-weight: 900; font-size: 12px; text-transform: uppercase; margin-bottom: 8px; border-bottom: 1px solid #eee; padding-bottom: 4px; display: block; }
        
        .blue-text { color: #2980b9; font-weight: bold; font-size: 14px; }
        .muted-text { color: #666; font-size: 10px; }
        .bold-val { font-weight: bold; color: #111; }
        
        .items-table { width: 100%; border-collapse: collapse; }
        .items-table th { background: #f4f6f7; border: 1px solid #ccc; padding: 10px 5px; text-align: left; font-size: 11px; font-weight: bold; color: #2c3e50; }
        .items-table td { border: 1px solid #ccc; padding: 10px 5px; vertical-align: middle; }
        
        .totals-container { background: #fdfdfd; }
        .tot-row { display: table; width: 100%; }
        .tot-lbl { display: table-cell; width: 80%; text-align: right; padding: 8px 20px; font-weight: bold; border-right: 1px solid #ccc; border-bottom: 1px solid #ccc; font-size: 11px; }
        .tot-val { display: table-cell; width: 20%; text-align: right; padding: 8px 20px; font-weight: 900; border-bottom: 1px solid #ccc; font-size: 12px; }
        
        .final-amount { color: #c0392b; font-size: 15px !important; }
        
        .footer-sec { margin-top: 20px; padding: 0 15px; }
        .sign-box { border-top: 1px solid #333; margin-top: 40px; display: inline-block; width: 180px; text-align: center; padding-top: 5px; font-weight: bold; }
        
        .status-pill { padding: 2px 8px; border-radius: 12px; font-size: 9px; font-weight: bold; text-transform: uppercase; }
        .status-complete { background: #e6fffa; color: #234e52; border: 1px solid #b2f5ea; }
        .status-partial { background: #fffaf0; color: #7b341e; border: 1px solid #feebc8; }
    </style>
</head>
<body>
    <div class="premium-border">
        <!-- Brand Strip -->
        <div class="header-strip">
            <div class="po-title-block">Purchase Order</div>
        </div>

        <!-- Vendor & Billing Row -->
        <div class="grid-row">
            <div class="grid-col">
                <span class="header-label">Vendor Details:</span>
                <div class="blue-text">{{ $order->vendor->legal_name }}</div>
                <div class="muted-text" style="font-size: 11px; margin-top: 3px;">
                    {{ $order->vendor->address_line1 }}<br>
                    {{ $order->vendor->city }}, {{ $order->vendor->state }}<br>
                    Tamil Nadu GSTIN # : <span class="bold-val">{{ $order->vendor->gstin }}</span>
                </div>
            </div>
            <div class="grid-col" style="text-align: right;">
                <span class="header-label" style="text-align: left;">Billing Address:</span>
                <div class="blue-text">{{ $order->plant->entity->entity_name ?? 'KMC Associates' }}</div>
                <div class="muted-text" style="font-size: 11px; margin-top: 3px;">
                    {{ $order->plant->address }}<br>
                    {{ $order->plant->city }} | {{ $order->plant->state }} - {{ $order->plant->pincode }}<br>
                    GSTIN : <span class="bold-val">{{ $order->plant->gstin }}</span><br>
                    PHONE : <span class="bold-val">{{ $order->plant->phone ?? 'N/A' }}</span>
                </div>
            </div>
        </div>

        <!-- Delivery & Project Row -->
        <div class="grid-row">
            <div class="grid-col">
                <span class="header-label">Delivery Address:</span>
                <div style="font-size: 11px;">
                    Address : <span class="muted-text">{{ $order->plant->address }}</span><br>
                    City : <span class="muted-text">{{ $order->plant->city }}, {{ $order->plant->state }} - {{ $order->plant->pincode }}</span><br>
                    Site Person : <span class="bold-val">{{ $order->plant->site_incharge ?? 'N/A' }}</span><br>
                    Site Contact : <span class="bold-val">{{ $order->plant->contact_no ?? 'N/A' }}</span>
                </div>
            </div>
            <div class="grid-col">
                <span class="header-label">Project Details:</span>
                <div style="font-size: 11px;">
                    Project Name : <span class="bold-val">{{ $order->plant->name }}</span><br>
                    P.O Ref # : <span class="bold-val">{{ $order->po_number }} / {{ $order->ref_no }}</span><br>
                    P.O Date : <span class="bold-val">{{ $order->date_order->format('d-m-Y') }}</span><br>
                    Delivery Date : <span class="bold-val">{{ $order->date_planned ? $order->date_planned->format('d-m-Y') : 'N/A' }}</span>
                </div>
            </div>
        </div>

        <!-- Line Items Table -->
        <table class="items-table">
            <thead>
                <tr>
                    <th width="3%" style="text-align: center;">#</th>
                    <th width="32%">Product & Description</th>
                    <th width="8%" style="text-align: center;">HSN</th>
                    <th width="8%" style="text-align: center;">Qty</th>
                    <th width="8%" style="text-align: center;">Recd</th>
                    <th width="6%" style="text-align: center;">Units</th>
                    <th width="10%" style="text-align: right;">Price ({{ $order->currency->currency_symbol ?? '₹' }})</th>
                    <th width="5%" style="text-align: center;">Tax</th>
                    <th width="10%" style="text-align: right;">Tax Amt</th>
                    <th width="10%" style="text-align: right;">Net Total</th>
                </tr>
            </thead>
            <tbody>
                @foreach($order->items as $idx => $item)
                <tr>
                    <td style="text-align: center;">{{ $idx + 1 }}</td>
                    <td>
                        <div style="font-weight: 800; color: #1e3a5f;">{{ $item->product->title }}</div>
                        <div style="font-size: 8px; color: #777;">{{ $item->description }}</div>
                    </td>
                    <td style="text-align: center; font-family: monospace;">{{ $item->product->hsn_code ?? '-' }}</td>
                    <td style="text-align: center; font-weight: bold;">{{ number_format($item->product_quantity, 2) }}</td>
                    <td style="text-align: center;">
                        <span class="status-pill {{ $item->received_quantity >= $item->product_quantity ? 'status-complete' : 'status-partial' }}">
                            {{ number_format($item->received_quantity ?? 0, 2) }}
                        </span>
                    </td>
                    <td style="text-align: center;">{{ $item->uom->unit_code }}</td>
                    <td style="text-align: right;">{{ number_format($item->unit_price, 2) }}</td>
                    <td style="text-align: center; font-size: 9px;">
                        @if($item->tax && $item->tax->tax_group === 'GST')
                            {{ (float)$item->tax->tax_rate / 2 }}% CGST<br>
                            {{ (float)$item->tax->tax_rate / 2 }}% SGST
                        @else
                            {{ $item->tax->tax_name ?? 'Tax' }} ({{ (float)($item->tax->tax_rate ?? 0) }}%)
                        @endif
                    </td>
                    <td style="text-align: right;">
                        @if($item->tax && $item->tax->tax_group === 'GST')
                            {{ number_format($item->price_tax / 2, 2) }}<br>
                            {{ number_format($item->price_tax / 2, 2) }}
                        @else
                            {{ number_format($item->price_tax, 2) }}
                        @endif
                    </td>
                    <td style="text-align: right; font-weight: 800; color: #111;">{{ number_format($item->price_total, 2) }}</td>
                </tr>
                @endforeach
            </tbody>
        </table>

        <!-- Summary & Totals -->
        <div class="totals-container">
            <div class="tot-row">
                <div class="tot-lbl">Subtotal (Untaxed):</div>
                <div class="tot-val">{{ number_format($order->amount_untaxed, 2) }}</div>
            </div>
            
            @php
                $taxGroups = [];
                foreach($order->items as $item) {
                    if(!$item->tax) continue;
                    $group = $item->tax->tax_group;
                    $taxGroups[$group] = ($taxGroups[$group] ?? 0) + $item->price_tax;
                }
            @endphp

            @foreach($taxGroups as $group => $amount)
                @if($group === 'GST')
                    <div class="tot-row">
                        <div class="tot-lbl">CGST (Splitted):</div>
                        <div class="tot-val">{{ number_format($amount / 2, 2) }}</div>
                    </div>
                    <div class="tot-row">
                        <div class="tot-lbl">SGST (Splitted):</div>
                        <div class="tot-val">{{ number_format($amount / 2, 2) }}</div>
                    </div>
                @else
                    <div class="tot-row">
                        <div class="tot-lbl">{{ $group }}:</div>
                        <div class="tot-val">{{ number_format($amount, 2) }}</div>
                    </div>
                @endif
            @endforeach
            @if($order->shipping_charges > 0)
            <div class="tot-row">
                <div class="tot-lbl">Freight & Logistics (+):</div>
                <div class="tot-val">{{ number_format($order->shipping_charges, 2) }}</div>
            </div>
            @endif
            @if($order->discount_amount > 0)
            <div class="tot-row">
                <div class="tot-lbl">Adjusted Discount (-):</div>
                <div class="tot-val" style="color: #e74c3c;">{{ number_format($order->discount_amount, 2) }}</div>
            </div>
            @endif
            <div class="tot-row">
                <div class="tot-lbl final-amount">Net Payable Amount ({{ $order->currency->currency_code }}):</div>
                <div class="tot-val final-amount">{{ number_format($order->amount_total, 2) }}</div>
            </div>
        </div>
    </div>

    <!-- T&C and Signatures -->
    <div class="footer-sec">
        <div style="display: table; width: 100%;">
            <div style="display: table-cell; width: 65%; vertical-align: top; padding-right: 30px;">
                <div style="font-weight: 900; text-decoration: underline; font-size: 11px; margin-bottom: 8px; color: #2c3e50;">TERMS & CONDITIONS:</div>
                <div style="font-size: 9.5px; color: #444; text-align: justify;">
                    {!! nl2br(e($order->terms_conditions ?? "Standard ERP procurement terms apply. Please check quality before delivery.")) !!}
                </div>
            </div>
            <div style="display: table-cell; width: 35%; vertical-align: bottom; text-align: right;">
                <div style="margin-bottom: 45px; font-weight: 800;">Authorized Signatory</div>
                <div class="sign-box">For {{ $order->plant->name }}</div>
            </div>
        </div>
        
        <div style="text-align: center; margin-top: 30px; font-size: 9px; color: #999; border-top: 1px solid #eee; padding-top: 10px;">
            This is a system-generated Purchase Order and does not require a physical stamp. | Powered by ModoMines Cloud ERP
        </div>
    </div>
</body>
</html>
