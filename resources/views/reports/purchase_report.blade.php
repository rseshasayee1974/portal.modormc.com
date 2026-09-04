<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Purchase Report</title>
    <style>
        {!! $css ?? $report_css ?? (file_exists(public_path('css/reports/purchase_report.css')) ? file_get_contents(public_path('css/reports/purchase_report.css')) : (file_exists(public_path('css/reports/report_pdf.css')) ? file_get_contents(public_path('css/reports/report_pdf.css')) : '')) !!}
    </style>
</head>
<body>

    <!-- Header section with logo and addresses in a borderless table layout -->
    <table class="header-table">
        <tr>
            <td style="width: 52%;">
                <!-- Client 'To' details if a specific patron is loaded -->
                @if($patron)
                    <div class="address-box" style="margin-top: 5px;">
                        <span class="address-title">Vendor:</span>
                        <span class="address-name">{{ $patron->legal_name }}</span>
                        @if($patron->addresses->isNotEmpty())
                            @php $pAddr = $patron->addresses->first(); @endphp
                            {{ $pAddr->line_1 ?? '' }}@if($pAddr->line_2), {{ $pAddr->line_2 }}@endif<br>
                            {{ $pAddr->city ?? '' }} - {{ $pAddr->zipcode ?? '' }}<br>
                        @endif
                        <strong>GSTIN/UIN # :</strong> {{ $patron->gstin ?? '' }}<br>
                        <strong>Contact #:</strong> {{ $patron->mobile_number ?? ($patron->contacts->first()->mobile ?? '0') }}
                    </div>
                @else
                    <div class="address-box" style="margin-top: 5px;">
                        <span class="address-title">Report Target:</span>
                        <span class="address-name">All Vendors (Consolidated Overview)</span>
                    </div>
                @endif
            </td>
            
            <td style="width: 48%; text-align: right;">
                <!-- Company / Plant details -->
                <div class="address-box" style="margin-top: 5px; padding-left: 20px;">
                    <span class="address-title">Address:</span>
                    <span class="address-name">{{ $plant->name ?? 'DEMO LOGIN' }}</span>
                    @if($plant && $plant->addresses->isNotEmpty())
                        @php $plAddr = $plant->addresses->first(); @endphp
                        {{ $plAddr->line_1 ?? '' }}<br>
                        @if($plAddr->line_2){{ $plAddr->line_2 }}<br>@endif
                        {{ $plAddr->city ?? '' }}, {{ $plAddr->state->state_name ?? $plAddr->state_code ?? '' }} - {{ $plAddr->zipcode ?? '' }}<br>
                    @else
                        3/150, Akkiyampatti (Po),<br>
                        Sendamangalam (Tk),<br>
                        Namakkal (Dt), Tamil Nadu - 637409<br>
                    @endif
                    <strong>GSTIN/UIN :</strong> {{ $plant->gstin ?? '' }}<br>
                    {{-- <strong>MSME - UDYAM-</strong> {{ $plant->msme_no ?? '' }} --}}
                </div>
            </td>
        </tr>
    </table>

    <!-- Purchase Report Title -->
    <div class="statement-title-container">
        <h2 class="statement-title">Purchase Statement of Accounts</h2>
        <span class="statement-period">Period: {{ $start }} to {{ $end }}</span>
    </div>

    <!-- Section 1: Purchase Order wise Details -->
    <div class="statement-title-container" style="margin-top: 20px; border-bottom: 1.5px solid #cbd5e1; padding-bottom: 3px;">
        <h3 style="font-size: 11pt; font-weight: bold; color: #334155; margin: 0; text-transform: uppercase;">1. Purchase Order wise Details</h3>
    </div>

    <table class="data-table">
        <thead>
            <tr>
                <th width="5%">#</th>
                <th width="15%">Date</th>
                <th width="20%">PO Number</th>
                <th width="24%">Supplier / Vendor</th>
                <th width="12%">Taxable Amt (₹)</th>
                <th width="12%">Tax Amt (₹)</th>
                <th width="12%">Total Amt (₹)</th>
            </tr>
        </thead>
        <tbody>
            @foreach($transactions as $index => $row)
                <tr>
                    <td class="text-center">{{ $index + 1 }}</td>
                    <td class="text-center">{{ \Carbon\Carbon::parse($row['date'])->format('d-m-Y') }}</td>
                    <td class="font-bold text-center">{{ $row['po_number'] }}</td>
                    <td>{{ $row['vendor_name'] }}</td>
                    <td class="text-right">{{ number_format($row['amount_untaxed'], 2) }}</td>
                    <td class="text-right">{{ number_format($row['amount_tax'], 2) }}</td>
                    <td class="text-right font-bold">{{ number_format($row['amount_total'], 2) }}</td>
                </tr>
            @endforeach
            
            <!-- Grand Totals -->
            <tr class="total-row">
                <td colspan="4" class="text-center font-bold">Total Details</td>
                <td class="text-right font-bold">{{ number_format($total_untaxed, 2) }}</td>
                <td class="text-right font-bold">{{ number_format($total_tax, 2) }}</td>
                <td class="text-right font-bold">{{ number_format($total_amount, 2) }}</td>
            </tr>
        </tbody>
    </table>

    <!-- Page Break for Cleanliness -->
    <div style="page-break-before: always;"></div>

    <!-- Section 2: Product wise Consolidated Summary -->
    <div class="statement-title-container" style="margin-top: 15px; border-bottom: 1.5px solid #cbd5e1; padding-bottom: 3px;">
        <h3 style="font-size: 11pt; font-weight: bold; color: #334155; margin: 0; text-transform: uppercase;">2. Product wise Consolidated Summary</h3>
    </div>

    <table class="data-table">
        <thead>
            <tr>
                <th width="5%">#</th>
                <th width="40%">Product Name</th>
                <th width="10%">UOM</th>
                <th width="10%">Quantity</th>
                <th width="11%">Avg Rate (₹)</th>
                <th width="12%">Taxable Amt (₹)</th>
                <th width="12%">Tax Amt (₹)</th>
                <th width="12%">Total Amt (₹)</th>
            </tr>
        </thead>
        <tbody>
            @foreach($product_summary as $index => $row)
                <tr>
                    <td class="text-center">{{ $index + 1 }}</td>
                    <td class="font-bold">{{ $row['product_name'] }}</td>
                    <td class="text-center">{{ $row['uom'] }}</td>
                    <td class="text-right">{{ number_format($row['quantity'], 2) }}</td>
                    <td class="text-right">{{ number_format($row['avg_rate'], 2) }}</td>
                    <td class="text-right">{{ number_format($row['amount_untaxed'], 2) }}</td>
                    <td class="text-right">{{ number_format($row['amount_tax'], 2) }}</td>
                    <td class="text-right font-bold">{{ number_format($row['amount_total'], 2) }}</td>
                </tr>
            @endforeach
            
            <!-- Grand Totals -->
            <tr class="total-row">
                <td colspan="3" class="text-center font-bold">Total Summary</td>
                <td class="text-right font-bold">{{ number_format($total_quantity, 2) }}</td>
                <td></td>
                <td class="text-right font-bold">{{ number_format($total_product_untaxed, 2) }}</td>
                <td class="text-right font-bold">{{ number_format($total_product_tax, 2) }}</td>
                <td class="text-right font-bold">{{ number_format($total_product_amount, 2) }}</td>
            </tr>
        </tbody>
    </table>

</body>
</html>
