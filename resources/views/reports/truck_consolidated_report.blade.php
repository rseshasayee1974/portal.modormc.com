<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Truck Wise Trip Report</title>
    <style>
        @page {
            size: A4 landscape;
            margin: 20px;
        }
        body {
            font-family: 'DejaVu Sans', sans-serif;
            font-size: 8pt;
            color: #1e293b;
            margin: 0;
            padding: 0;
            line-height: 1.35;
        }

        .header-table {
            width: 100%;
            border-collapse: collapse;
            border: 0;
            margin-bottom: 15px;
        }
        .header-table td {
            border: 0;
            padding: 0;
            vertical-align: top;
        }
        
        .address-box {
            font-size: 8pt;
            color: #334155;
            line-height: 1.3;
        }
        .address-title {
            font-weight: bold;
            color: #64748b;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-bottom: 2px;
            display: block;
            font-size: 7.5pt;
        }
        .address-name {
            font-weight: bold;
            font-size: 10pt;
            color: #0f172a;
            margin-bottom: 2px;
            display: block;
        }

        .statement-title-container {
            border-top: 2px solid #0284c7;
            border-bottom: 2px solid #0284c7;
            padding: 6px 0;
            margin-bottom: 15px;
            text-align: center;
        }
        .statement-title {
            font-size: 12pt;
            font-weight: bold;
            color: #0369a1;
            margin: 0 0 2px 0;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        .statement-period {
            font-size: 8pt;
            color: #475569;
            font-weight: bold;
        }

        table.data-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 10px;
            border: 1px solid #0284c7;
        }
        table.data-table th {
            background-color: #f0f9ff;
            color: #0369a1;
            border: 1px solid #38bdf8;
            padding: 6px 3px;
            font-size: 7pt;
            font-weight: bold;
            text-align: center;
            text-transform: uppercase;
        }
        table.data-table td {
            padding: 5px 4px;
            border: 1px solid #bae6fd;
            font-size: 7.5pt;
            vertical-align: middle;
        }
        
        .total-row {
            background-color: #f0f9ff;
            font-weight: bold;
            border-top: 2px solid #0284c7;
        }
        
        .text-right { text-align: right; }
        .text-center { text-align: center; }
        .font-bold { font-weight: bold; }
    </style>
