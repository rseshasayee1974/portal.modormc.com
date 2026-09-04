<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Sales Report</title>
    <style>
        @page {
            size: A4 landscape;
            margin: 25px;
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
                @if(!empty($patron))
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
                    <strong>GSTIN/UIN :</strong> {{ $plant->gstin ?? '' }}<br>
                    {{-- <strong>MSME - UDYAM-</strong> {{ $plant->msme_no ?? '' }} --}}
                </div>
            </td>
        </tr>
    </table>

    <!-- Sales Report Title -->
    <div class="statement-title-container">
        <h2 class="statement-title">Sales Statement of Accounts</h2>
        <span class="statement-period">Period: {{ $start }} to {{ $end }}</span>
    </div>

    <!-- Section 1: Sales Dispatch & Invoice wise Details -->
    <div class="statement-title-container" style="margin-top: 20px; border-bottom: 1.5px solid #cbd5e1; padding-bottom: 3px;">
        <h3 style="font-size: 11pt; font-weight: bold; color: #334155; margin: 0; text-transform: uppercase;">1. Sales Dispatch & Invoice wise Details</h3>
    </div>

    <table class="data-table">
        <thead>
            <tr>
                <th width="3%">#</th>
                <th width="9%">Date</th>
                <th width="12%">Dispatch / Batch</th>
                <th width="12%">Invoice Details</th>
                <th width="20%">Customer / Site</th>
                <th width="6%">Qty (m³)</th>
                <th width="6%">Empty Wt (T)</th>
                <th width="6%">Loaded Wt (T)</th>
                <th width="6%">Net Wt (T)</th>
                <th width="7%">Taxable Amt (₹)</th>
                <th width="6%">Tax Amt (₹)</th>
                <th width="8%">Total Amt (₹)</th>
            </tr>
        </thead>
        <tbody>
            @foreach($transactions as $index => $row)
                <tr>
                    <td class="text-center">{{ $index + 1 }}</td>
                    <td class="text-center">{{ \Carbon\Carbon::parse($row['date'])->format('d-m-Y') }}</td>
                    <td class="text-center">
                        <strong>{{ $row['dispatch_no'] ?? '-' }}</strong><br>
                        <small style="color: #64748b;">{{ $row['batch_no'] ?? '-' }}</small>
                    </td>
                    <td class="text-center">
                        <strong>{{ ($row['invoice_number'] ?? '-') !== '-' ? $row['invoice_number'] : 'Unbilled' }}</strong>
                        @if(!empty($row['invoice_date']) && $row['invoice_date'] !== '-')
                            <br><small style="color: #64748b;">{{ $row['invoice_date'] }}</small>
                        @endif
                    </td>
                    <td>
                        {{ $row['customer_name'] }}
                        @if(!empty($row['site_name']) && $row['site_name'] !== 'N/A')
                            <br><small style="color: #64748b;">{{ $row['site_name'] }}</small>
                        @endif
                    </td>
                    <td class="text-right font-bold">{{ number_format($row['quantity'] ?? 0, 2) }}</td>
                    <td class="text-right">{{ number_format($row['truck_empty'] ?? ($row['empty_weight'] ?? 0), 2) }}</td>
                    <td class="text-right">{{ number_format($row['loaded_weight'] ?? ($row['truck_loaded'] ?? 0), 2) }}</td>
                    <td class="text-right font-bold">{{ number_format($row['netweight'] ?? ($row['net_weight'] ?? 0), 2) }}</td>
                    <td class="text-right">{{ number_format($row['amount_untaxed'], 2) }}</td>
                    <td class="text-right">{{ number_format($row['amount_tax'], 2) }}</td>
                    <td class="text-right font-bold">{{ number_format($row['amount_total'], 2) }}</td>
                </tr>
            @endforeach
            
            <!-- Grand Totals -->
            <tr class="total-row">
                <td colspan="5" class="text-center font-bold">Total Sales</td>
                <td class="text-right font-bold">{{ number_format($total_quantity ?? collect($transactions)->sum('quantity'), 2) }}</td>
                <td class="text-right font-bold">{{ number_format($total_truck_empty ?? collect($transactions)->sum('truck_empty'), 2) }}</td>
                <td class="text-right font-bold">{{ number_format($total_loaded_weight ?? collect($transactions)->sum('loaded_weight'), 2) }}</td>
                <td class="text-right font-bold">{{ number_format($total_net_weight ?? collect($transactions)->sum('netweight'), 2) }}</td>
                <td class="text-right font-bold">{{ number_format($total_untaxed, 2) }}</td>
                <td class="text-right font-bold">{{ number_format($total_tax, 2) }}</td>
                <td class="text-right font-bold">{{ number_format($total_amount, 2) }}</td>
            </tr>
        </tbody>
    </table>

    <!-- Page Break for Cleanliness -->
    <div style="page-break-before: always;"></div>

    <!-- Section 2: Product Consolidated Report (Mix Design & Concrete Grade wise) -->
    <div class="statement-title-container" style="margin-top: 15px; border-bottom: 1.5px solid #cbd5e1; padding-bottom: 3px;">
        <h3 style="font-size: 11pt; font-weight: bold; color: #334155; margin: 0; text-transform: uppercase;">2. Product Consolidated Report (Mix Design & Concrete Grade wise)</h3>
    </div>

    <table class="data-table">
        <thead>
            <tr>
                <th width="5%">#</th>
                <th width="35%">Mix Design Name</th>
                <th width="15%">Grade</th>
                <th width="10%">UOM</th>
                <th width="10%">Trips</th>
                <!-- <th width="7%">Batch Size</th> -->
                <th width="10%">Delivered Qty</th>
                <!-- <th width="7%">Empty Wt</th>
                <th width="7%">Loaded Wt</th>
                <th width="7%">Net Wt</th>
                <th width="8%">Taxable Amt</th>
                <th width="8%">Tax Amt</th> -->
                <th width="15%">Total Amt (₹)</th>
            </tr>
        </thead>
        <tbody>
            @foreach($product_summary as $index => $row)
                <tr>
                    <td class="text-center">{{ $index + 1 }}</td>
                    <td class="font-bold">{{ $row['mix_name'] ?? $row['product_name'] }}</td>
                    <td class="text-center font-bold" style="color: #4338ca;">{{ $row['concrete_grade'] ?? 'N/A' }}</td>
                    <td class="text-center">{{ $row['uom'] ?? 'm³' }}</td>
                    <td class="text-center font-bold">{{ $row['trips_count'] ?? 1 }}</td>
                    <!-- <td class="text-right">{{ number_format($row['batch_size'] ?? 0, 2) }}</td> -->
                    <td class="text-right font-bold">{{ number_format($row['quantity'] ?? 0, 2) }}</td>
                    <!-- <td class="text-right">{{ number_format($row['truck_empty'] ?? 0, 2) }}</td>
                    <td class="text-right">{{ number_format($row['loaded_weight'] ?? 0, 2) }}</td>
                    <td class="text-right font-bold">{{ number_format($row['netweight'] ?? 0, 2) }}</td>
                    <td class="text-right">{{ number_format($row['amount_untaxed'] ?? 0, 2) }}</td>
                    <td class="text-right">{{ number_format($row['amount_tax'] ?? 0, 2) }}</td> -->
                    <td class="text-right font-bold">{{ number_format($row['amount_total'] ?? 0, 2) }}</td>
                </tr>
            @endforeach
            
            <!-- Grand Totals -->
            <tr class="total-row">
                <td colspan="4" class="text-center font-bold">Total Product Summary</td>
                <td class="text-center font-bold">{{ collect($product_summary)->sum('trips_count') }}</td>
                <!-- <td class="text-right font-bold">{{ number_format($total_product_batch_size ?? collect($product_summary)->sum('batch_size'), 2) }}</td> -->
                <td class="text-right font-bold">{{ number_format($total_product_quantity ?? $total_quantity, 2) }}</td>
                <!-- <td class="text-right font-bold">{{ number_format($total_product_truck_empty ?? collect($product_summary)->sum('truck_empty'), 2) }}</td>
                <td class="text-right font-bold">{{ number_format($total_product_loaded_weight ?? collect($product_summary)->sum('loaded_weight'), 2) }}</td>
                <td class="text-right font-bold">{{ number_format($total_product_net_weight ?? collect($product_summary)->sum('netweight'), 2) }}</td>
                <td class="text-right font-bold">{{ number_format($total_product_untaxed, 2) }}</td>
                <td class="text-right font-bold">{{ number_format($total_product_tax, 2) }}</td> -->
                <td class="text-right font-bold">{{ number_format($total_product_amount, 2) }}</td>
            </tr>
        </tbody>
    </table>

    <!-- Page Break for Cleanliness -->
    <div style="page-break-before: always;"></div>

    <!-- Section 3: Customer Consolidated Report (Customer / Party wise) -->
    <div class="statement-title-container" style="margin-top: 15px; border-bottom: 1.5px solid #cbd5e1; padding-bottom: 3px;">
        <h3 style="font-size: 11pt; font-weight: bold; color: #334155; margin: 0; text-transform: uppercase;">3. Customer Consolidated Report (Customer / Party wise)</h3>
    </div>

    <table class="data-table">
        <thead>
            <tr>
                <th width="5%">#</th>
                <th width="45%">Customer / Party Name</th>
                <th width="15%">Trips</th>
                <!-- <th width="8%">Batch Size</th> -->
                <th width="15%">Delivered Qty</th>
                <!-- <th width="8%">Empty Wt</th>
                <th width="8%">Loaded Wt</th>
                <th width="8%">Net Wt</th> -->
                <!-- <th width="11%">Taxable Amt</th>
                <th width="8%">Tax Amt</th> -->
                <th width="20%">Total Amt (₹)</th>
            </tr>
        </thead>
        <tbody>
            @foreach($party_summary as $index => $row)
                <tr>
                    <td class="text-center">{{ $index + 1 }}</td>
                    <td class="font-bold">{{ $row['party_name'] ?? $row['customer_name'] }}</td>
                    <td class="text-center font-bold">{{ $row['trips_count'] ?? 1 }}</td>
                    <!-- <td class="text-right">{{ number_format($row['batch_size'] ?? 0, 2) }}</td> -->
                    <td class="text-right font-bold">{{ number_format($row['quantity'] ?? 0, 2) }}</td>
                    <!-- <td class="text-right">{{ number_format($row['truck_empty'] ?? 0, 2) }}</td>
                    <td class="text-right">{{ number_format($row['loaded_weight'] ?? 0, 2) }}</td>
                    <td class="text-right font-bold">{{ number_format($row['netweight'] ?? 0, 2) }}</td> -->
                    <!-- <td class="text-right">{{ number_format($row['amount_untaxed'] ?? 0, 2) }}</td>
                    <td class="text-right">{{ number_format($row['amount_tax'] ?? 0, 2) }}</td> -->
                    <td class="text-right font-bold">{{ number_format($row['amount_total'] ?? 0, 2) }}</td>
                </tr>
            @endforeach
            
            <!-- Grand Totals -->
            <tr class="total-row">
                <td colspan="2" class="text-center font-bold">Total Customer Volume</td>
                <td class="text-center font-bold">{{ collect($party_summary)->sum('trips_count') }}</td>
                <!-- <td class="text-right font-bold">{{ number_format($total_party_batch_size ?? collect($party_summary)->sum('batch_size'), 2) }}</td> -->
                <td class="text-right font-bold">{{ number_format($total_party_quantity, 2) }}</td>
                <!-- <td class="text-right font-bold">{{ number_format($total_party_truck_empty ?? collect($party_summary)->sum('truck_empty'), 2) }}</td>
                <td class="text-right font-bold">{{ number_format($total_party_loaded_weight ?? collect($party_summary)->sum('loaded_weight'), 2) }}</td>
                <td class="text-right font-bold">{{ number_format($total_party_net_weight ?? collect($party_summary)->sum('netweight'), 2) }}</td> -->
                <!-- <td class="text-right font-bold">{{ number_format($total_party_untaxed, 2) }}</td>
                <td class="text-right font-bold">{{ number_format($total_party_tax, 2) }}</td> -->
                <td class="text-right font-bold">{{ number_format($total_party_amount, 2) }}</td>
            </tr>
        </tbody>
    </table>

    @if(!empty($truck_summary))
    <!-- Page Break for Cleanliness -->
    <div style="page-break-before: always;"></div>

    <!-- Section 4: Truck Consolidated Report (Vehicle / Fleet wise) -->
    <div class="statement-title-container" style="margin-top: 15px; border-bottom: 1.5px solid #cbd5e1; padding-bottom: 3px;">
        <h3 style="font-size: 11pt; font-weight: bold; color: #334155; margin: 0; text-transform: uppercase;">4. Truck Consolidated Report (Vehicle / Fleet wise)</h3>
    </div>

    <table class="data-table">
        <thead>
            <tr>
                <th width="4%">#</th>
                <th width="26%">Truck / Vehicle Registration</th>
                <th width="6%">Trips</th>
                <!-- <th width="8%">Batch Size</th> -->
                <th width="9%">Delivered Qty</th>
                <th width="8%">Empty Wt</th>
                <th width="8%">Loaded Wt</th>
                <th width="8%">Net Wt</th>
                <!-- <th width="11%">Taxable Amt</th>
                <th width="8%">Tax Amt</th> -->
                <th width="10%">Total Amt (₹)</th>
            </tr>
        </thead>
        <tbody>
            @foreach($truck_summary as $index => $row)
                <tr>
                    <td class="text-center">{{ $index + 1 }}</td>
                    <td class="font-bold">{{ $row['truck_no'] }}</td>
                    <td class="text-center font-bold">{{ $row['trips_count'] ?? 1 }}</td>
                    <!-- <td class="text-right">{{ number_format($row['batch_size'] ?? 0, 2) }}</td> -->
                    <td class="text-right font-bold">{{ number_format($row['quantity'] ?? 0, 2) }}</td>
                    <td class="text-right">{{ number_format($row['truck_empty'] ?? 0, 2) }}</td>
                    <td class="text-right">{{ number_format($row['loaded_weight'] ?? 0, 2) }}</td>
                    <td class="text-right font-bold">{{ number_format($row['netweight'] ?? 0, 2) }}</td>
                    <!-- <td class="text-right">{{ number_format($row['amount_untaxed'] ?? 0, 2) }}</td>
                    <td class="text-right">{{ number_format($row['amount_tax'] ?? 0, 2) }}</td> -->
                    <td class="text-right font-bold">{{ number_format($row['amount_total'] ?? 0, 2) }}</td>
                </tr>
            @endforeach
            
            <!-- Grand Totals -->
            <tr class="total-row">
                <td colspan="2" class="text-center font-bold">Total Fleet Volume</td>
                <td class="text-center font-bold">{{ $total_truck_trips ?? collect($truck_summary)->sum('trips_count') }}</td>
                <!-- <td class="text-right font-bold">{{ number_format($total_truck_batch_size ?? collect($truck_summary)->sum('batch_size'), 2) }}</td> -->
                <td class="text-right font-bold">{{ number_format($total_truck_quantity ?? collect($truck_summary)->sum('quantity'), 2) }}</td>
                <td class="text-right font-bold">{{ number_format($total_truck_empty ?? collect($truck_summary)->sum('truck_empty'), 2) }}</td>
                <td class="text-right font-bold">{{ number_format($total_truck_loaded_weight ?? collect($truck_summary)->sum('loaded_weight'), 2) }}</td>
                <td class="text-right font-bold">{{ number_format($total_truck_net_weight ?? collect($truck_summary)->sum('netweight'), 2) }}</td>
                <!-- <td class="text-right font-bold">{{ number_format($total_truck_untaxed ?? collect($truck_summary)->sum('amount_untaxed'), 2) }}</td>
                <td class="text-right font-bold">{{ number_format($total_truck_tax ?? collect($truck_summary)->sum('amount_tax'), 2) }}</td> -->
                <td class="text-right font-bold">{{ number_format($total_truck_amount ?? collect($truck_summary)->sum('amount_total'), 2) }}</td>
            </tr>
        </tbody>
    </table>
    @endif

    @if(!empty($site_summary))
    <!-- Page Break for Cleanliness -->
    <div style="page-break-before: always;"></div>

    <!-- Section 5: Unload Site Consolidated Report (Site wise) -->
    <div class="statement-title-container" style="margin-top: 15px; border-bottom: 1.5px solid #cbd5e1; padding-bottom: 3px;">
        <h3 style="font-size: 11pt; font-weight: bold; color: #334155; margin: 0; text-transform: uppercase;">5. Unload Site Consolidated Report (Site wise)</h3>
    </div>

    <table class="data-table">
        <thead>
            <tr>
                <th width="5%">#</th>
                <th width="26%">Unload Site Name</th>
                <th width="23%">Customer / Party</th>
                <th width="10%">Trips</th>
                {{-- <th width="12%">Batch Size</th> --}}
                <th width="12%">Delivered Qty</th>
                <th width="12%">Total Amt (₹)</th>
            </tr>
        </thead>
        <tbody>
            @foreach($site_summary as $index => $row)
                <tr>
                    <td class="text-center">{{ $index + 1 }}</td>
                    <td class="font-bold">{{ $row['site_name'] }}</td>
                    <td>{{ $row['customer_name'] ?? '-' }}</td>
                    <td class="text-center font-bold">{{ $row['trips_count'] ?? 1 }}</td>
                    {{-- <td class="text-right">{{ number_format($row['batch_size'] ?? 0, 2) }}</td> --}}
                    <td class="text-right font-bold">{{ number_format($row['quantity'] ?? 0, 2) }}</td>
                    <td class="text-right font-bold">{{ number_format($row['amount_total'] ?? 0, 2) }}</td>
                </tr>
            @endforeach
            
            <!-- Grand Totals -->
            <tr class="total-row">
                <td colspan="3" class="text-center font-bold">Total Site Volume</td>
                <td class="text-center font-bold">{{ $total_site_trips ?? collect($site_summary)->sum('trips_count') }}</td>
                {{-- <td class="text-right font-bold">{{ number_format($total_site_batch_size ?? collect($site_summary)->sum('batch_size'), 2) }}</td> --}}
                <td class="text-right font-bold">{{ number_format($total_site_quantity ?? collect($site_summary)->sum('quantity'), 2) }}</td>
                <td class="text-right font-bold">{{ number_format($total_site_amount ?? collect($site_summary)->sum('amount_total'), 2) }}</td>
            </tr>
        </tbody>
    </table>
    @endif

    @if(!empty($payment_mode_summary))
    <!-- Section 6: Payment Mode Consolidated Report -->
    <div class="statement-title-container" style="margin-top: 25px; border-bottom: 1.5px solid #cbd5e1; padding-bottom: 3px;">
        <h3 style="font-size: 11pt; font-weight: bold; color: #334155; margin: 0; text-transform: uppercase;">6. Payment Mode Consolidated Report</h3>
    </div>

    <table class="data-table">
        <thead>
            <tr>
                <th width="6%">#</th>
                <th width="44%">Payment Mode</th>
                <th width="15%">Trips</th>
                <!-- <th width="14%">Batch Size</th> -->
                <th width="15%">Delivered Qty</th>
                <th width="20%">Total Amt (₹)</th>
            </tr>
        </thead>
        <tbody>
            @foreach($payment_mode_summary as $index => $row)
                <tr>
                    <td class="text-center">{{ $index + 1 }}</td>
                    <td class="font-bold">{{ $row['payment_mode'] }}</td>
                    <td class="text-center font-bold">{{ $row['trips_count'] ?? 1 }}</td>
                    <!-- <td class="text-right">{{ number_format($row['batch_size'] ?? 0, 2) }}</td> -->
                    <td class="text-right font-bold">{{ number_format($row['quantity'] ?? 0, 2) }}</td>
                    <td class="text-right font-bold">{{ number_format($row['amount_total'] ?? 0, 2) }}</td>
                </tr>
            @endforeach
            
            <!-- Grand Totals -->
            <tr class="total-row">
                <td colspan="2" class="text-center font-bold">Total Payment Modes</td>
                <td class="text-center font-bold">{{ $total_payment_mode_trips ?? collect($payment_mode_summary)->sum('trips_count') }}</td>
                <!-- <td class="text-right font-bold">{{ number_format($total_payment_mode_batch_size ?? collect($payment_mode_summary)->sum('batch_size'), 2) }}</td> -->
                <td class="text-right font-bold">{{ number_format($total_payment_mode_quantity ?? collect($payment_mode_summary)->sum('quantity'), 2) }}</td>
                <td class="text-right font-bold">{{ number_format($total_payment_mode_amount ?? collect($payment_mode_summary)->sum('amount_total'), 2) }}</td>
            </tr>
        </tbody>
    </table>
    @endif

    @php 
        $resolvedPlantName = $plantName ?? $plant?->name ?? ''; 
    @endphp
    <div style="margin-top: 30px; border-top: 1px solid #cbd5e1; padding-top: 8px; font-size: 8pt; color: #64748b; display: table; width: 100%;">
        <div style="display: table-cell; text-align: left; vertical-align: middle;">
            @if(!empty($resolvedPlantName))
                <strong style="color: #334155;">{{ $resolvedPlantName }}</strong> &bull;
            @endif
            Generated on {{ now()->format('d M Y, h:i A') }}
        </div>
        <div style="display: table-cell; text-align: right; vertical-align: middle;">
            Powered by : <strong style="color: #1e293b;">{{ $resolvedPlantName ?: 'onemodo.com' }}</strong>
        </div>
    </div>
</body>
</html>
