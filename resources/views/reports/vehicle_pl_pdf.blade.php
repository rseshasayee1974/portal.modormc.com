<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Vehicle Wise Profit & Loss Report</title>
    <style>
        @page {
            margin: 20px;
        }
        body {
            font-family: 'DejaVu Sans', sans-serif;
            font-size: 8pt;
            color: #1e293b;
            margin: 0;
            padding: 0;
            line-height: 1.2;
        }
        .title-container {
            border-bottom: 2px solid #1d2d3e;
            padding-bottom: 5px;
            margin-bottom: 10px;
        }
        .report-title {
            font-size: 12pt;
            font-weight: bold;
            color: #1d2d3e;
            margin: 0;
        }
        .filter-info {
            font-size: 8pt;
            color: #475569;
            margin-top: 3px;
        }
        table.data-table {
            width: 100%;
            border-collapse: collapse;
            border: 1px solid #cbd5e1;
        }
        table.data-table th {
            background-color: #f2f4f7;
            color: #1d2d3e;
            border: 1px solid #cbd5e1;
            padding: 5px 3px;
            font-size: 7pt;
            font-weight: bold;
            text-align: center;
            text-transform: uppercase;
        }
        table.data-table td {
            padding: 4px 5px;
            border: 1px solid #cbd5e1;
            font-size: 7.5pt;
            vertical-align: middle;
        }
        .total-row {
            background-color: #e2e8f0;
            font-weight: bold;
        }
        .text-right { text-align: right; }
        .text-center { text-align: center; }
        .font-bold { font-weight: bold; }
        .profit-text {
            color: #16a34a;
            font-weight: bold;
        }
        .loss-text {
            color: #dc2626;
            font-weight: bold;
        }
    </style>
</head>
<body>

    <div class="title-container">
        <h2 class="report-title">Vehicle Wise Profit & Loss Report</h2>
        <div class="filter-info">
            <strong>Period:</strong> {{ \Carbon\Carbon::parse($filters['from_date'] ?? $filters['start_date'] ?? $filters['start'] ?? now())->format('d-m-Y') }} to {{ \Carbon\Carbon::parse($filters['to_date'] ?? $filters['end_date'] ?? $filters['end'] ?? now())->format('d-m-Y') }} 
            &nbsp;|&nbsp; <strong>Generated At:</strong> {{ $generated_at }}
        </div>
    </div>

    <table class="data-table">
        <thead>
            <tr>
                <th>Registration</th>
                <th>Model</th>
                <th>Trip Revenue (₹)</th>
                <th>Trip Cost (₹)</th>
                <th>Fuel Expenses (₹)</th>
                <th>Maintenance (₹)</th>
                <th>Other Expenses (₹)</th>
                <th>Total Cost (₹)</th>
                <th>Net Profit (₹)</th>
                <th>Margin %</th>
            </tr>
        </thead>
        <tbody>
            @php
                $sumRevenue = 0;
                $sumTripCost = 0;
                $sumFuel = 0;
                $sumMaint = 0;
                $sumOther = 0;
                $sumTotalCost = 0;
                $sumNetProfit = 0;
            @endphp
            @forelse($items as $item)
                @php
                    $sumRevenue += $item['trip_revenue'];
                    $sumTripCost += $item['trip_cost'];
                    $sumFuel += $item['fuel_expenses'];
                    $sumMaint += $item['maintenance_expenses'];
                    $sumOther += $item['other_expenses'];
                    $sumTotalCost += $item['total_cost'];
                    $sumNetProfit += $item['net_profit'];
                @endphp
                <tr>
                    <td class="text-center font-bold">{{ $item['registration'] }}</td>
                    <td>{{ $item['vehicle_model'] }}</td>
                    <td class="text-right">{{ number_format($item['trip_revenue'], 2) }}</td>
                    <td class="text-right">{{ number_format($item['trip_cost'], 2) }}</td>
                    <td class="text-right">{{ number_format($item['fuel_expenses'], 2) }}</td>
                    <td class="text-right">{{ number_format($item['maintenance_expenses'], 2) }}</td>
                    <td class="text-right">{{ number_format($item['other_expenses'], 2) }}</td>
                    <td class="text-right font-bold">{{ number_format($item['total_cost'], 2) }}</td>
                    <td class="text-right @if($item['net_profit'] >= 0) profit-text @else loss-text @endif">
                        {{ number_format($item['net_profit'], 2) }}
                    </td>
                    <td class="text-right font-bold @if($item['margin_pct'] >= 0) profit-text @else loss-text @endif">
                        {{ number_format($item['margin_pct'], 2) }}%
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="10" class="text-center" style="padding: 15px;">No records found for the selected period.</td>
                </tr>
            @endforelse

            @if(!empty($items))
                @php
                    $overallMargin = $sumRevenue > 0 ? ($sumNetProfit / $sumRevenue) * 100.0 : 0.0;
                @endphp
                <tr class="total-row">
                    <td colspan="2" class="text-center font-bold">TOTAL</td>
                    <td class="text-right font-bold">{{ number_format($sumRevenue, 2) }}</td>
                    <td class="text-right font-bold">{{ number_format($sumTripCost, 2) }}</td>
                    <td class="text-right font-bold">{{ number_format($sumFuel, 2) }}</td>
                    <td class="text-right font-bold">{{ number_format($sumMaint, 2) }}</td>
                    <td class="text-right font-bold">{{ number_format($sumOther, 2) }}</td>
                    <td class="text-right font-bold">{{ number_format($sumTotalCost, 2) }}</td>
                    <td class="text-right font-bold @if($sumNetProfit >= 0) profit-text @else loss-text @endif">
                        {{ number_format($sumNetProfit, 2) }}
                    </td>
                    <td class="text-right font-bold @if($overallMargin >= 0) profit-text @else loss-text @endif">
                        {{ number_format($overallMargin, 2) }}%
                    </td>
                </tr>
            @endif
        </tbody>
    </table>

</body>
</html>
