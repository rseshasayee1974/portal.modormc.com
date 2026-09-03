<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Product Consolidated Report</title>
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
                <div class="address-box" style="margin-top: 5px;">
                    <span class="address-title">Report Focus:</span>
                    <span class="address-name">Mix Designs & Concrete Grades Consolidated</span>
                    <span>Consolidated dispatch summary with batch size, truck weights, and revenue</span>
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
        <h2 class="statement-title">Product Consolidated Report (Mix Design & Concrete Grade wise)</h2>
        <span class="statement-period">Reporting Period: {{ $start }} to {{ $end }}</span>
    </div>

    <table class="data-table">
        <thead>
            <tr>
                <th width="4%">#</th>
                <th width="24%">Mix Design Name</th>
                <th width="10%">Grade</th>
                <th width="5%">UOM</th>
                <th width="6%">Trips</th>
                <th width="10%">Batch Size</th>
                <th width="11%">Delivered Qty</th>
                <th width="10%">Net Wt</th>
                <th width="8%">Avg Rate</th>
                <th width="12%">Total Amt</th>
            </tr>
        </thead>
        <tbody>
            @foreach(($transactions ?? $items ?? []) as $index => $row)
                <tr>
                    <td class="text-center">{{ $index + 1 }}</td>
                    <td class="font-bold">{{ $row['mix_name'] ?? $row['product_name'] }}</td>
                    <td class="text-center font-bold" style="color: #4338ca;">{{ $row['concrete_grade'] ?? 'N/A' }}</td>
                    <td class="text-center">{{ $row['uom'] ?? 'm³' }}</td>
                    <td class="text-center font-bold">{{ $row['trips_count'] ?? 1 }}</td>
                    <td class="text-right">{{ number_format($row['batch_size'] ?? 0, 2) }}</td>
                    <td class="text-right font-bold">{{ number_format($row['quantity'] ?? 0, 2) }}</td>
                    <td class="text-right font-bold">{{ number_format($row['netweight'] ?? 0, 2) }}</td>
                    <td class="text-right">₹ {{ number_format($row['avg_rate'] ?? 0, 2) }}</td>
                    <td class="text-right font-bold">₹ {{ number_format($row['amount_total'] ?? 0, 2) }}</td>
                </tr>
            @endforeach
            
            <tr class="total-row">
                <td colspan="4" class="text-center font-bold">Total Summary</td>
                <td class="text-center font-bold">{{ $total_trips ?? collect($transactions ?? $items ?? [])->sum('trips_count') }}</td>
                <td class="text-right font-bold">{{ number_format($total_batch_size ?? 0, 2) }}</td>
                <td class="text-right font-bold">{{ number_format($total_quantity ?? 0, 2) }}</td>
                <td class="text-right font-bold">{{ number_format($total_net_weight ?? 0, 2) }}</td>
                <td></td>
                <td class="text-right font-bold">₹ {{ number_format($total_amount ?? 0, 2) }}</td>
            </tr>
        </tbody>
    </table>

    @if(!empty($product_site_summary))
    <!-- Section 2: Unload Site based Product Consolidation -->
    <div class="statement-title-container" style="margin-top: 25px; border-bottom: 1.5px solid #cbd5e1; padding-bottom: 3px;">
        <h3 style="font-size: 11pt; font-weight: bold; color: #334155; margin: 0; text-transform: uppercase;">Unload Site based Product Consolidated Summary</h3>
    </div>

    <table class="data-table">
        <thead>
            <tr>
                <th width="4%">#</th>
                <th width="22%">Mix Design</th>
                <th width="10%">Grade</th>
                <th width="22%">Unloading Site</th>
                <th width="6%">UOM</th>
                <th width="6%">Trips</th>
                <th width="10%">Batch Size</th>
                <th width="10%">Delivered Qty</th>
                <th width="10%">Net Wt</th>
                <th width="12%">Total Amt</th>
            </tr>
        </thead>
        <tbody>
            @foreach($product_site_summary as $index => $row)
                <tr>
                    <td class="text-center">{{ $index + 1 }}</td>
                    <td class="font-bold">{{ $row['mix_name'] }}</td>
                    <td class="text-center font-bold" style="color: #4338ca;">{{ $row['concrete_grade'] ?? 'N/A' }}</td>
                    <td class="font-bold">{{ $row['site_name'] }}</td>
                    <td class="text-center">{{ $row['uom'] ?? 'm³' }}</td>
                    <td class="text-center font-bold">{{ $row['trips_count'] ?? 1 }}</td>
                    <td class="text-right">{{ number_format($row['batch_size'] ?? 0, 2) }}</td>
                    <td class="text-right font-bold">{{ number_format($row['quantity'] ?? 0, 2) }}</td>
                    <td class="text-right font-bold">{{ number_format($row['netweight'] ?? 0, 2) }}</td>
                    <td class="text-right font-bold">₹ {{ number_format($row['amount_total'] ?? 0, 2) }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>
    @endif

    @if(!empty($payment_mode_summary))
    <!-- Section 3: Payment Mode Consolidation -->
    <div class="statement-title-container" style="margin-top: 25px; border-bottom: 1.5px solid #cbd5e1; padding-bottom: 3px;">
        <h3 style="font-size: 11pt; font-weight: bold; color: #334155; margin: 0; text-transform: uppercase;">Payment Mode Consolidated Summary</h3>
    </div>

    <table class="data-table">
        <thead>
            <tr>
                <th width="6%">#</th>
                <th width="38%">Payment Mode</th>
                <th width="12%">Trips</th>
                <th width="14%">Batch Size</th>
                <th width="15%">Delivered Qty</th>
                <th width="15%">Total Amt</th>
            </tr>
        </thead>
        <tbody>
            @foreach($payment_mode_summary as $index => $row)
                <tr>
                    <td class="text-center">{{ $index + 1 }}</td>
                    <td class="font-bold">{{ $row['payment_mode'] }}</td>
                    <td class="text-center font-bold">{{ $row['trips_count'] ?? 1 }}</td>
                    <td class="text-right">{{ number_format($row['batch_size'] ?? 0, 2) }}</td>
                    <td class="text-right font-bold">{{ number_format($row['quantity'] ?? 0, 2) }}</td>
                    <td class="text-right font-bold">₹ {{ number_format($row['amount_total'] ?? 0, 2) }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>
    @endif

    @php
        $resolvedPlantName = ($plantName ?? $plant?->name ?? '');
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
