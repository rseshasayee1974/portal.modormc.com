<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Vehicle Wise Profit & Loss Report</title>
    <style>
        {!! $css ?? $report_css ?? (file_exists(public_path('css/reports/vehicle_pl_pdf.css')) ? file_get_contents(public_path('css/reports/vehicle_pl_pdf.css')) : (file_exists(public_path('css/reports/report_pdf.css')) ? file_get_contents(public_path('css/reports/report_pdf.css')) : '')) !!}
        .profit-text { color: #15803d; font-weight: bold; }
        .loss-text { color: #b91c1c; font-weight: bold; }
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
