<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Driver Report</title>
    <style>
        {!! $css ?? $report_css ?? (file_exists(public_path('css/reports/driver_report.css')) ? file_get_contents(public_path('css/reports/driver_report.css')) : (file_exists(public_path('css/reports/report_pdf.css')) ? file_get_contents(public_path('css/reports/report_pdf.css')) : '')) !!}
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

    {{-- @if(!empty($transactions) && count($transactions) > 0)
    <!-- Section 3: Driver Batching / Trip Verification List -->
    <div class="statement-title-container" style="margin-top: 25px; border-bottom: 2px solid #0284c7; padding-bottom: 4px;">
        <h3 style="font-size: 11pt; font-weight: bold; color: #0369a1; margin: 0; text-transform: uppercase;">Every Batching / Trip Verification List ({{ count($transactions) }} Trips)</h3>
        <span style="font-size: 8.5pt; color: #64748b;">Itemized driver trip dispatch tickets for trip count audit and verification</span>
    </div>

    <table class="data-table">
        <thead>
            <tr>
                <th width="4%">Trip #</th>
                <th width="12%">Date & Time</th>
                <th width="13%">Dispatch / DSP #</th>
                <th width="14%">Driver Name</th>
                <th width="10%">Truck / Mixer</th>
                <th width="15%">Customer Name</th>
                <th width="13%">Unload Site</th>
                <th width="9%">Grade / Mix</th>
                <th width="5%">Batch (m³)</th>
                <th width="5%">Deliv (m³)</th>
            </tr>
        </thead>
        <tbody>
            @foreach($transactions as $index => $trip)
                <tr>
                    <td class="text-center font-bold">{{ $index + 1 }}</td>
                    <td class="text-center" style="font-size: 7.5pt;">{{ $trip['datetime'] ?? $trip['date'] ?? '-' }}</td>
                    <td class="font-bold text-center">{{ $trip['dispatch_no'] ?? '-' }}</td>
                    <td class="font-bold">{{ $trip['driver_name'] ?? '-' }}</td>
                    <td class="text-center font-bold" style="color: #0369a1;">{{ $trip['truck_no'] ?? '-' }}</td>
                    <td class="font-bold">{{ $trip['customer_name'] ?? '-' }}</td>
                    <td>{{ $trip['site_name'] ?? '-' }}</td>
                    <td class="text-center font-bold" style="color: #4338ca;">{{ $trip['concrete_grade'] ?? $trip['mix_name'] ?? '-' }}</td>
                    <td class="text-right">{{ number_format($trip['batch_size'] ?? 0, 2) }}</td>
                    <td class="text-right font-bold">{{ number_format($trip['quantity'] ?? $trip['delivered_qty'] ?? 0, 2) }}</td>
                </tr>
            @endforeach
            <tr class="total-row">
                <td colspan="8" class="text-center font-bold">Total Driver Dispatches ({{ count($transactions) }} Trips)</td>
                <td class="text-right font-bold">{{ number_format(collect($transactions)->sum('batch_size'), 2) }}</td>
                <td class="text-right font-bold">{{ number_format(collect($transactions)->sum('quantity'), 2) }}</td>
            </tr>
        </tbody>
    </table>
    @endif --}}

    @include('pdfs.partials._footer')

</body>
</html>