</head>
<body>

    <table class="header-table">
        <tr>
            <td style="width: 52%;">
                <div class="address-box">
                    <span class="address-title">Fleet & Logistics Operations:</span>
                    <span class="address-name">Truck Wise Trip Report</span>
                    <span>Itemized dispatch trip tickets with delivered qty, empty/loaded/net weights, and tax breakdown</span>
                </div>
            </td>
            
            <td style="width: 48%; text-align: right;">
                <div class="address-box">
                    <span class="address-name" style="color: #0369a1;">{{ $plant->name ?? 'Ready Mix Concrete Operations' }}</span>
                    @if(!empty($plant->addresses) && $plant->addresses->isNotEmpty())
                        @php $addr = $plant->addresses->first(); @endphp
                        {{ $addr->line_1 ?? '' }}@if($addr->line_2), {{ $addr->line_2 }}@endif<br>
                        {{ $addr->city ?? '' }} - {{ $addr->zipcode ?? '' }}@if(!empty($addr->state)), {{ $addr->state->name }}@endif<br>
                    @endif
                    @if(!empty($plant->gstin))
                        <strong>GSTIN:</strong> {{ $plant->gstin }}<br>
                    @endif
                    @if(!empty($plant->phone))
                        <strong>Phone:</strong> {{ $plant->phone }}
                    @endif
                </div>
            </td>
        </tr>
    </table>

    <div class="statement-title-container">
        <h2 class="statement-title">Truck Consolidated & Trip Performance Report</h2>
        <span class="statement-period">Reporting Period: {{ $start }} to {{ $end }}</span>
    </div>

    @if(!empty($truck_groups) && count($truck_groups) > 0)
    <!-- Section 1: Truck Fleet Consolidated Summary -->
    <div class="statement-title-container" style="margin-top: 10px; border-bottom: 1.5px solid #cbd5e1; padding-bottom: 3px;">
        <h3 style="font-size: 10pt; font-weight: bold; color: #334155; margin: 0; text-transform: uppercase;">Fleet Consolidated Summary</h3>
    </div>

    <table class="data-table">
        <thead>
            <tr>
                <th width="5%">#</th>
                <th width="25%">Truck / Mixer Reg</th>
                <th width="15%">Trips</th>
                <th width="18%">Batch Size (m³)</th>
                <th width="18%">Delivered Qty (m³)</th>
                <th width="19%">Total Amt</th>
            </tr>
        </thead>
        <tbody>
            @foreach($truck_groups as $index => $tg)
                <tr>
                    <td class="text-center">{{ $index + 1 }}</td>
                    <td class="font-bold text-center" style="color: #4338ca;">{{ $tg['truck_no'] }}</td>
                    <td class="text-center font-bold">{{ $tg['trips_count'] }}</td>
                    <td class="text-right">{{ number_format($tg['total_batch'] ?? 0, 2) }}</td>
                    <td class="text-right font-bold">{{ number_format($tg['total_qty'] ?? 0, 2) }}</td>
                    <td class="text-right font-bold">₹ {{ number_format($tg['total_amount'] ?? 0, 2) }}</td>
                </tr>
            @endforeach
            <tr class="total-row">
                <td colspan="2" class="text-center font-bold">Grand Fleet Total</td>
                <td class="text-center font-bold">{{ $total_trips ?? collect($truck_groups)->sum('trips_count') }}</td>
                <td class="text-right font-bold">{{ number_format($total_batch_size ?? collect($truck_groups)->sum('total_batch'), 2) }}</td>
                <td class="text-right font-bold">{{ number_format($total_quantity ?? collect($truck_groups)->sum('total_qty'), 2) }}</td>
                <td class="text-right font-bold">₹ {{ number_format($total_amount ?? collect($truck_groups)->sum('total_amount'), 2) }}</td>
            </tr>
        </tbody>
    </table>
    @endif

    @php
        $trips = $truck_trips ?? $transactions ?? $items ?? [];
    @endphp

    <!-- Section 2: Truck Wise Every Batching / Trip Verification List -->
    <div class="statement-title-container" style="margin-top: 20px; border-bottom: 2px solid #0284c7; padding-bottom: 4px;">
        <h3 style="font-size: 11pt; font-weight: bold; color: #0369a1; margin: 0; text-transform: uppercase;">Every Batching / Trip Verification List ({{ count($trips) }} Trips)</h3>
        <span style="font-size: 8.5pt; color: #64748b;">Itemized fleet trip dispatch tickets for audit and delivery verification</span>
    </div>

    <table class="data-table">
        <thead>
            <tr>
                <th width="4%">Trip #</th>
                <th width="9%">Truck / Mixer</th>
                <th width="11%">Date & Time</th>
                <th width="11%">Dispatch / DSP #</th>
                <th width="14%">Customer Name</th>
                <th width="12%">Unload Site</th>
                <th width="8%">Grade</th>
                <th width="6%">Deliv Qty</th>
                <th width="5%">Empty Wt</th>
                <th width="5%">Load Wt</th>
                <th width="5%">Net Wt</th>
                <th width="7%">Taxable Amt</th>
                <th width="6%">Tax Amt</th>
                <th width="8%">Total Amt</th>
            </tr>
        </thead>
        <tbody>
            @foreach($trips as $index => $row)
                <tr>
                    <td class="text-center">{{ $index + 1 }}</td>
                    <td class="text-center font-bold" style="color: #4338ca;">{{ $row['truck_no'] }}</td>
                    <td style="font-size: 7pt;">{{ $row['dispatch_time'] }}</td>
                    <td class="font-bold">{{ $row['docket_no'] }}</td>
                    <td class="font-bold">{{ $row['customer_name'] }}</td>
                    <td>{{ $row['site_name'] }}</td>
                    <td class="text-center font-bold" style="color: #047857;">{{ $row['concrete_grade'] }}</td>
                    <td class="text-right font-bold">{{ number_format($row['delivered_qty'] ?? 0, 2) }}</td>
                    <td class="text-right">{{ number_format($row['empty_weight'] ?? 0, 2) }}</td>
                    <td class="text-right">{{ number_format($row['loaded_weight'] ?? 0, 2) }}</td>
                    <td class="text-right font-bold">{{ number_format($row['net_weight'] ?? 0, 2) }}</td>
                    <td class="text-right">₹ {{ number_format($row['amount_untaxed'] ?? 0, 2) }}</td>
                    <td class="text-right">₹ {{ number_format($row['amount_tax'] ?? 0, 2) }}</td>
                    <td class="text-right font-bold">₹ {{ number_format($row['amount_total'] ?? 0, 2) }}</td>
                </tr>
            @endforeach
            
            <tr class="total-row">
                <td colspan="7" class="text-center font-bold">Total Fleet Volume ({{ count($trips) }} Trips)</td>
                <td class="text-right font-bold">{{ number_format($total_quantity ?? collect($trips)->sum('delivered_qty'), 2) }}</td>
                <td class="text-right font-bold">{{ number_format($total_truck_empty ?? collect($trips)->sum('empty_weight'), 2) }}</td>
                <td class="text-right font-bold">{{ number_format($total_loaded_weight ?? collect($trips)->sum('loaded_weight'), 2) }}</td>
                <td class="text-right font-bold">{{ number_format($total_net_weight ?? collect($trips)->sum('net_weight'), 2) }}</td>
                <td class="text-right font-bold">₹ {{ number_format($total_untaxed ?? collect($trips)->sum('amount_untaxed'), 2) }}</td>
                <td class="text-right font-bold">₹ {{ number_format($total_tax ?? collect($trips)->sum('amount_tax'), 2) }}</td>
                <td class="text-right font-bold">₹ {{ number_format($total_amount ?? collect($trips)->sum('amount_total'), 2) }}</td>
            </tr>
        </tbody>
    </table>

    <div style="margin-top: 25px; border-top: 1px solid #cbd5e1; padding-top: 8px; font-size: 8pt; color: #64748b; display: table; width: 100%;">
        <div style="display: table-cell; text-align: left; vertical-align: middle;">
            @if(!empty($plantName ?? $plant?->name))
                <strong style="color: #334155;">{{ $plantName ?? $plant?->name }}</strong> &bull;
            @endif
            Generated on {{ now()->format('d M Y, h:i A') }}
        </div>
        <div style="display: table-cell; text-align: right; vertical-align: middle;">
            Powered by : <strong style="color: #1e293b;">{{ ($plantName ?? $plant?->name) ?: 'onemodo.com' }}</strong>
        </div>
    </div>
</body>
</html>
