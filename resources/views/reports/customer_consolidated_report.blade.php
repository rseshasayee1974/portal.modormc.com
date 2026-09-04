<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Customer Consolidated Report</title>
    <style>
        {!! $css ?? $report_css ?? (file_exists(public_path('css/reports/customer_consolidated_report.css')) ? file_get_contents(public_path('css/reports/customer_consolidated_report.css')) : (file_exists(public_path('css/reports/report_pdf.css')) ? file_get_contents(public_path('css/reports/report_pdf.css')) : '')) !!}
    </style>
</head>
<body>

    <table class="header-table">
        <tr>
            <td style="width: 52%;">
                <div class="address-box" style="margin-top: 5px;">
                    <span class="address-title">Customer / Partner:</span>
                    @if(!empty($patron))
                        <span class="address-name">{{ $patron->legal_name }}</span>
                        @if($patron->addresses->isNotEmpty())
                            @php $pAddr = $patron->addresses->first(); @endphp
                            {{ $pAddr->line_1 ?? '' }}@if($pAddr->line_2), {{ $pAddr->line_2 }}@endif<br>
                            {{ $pAddr->city ?? '' }} - {{ $pAddr->zipcode ?? '' }}<br>
                        @endif
                        <strong>GSTIN:</strong> {{ $patron->gstin ?? '-' }}
                    @else
                        <span class="address-name">All Customers (Party wise Consolidated)</span>
                        {{-- <span>Consolidated dispatch summary with batch size, truck weights, and revenue</span> --}}
                    @endif
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
@if(!empty($batch_dispatches))
    <!-- Section 2: Customer Batching / Trip Verification List -->
    <div class="statement-title-container" style="margin-top: 25px; border-bottom: 2px solid #0284c7; padding-bottom: 4px;">
        <h3 style="font-size: 11pt; font-weight: bold; color: #0369a1; margin: 0; text-transform: uppercase;">Every Batching / Trip Verification List ({{ count($batch_dispatches) }} Trips)</h3>
        <span style="font-size: 8.5pt; color: #64748b;">Itemized trip dispatch tickets for customer batch count audit and verification</span>
    </div>

    <table class="data-table">
        <thead>
            <tr>
                <th width="4%">Trip #</th>
                <th width="12%">Date & Time</th>
                <th width="11%">Dispatch / DSP No</th>
                <th width="13%">Customer Name</th>
                <th width="11%">Unload Site</th>
                <th width="9%">Truck / Mixer</th>
                <th width="9%">Grade / Mix</th>
                <th width="6%">Deliv (m³)</th>
                <th width="5%">Empty (T)</th>
                <th width="5%">Load (T)</th>
                <th width="5%">Net (T)</th>
                <th width="10%">Total Amt (₹)</th>
            </tr>
        </thead>
        <tbody>
            @foreach($batch_dispatches as $index => $trip)
                <tr>
                    <td class="text-center font-bold">{{ $index + 1 }}</td>
                    <td class="text-center" style="font-size: 7.5pt;">{{ $trip['dispatch_time'] ?? '-' }}</td>
                    <td class="font-bold text-center">{{ $trip['docket_no'] ?? $trip['dispatch_no'] ?? '-' }}</td>
                    <td class="font-bold">{{ $trip['customer_name'] ?? '-' }}</td>
                    <td>{{ $trip['site_name'] ?? '-' }}</td>
                    <td class="text-center font-bold" style="color: #0369a1;">{{ $trip['truck_no'] ?? '-' }}</td>
                    <td class="text-center font-bold" style="color: #4338ca;">{{ $trip['concrete_grade'] ?? $trip['mix_name'] ?? '-' }}</td>
                    <td class="text-right font-bold">{{ number_format($trip['delivered_qty'] ?? 0, 2) }}</td>
                    <td class="text-right font-bold">{{ number_format($trip['empty_weight'] ?? 0, 2) }}</td>
                    <td class="text-right font-bold">{{ number_format($trip['loaded_weight'] ?? 0, 2) }}</td>
                    <td class="text-right font-bold">{{ number_format($trip['net_weight'] ?? 0, 2) }}</td>
                    <td class="text-right font-bold">{{ number_format($trip['amount_total'] ?? 0, 2) }}</td>
                </tr>
            @endforeach
            <tr class="total-row">
                <td colspan="7" class="text-center font-bold">Total Verified Batch Trips ({{ count($batch_dispatches) }} Trips)</td>
                <td class="text-right font-bold">{{ number_format(collect($batch_dispatches)->sum('delivered_qty'), 2) }}</td>
                <td class="text-right font-bold">{{ number_format(collect($batch_dispatches)->sum('empty_weight'), 2) }}</td>
                <td class="text-right font-bold">{{ number_format(collect($batch_dispatches)->sum('loaded_weight'), 2) }}</td>
                <td class="text-right font-bold">{{ number_format(collect($batch_dispatches)->sum('net_weight'), 2) }}</td>
                <td class="text-right font-bold">{{ number_format(collect($batch_dispatches)->sum('amount_total'), 2) }}</td>
            </tr>
        </tbody>
    </table>
    @endif
    <div class="statement-title-container">
        <h2 class="statement-title">Customer Consolidated Report (Customer / Party wise)</h2>
        <span class="statement-period">Reporting Period: {{ $start }} to {{ $end }}</span>
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
            @foreach(($transactions ?? $items ?? []) as $index => $row)
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
            
            <tr class="total-row">
                <td colspan="2" class="text-center font-bold">Total Customer Volume</td>
                <td class="text-center font-bold">{{ $total_trips ?? collect($transactions ?? $items ?? [])->sum('trips_count') }}</td>
                <!-- <td class="text-right font-bold">{{ number_format($total_batch_size ?? 0, 2) }}</td> -->
                <td class="text-right font-bold">{{ number_format($total_quantity ?? 0, 2) }}</td>
                <!-- <td class="text-right font-bold">{{ number_format($total_truck_empty ?? 0, 2) }}</td>
                <td class="text-right font-bold">{{ number_format($total_loaded_weight ?? 0, 2) }}</td>
                <td class="text-right font-bold">{{ number_format($total_net_weight ?? 0, 2) }}</td> -->
                <!-- <td class="text-right font-bold">{{ number_format($total_untaxed ?? 0, 2) }}</td>
                <td class="text-right font-bold">{{ number_format($total_tax ?? 0, 2) }}</td> -->
                <td class="text-right font-bold">{{ number_format($total_amount ?? 0, 2) }}</td>
            </tr>
        </tbody>
    </table>

    

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
