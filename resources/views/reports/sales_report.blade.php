<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Sales Report</title>
    <style>
        @page {
            margin: 35px;
        }
        body {
            font-family: 'DejaVu Sans', sans-serif;
            font-size: 9pt;
            color: #1e293b;
            margin: 0;
            padding: 0;
            line-height: 1.4;
        }

        /* Layout header table */
        .header-table {
            width: 100%;
            border-collapse: collapse;
            border: 0;
            margin-bottom: 20px;
        }
        .header-table td {
            border: 0;
            padding: 0;
            vertical-align: top;
        }
        
        /* Address styling */
        .address-box {
            font-size: 8pt;
            color: #334155;
            line-height: 1.35;
        }
        .address-title {
            font-weight: bold;
            color: #64748b;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-bottom: 2px;
            display: block;
        }
        .address-name {
            font-weight: bold;
            font-size: 10.5pt;
            color: #0f172a;
            margin-bottom: 2px;
            display: block;
        }

        /* Statement Title Section */
        .statement-title-container {
            border-bottom: 2px solid #0284c7;
            padding-bottom: 5px;
            margin-bottom: 20px;
        }
        .statement-title {
            font-size: 13pt;
            font-weight: bold;
            color: #0369a1;
            margin: 0;
            text-transform: uppercase;
        }
        .statement-period {
            font-size: 9.5pt;
            color: #64748b;
            margin-top: 3px;
            display: block;
        }

        /* Data Table */
        table.data-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 15px;
            border: 1px solid #0284c7;
        }
        table.data-table th {
            background-color: #f0f9ff;
            color: #0369a1;
            border: 1px solid #38bdf8;
            padding: 8px 5px;
            font-size: 8pt;
            font-weight: bold;
            text-align: center;
            text-transform: uppercase;
        }
        table.data-table td {
            padding: 7px 8px;
            border: 1px solid #bae6fd;
            font-size: 8.5pt;
            vertical-align: middle;
        }
        
        /* Rows styling */
        .total-row {
            background-color: #f0f9ff;
            font-weight: bold;
            border-top: 2px solid #0284c7;
        }
        
        /* Helpers */
        .text-right { text-align: right; }
        .text-center { text-align: center; }
        .font-bold { font-weight: bold; }
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
                        <span class="address-title">Customer / Party:</span>
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
                        <span class="address-name">All Customers (Consolidated Overview)</span>
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
                    <strong>GSTIN/UIN #:</strong> {{ $plant->gstin ?? '' }}<br>
                    <strong>MSME - UDYAM-</strong> {{ $plant->msme_no ?? '' }}
                </div>
            </td>
        </tr>
    </table>

    <!-- Sales Report Title -->
    <div class="statement-title-container">
        <h2 class="statement-title">Sales Statement of Accounts</h2>
        <span class="statement-period">Period: {{ $start }} to {{ $end }}</span>
    </div>

    <!-- Section 1: Sales Invoice wise Details -->
    <div class="statement-title-container" style="margin-top: 20px; border-bottom: 1.5px solid #cbd5e1; padding-bottom: 3px;">
        <h3 style="font-size: 11pt; font-weight: bold; color: #334155; margin: 0; text-transform: uppercase;">1. Sales Invoice wise Details</h3>
    </div>

    <table class="data-table">
        <thead>
            <tr>
                <th width="5%">#</th>
                <th width="15%">Date</th>
                <th width="20%">Invoice Number</th>
                <th width="24%">Customer / Party</th>
                <th width="12%">Taxable Amt</th>
                <th width="12%">Tax Amt</th>
                <th width="12%">Total Amt</th>
            </tr>
        </thead>
        <tbody>
            @foreach($transactions as $index => $row)
                <tr>
                    <td class="text-center">{{ $index + 1 }}</td>
                    <td class="text-center">{{ \Carbon\Carbon::parse($row['date'])->format('d-m-Y') }}</td>
                    <td class="font-bold text-center">{{ $row['invoice_number'] }}</td>
                    <td>{{ $row['customer_name'] }}</td>
                    <td class="text-right">₹ {{ number_format($row['amount_untaxed'], 2) }}</td>
                    <td class="text-right">₹ {{ number_format($row['amount_tax'], 2) }}</td>
                    <td class="text-right font-bold">₹ {{ number_format($row['amount_total'], 2) }}</td>
                </tr>
            @endforeach
            
            <!-- Grand Totals -->
            <tr class="total-row">
                <td colspan="4" class="text-center font-bold">Total Details</td>
                <td class="text-right font-bold">₹ {{ number_format($total_untaxed, 2) }}</td>
                <td class="text-right font-bold">₹ {{ number_format($total_tax, 2) }}</td>
                <td class="text-right font-bold">₹ {{ number_format($total_amount, 2) }}</td>
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
                <th width="11%">Avg Rate</th>
                <th width="12%">Taxable Amt</th>
                <th width="12%">Tax Amt</th>
                <th width="12%">Total Amt</th>
            </tr>
        </thead>
        <tbody>
            @foreach($product_summary as $index => $row)
                <tr>
                    <td class="text-center">{{ $index + 1 }}</td>
                    <td class="font-bold">{{ $row['product_name'] }}</td>
                    <td class="text-center">{{ $row['uom'] }}</td>
                    <td class="text-right">{{ number_format($row['quantity'], 2) }}</td>
                    <td class="text-right">₹ {{ number_format($row['avg_rate'], 2) }}</td>
                    <td class="text-right">₹ {{ number_format($row['amount_untaxed'], 2) }}</td>
                    <td class="text-right">₹ {{ number_format($row['amount_tax'], 2) }}</td>
                    <td class="text-right font-bold">₹ {{ number_format($row['amount_total'], 2) }}</td>
                </tr>
            @endforeach
            
            <!-- Grand Totals -->
            <tr class="total-row">
                <td colspan="3" class="text-center font-bold">Total Summary</td>
                <td class="text-right font-bold">{{ number_format($total_quantity, 2) }}</td>
                <td></td>
                <td class="text-right font-bold">₹ {{ number_format($total_product_untaxed, 2) }}</td>
                <td class="text-right font-bold">₹ {{ number_format($total_product_tax, 2) }}</td>
                <td class="text-right font-bold">₹ {{ number_format($total_product_amount, 2) }}</td>
            </tr>
        </tbody>
    </table>

    <!-- Page Break for Cleanliness -->
    <div style="page-break-before: always;"></div>

    <!-- Section 3: Dispatch Consolidated - Mix Design & Concrete Grade wise -->
    <div class="statement-title-container" style="margin-top: 15px; border-bottom: 1.5px solid #cbd5e1; padding-bottom: 3px;">
        <h3 style="font-size: 11pt; font-weight: bold; color: #334155; margin: 0; text-transform: uppercase;">3. Dispatch Report (Mix Design & Concrete Grade wise Consolidated)</h3>
    </div>

    <table class="data-table">
        <thead>
            <tr>
                <th width="5%">#</th>
                <th width="30%">Mix Design Name</th>
                <th width="15%">Concrete Grade</th>
                <th width="8%">UOM</th>
                <th width="10%">Quantity</th>
                <th width="10%">Avg Rate</th>
                <th width="11%">Taxable Amt</th>
                <th width="11%">Tax Amt</th>
                <th width="11%">Total Amt</th>
            </tr>
        </thead>
        <tbody>
            @foreach($mix_design_summary as $index => $row)
                <tr>
                    <td class="text-center">{{ $index + 1 }}</td>
                    <td class="font-bold">{{ $row['mix_name'] }}</td>
                    <td class="text-center font-bold text-slate-700">{{ $row['concrete_grade'] }}</td>
                    <td class="text-center">{{ $row['uom'] }}</td>
                    <td class="text-right font-bold">{{ number_format($row['quantity'], 2) }}</td>
                    <td class="text-right">₹ {{ number_format($row['avg_rate'], 2) }}</td>
                    <td class="text-right">₹ {{ number_format($row['amount_untaxed'], 2) }}</td>
                    <td class="text-right">₹ {{ number_format($row['amount_tax'], 2) }}</td>
                    <td class="text-right font-bold">₹ {{ number_format($row['amount_total'], 2) }}</td>
                </tr>
            @endforeach
            
            <!-- Grand Totals -->
            <tr class="total-row">
                <td colspan="4" class="text-center font-bold">Total Dispatches</td>
                <td class="text-right font-bold">{{ number_format($total_dispatch_quantity, 2) }}</td>
                <td></td>
                <td class="text-right font-bold">₹ {{ number_format($total_dispatch_untaxed, 2) }}</td>
                <td class="text-right font-bold">₹ {{ number_format($total_dispatch_tax, 2) }}</td>
                <td class="text-right font-bold">₹ {{ number_format($total_dispatch_amount, 2) }}</td>
            </tr>
        </tbody>
    </table>

    <!-- Page Break for Cleanliness -->
    <div style="page-break-before: always;"></div>

    <!-- Section 4: Dispatch Consolidated - Party wise -->
    <div class="statement-title-container" style="margin-top: 15px; border-bottom: 1.5px solid #cbd5e1; padding-bottom: 3px;">
        <h3 style="font-size: 11pt; font-weight: bold; color: #334155; margin: 0; text-transform: uppercase;">4. Dispatch Report (Party wise Consolidated Summary)</h3>
    </div>

    <table class="data-table">
        <thead>
            <tr>
                <th width="5%">#</th>
                <th width="40%">Customer / Party Name</th>
                <th width="15%">Delivered Qty</th>
                <th width="15%">Taxable Amt</th>
                <th width="15%">Tax Amt</th>
                <th width="15%">Total Amt</th>
            </tr>
        </thead>
        <tbody>
            @foreach($party_summary as $index => $row)
                <tr>
                    <td class="text-center">{{ $index + 1 }}</td>
                    <td class="font-bold">{{ $row['party_name'] }}</td>
                    <td class="text-right font-bold">{{ number_format($row['quantity'], 2) }}</td>
                    <td class="text-right">₹ {{ number_format($row['amount_untaxed'], 2) }}</td>
                    <td class="text-right">₹ {{ number_format($row['amount_tax'], 2) }}</td>
                    <td class="text-right font-bold">₹ {{ number_format($row['amount_total'], 2) }}</td>
                </tr>
            @endforeach
            
            <!-- Grand Totals -->
            <tr class="total-row">
                <td colspan="2" class="text-center font-bold">Total Party Consolidations</td>
                <td class="text-right font-bold">{{ number_format($total_party_quantity, 2) }}</td>
                <td class="text-right font-bold">₹ {{ number_format($total_party_untaxed, 2) }}</td>
                <td class="text-right font-bold">₹ {{ number_format($total_party_tax, 2) }}</td>
                <td class="text-right font-bold">₹ {{ number_format($total_party_amount, 2) }}</td>
            </tr>
        </tbody>
    </table>

</body>
</html>
