<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Driver Report</title>
    <style>
        @page {
            size: A4 landscape;
            margin: 25px;
        }
        body {
            font-family: 'DejaVu Sans', sans-serif;
            font-size: 8.5pt;
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
            border-top: 2px solid #0284c7;
            border-bottom: 2px solid #0284c7;
            padding: 8px 0;
            margin-bottom: 15px;
            text-align: center;
        }
        .statement-title {
            font-size: 13pt;
            font-weight: bold;
            color: #0369a1;
            margin: 0 0 3px 0;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        .statement-period {
            font-size: 8.5pt;
            color: #475569;
            font-weight: bold;
        }

        table.data-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 25px;
        }
        table.data-table th {
            background-color: #f0f9ff;
            color: #0369a1;
            border: 1px solid #38bdf8;
            padding: 7px 4px;
            font-size: 7.5pt;
            font-weight: bold;
            text-align: center;
            text-transform: uppercase;
        }
        table.data-table td {
            padding: 6px 6px;
            border: 1px solid #bae6fd;
            font-size: 8pt;
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
                    <span class="address-name">Driver Dispatch & Trip Performance Report</span>
                    <span>Consolidated driver trips, delivered volume, and batch sizes</span>
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
        <h2 class="statement-title">Driver Dispatch Consolidated Report</h2>
        <span class="statement-period">Reporting Period: {{ $start }} to {{ $end }}</span>
    </div>

    <!-- Section 1: Consolidated by Driver -->
    <table class="data-table">
        <thead>
            <tr>
                <th width="5%">#</th>
                <th width="35%">Driver Name</th>
                <th width="15%">Code</th>
                <th width="15%">Trips</th>
                <th width="15%">Batch Size</th>
                <th width="15%">Delivered Qty</th>
            </tr>
        </thead>
        <tbody>
            @php 
                $rows = $consolidated ?? $items ?? [];
            @endphp
            @foreach($rows as $index => $row)
                <tr>
                    <td class="text-center">{{ $index + 1 }}</td>
                    <td class="font-bold">{{ $row['driver_name'] }}</td>
                    <td class="text-center">{{ $row['driver_code'] ?: '-' }}</td>
                    <td class="text-center font-bold">{{ $row['trips_count'] ?? 1 }}</td>
                    <td class="text-right">{{ number_format($row['batch_size'] ?? 0, 2) }}</td>
                    <td class="text-right font-bold">{{ number_format($row['quantity'] ?? 0, 2) }}</td>
                </tr>
            @endforeach
            
            <tr class="total-row">
                <td colspan="3" class="text-center font-bold">Grand Total</td>
                <td class="text-center font-bold">{{ $totals['trips_count'] ?? collect($rows)->sum('trips_count') }}</td>
                <td class="text-right font-bold">{{ number_format($totals['batch_size'] ?? 0, 2) }}</td>
                <td class="text-right font-bold">{{ number_format($totals['quantity'] ?? $total_quantity ?? 0, 2) }}</td>
            </tr>
        </tbody>
    </table>

    @if(!empty($driver_vehicle_summary) && count($driver_vehicle_summary) > 0)
    <!-- Section 2: Driver & Vehicle Breakdown -->
    <div class="statement-title-container" style="margin-top: 20px; border-bottom: 1.5px solid #cbd5e1; padding-bottom: 3px;">
        <h3 style="font-size: 10pt; font-weight: bold; color: #334155; margin: 0; text-transform: uppercase;">Driver & Vehicle Trips Summary</h3>
    </div>

    <table class="data-table">
        <thead>
            <tr>
                <th width="6%">#</th>
                <th width="38%">Driver Name</th>
                <th width="26%">Vehicle / Truck Reg</th>
                <th width="15%">Trips</th>
                <th width="15%">Delivered Qty</th>
            </tr>
        </thead>
        <tbody>
            @foreach($driver_vehicle_summary as $index => $row)
                <tr>
                    <td class="text-center">{{ $index + 1 }}</td>
                    <td class="font-bold">{{ $row['driver_name'] }}</td>
                    <td class="font-bold">{{ $row['truck_no'] }}</td>
                    <td class="text-center font-bold">{{ $row['trips_count'] ?? 1 }}</td>
                    <td class="text-right font-bold">{{ number_format($row['quantity'] ?? 0, 2) }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>
    @endif

    @include('pdfs.partials._footer')

</body>
</html>
