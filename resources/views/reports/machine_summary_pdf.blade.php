<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Machine Summary Report</title>
    <style>
        {!! $css ?? $report_css ?? (file_exists(public_path('css/reports/machine_summary_pdf.css')) ? file_get_contents(public_path('css/reports/machine_summary_pdf.css')) : (file_exists(public_path('css/reports/report_pdf.css')) ? file_get_contents(public_path('css/reports/report_pdf.css')) : '')) !!}
        .alert-text { color: #b91c1c; font-weight: bold; font-size: 10px; }
    </style>
</head>
<body>

    <div class="title-container">
        <h2 class="report-title">Machine Summary Report</h2>
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
                <th>Type</th>
                <th>Make Year</th>
                <th>Capacity</th>
                <th>Owner</th>
                <th>Trips</th>
                <th>Qty</th>
                <th>Weight (Tons)</th>
                <th>Revenue</th>
                <th>Expenses</th>
                <th>Document Alerts</th>
            </tr>
        </thead>
        <tbody>
            @php
                $sumTrips = 0;
                $sumQty = 0;
                $sumWeight = 0;
                $sumRevenue = 0;
                $sumExpenses = 0;
            @endphp
            @forelse($items as $item)
                @php
                    $sumTrips += $item['trips_count'];
                    $sumQty += $item['total_qty'];
                    $sumWeight += $item['total_weight_tons'];
                    $sumRevenue += $item['total_revenue'];
                    $sumExpenses += $item['general_expenses'];
                @endphp
                <tr>
                    <td class="text-center font-bold">{{ $item['registration'] }}</td>
                    <td>{{ $item['vehicle_model'] }}</td>
                    <td class="text-center">{{ $item['vehicle_type'] }}</td>
                    <td class="text-center">{{ $item['make_year'] }}</td>
                    <td class="text-right">{{ $item['capacity'] }}</td>
                    <td>{{ $item['owner'] }}</td>
                    <td class="text-center">{{ number_format($item['trips_count']) }}</td>
                    <td class="text-right">{{ number_format($item['total_qty'], 2) }}</td>
                    <td class="text-right">{{ number_format($item['total_weight_tons'], 2) }}</td>
                    <td class="text-right">₹{{ number_format($item['total_revenue'], 2) }}</td>
                    <td class="text-right">₹{{ number_format($item['general_expenses'], 2) }}</td>
                    <td class="alert-text">
                        @foreach($item['alerts'] as $alert)
                            <div>{{ $alert['message'] }}</div>
                        @endforeach
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="12" class="text-center" style="padding: 15px;">No records found for the selected period.</td>
                </tr>
            @endforelse

            @if(!empty($items))
                <tr class="total-row">
                    <td colspan="6" class="text-center font-bold">TOTAL</td>
                    <td class="text-center font-bold">{{ number_format($sumTrips) }}</td>
                    <td class="text-right font-bold">{{ number_format($sumQty, 2) }}</td>
                    <td class="text-right font-bold">{{ number_format($sumWeight, 2) }}</td>
                    <td class="text-right font-bold">₹{{ number_format($sumRevenue, 2) }}</td>
                    <td class="text-right font-bold">₹{{ number_format($sumExpenses, 2) }}</td>
                    <td></td>
                </tr>
            @endif
        </tbody>
    </table>

</body>
</html>
