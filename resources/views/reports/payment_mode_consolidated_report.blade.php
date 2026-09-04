<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Payment Mode Consolidated Report</title>
    <style>
        {!! $css ?? $report_css ?? (file_exists(public_path('css/reports/payment_mode_consolidated_report.css')) ? file_get_contents(public_path('css/reports/payment_mode_consolidated_report.css')) : (file_exists(public_path('css/reports/report_pdf.css')) ? file_get_contents(public_path('css/reports/report_pdf.css')) : '')) !!}
    </style>
</head>
<body>

    <table class="header-table">
        <tr>
            <td style="width: 52%;">
                <div class="address-box" style="margin-top: 5px;">
                    @if(!empty($customer))
                        <span class="address-title">Customer / Account:</span>
                        <span class="address-name">{{ $customer->legal_name ?? $customer->name ?? '' }}</span>
                        <span>{{ $customer->phone ?? '' }}</span>
                    @elseif(!empty($target_name) && $target_name !== 'Payment Mode Consolidated Report')
                        <span class="address-title">Customer / Account:</span>
                        <span class="address-name">{{ $target_name }}</span>
                    @else
                        <span class="address-title">Report Focus:</span>
                        <span class="address-name">Payment Mode Consolidated Overview</span>
                        <span>Consolidated dispatch metrics grouped by settlement and payment terms</span>
                    @endif
                </div>
            </td>
            
            <td style="width: 48%; text-align: right;">
                <div class="address-box">
                    <span class="address-name" style="color: #0d9488;">{{ $plant->name ?? 'Ready Mix Concrete Operations' }}</span>
                    @if(!empty($plant->addresses) && $plant->addresses->isNotEmpty())
                        @php $addr = $plant->addresses->first(); @endphp
                        {{ $addr->line_1 ?? '' }}@if($addr->line_2), {{ $addr->line_2 }}@endif<br>
                        {{ $addr->city ?? '' }} - {{ $addr->zipcode ?? '' }}@if(!empty($addr->state)), {{ $addr->state->name }}@endif<br>
                    @endif
                    @if(!empty($plant->gstin))
                        <strong>GSTIN:</strong> {{ $plant->gstin }}<br>
                    @endif
                </div>
            </td>
        </tr>
    </table>

    <div class="statement-title-container">
        <h2 class="statement-title">Payment Mode Consolidated Report</h2>
        <span class="statement-period">Reporting Period: {{ $start }} to {{ $end }}</span>
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
            @foreach(($transactions ?? $items ?? []) as $index => $row)
                <tr>
                    <td class="text-center">{{ $index + 1 }}</td>
                    <td class="font-bold">{{ $row['payment_mode'] }}</td>
                    <td class="text-center font-bold">{{ $row['trips_count'] ?? 1 }}</td>
                    <!-- <td class="text-right">{{ number_format($row['batch_size'] ?? 0, 2) }}</td> -->
                    <td class="text-right font-bold">{{ number_format($row['quantity'] ?? 0, 2) }}</td>
                    <td class="text-right font-bold">{{ number_format($row['amount_total'] ?? 0, 2) }}</td>
                </tr>
            @endforeach
            
            <tr class="total-row">
                <td colspan="2" class="text-center font-bold">Total Payment Modes</td>
                <td class="text-center font-bold">{{ $total_trips ?? collect($transactions ?? $items ?? [])->sum('trips_count') }}</td>
                <!-- <td class="text-right font-bold">{{ number_format($total_batch_size ?? 0, 2) }}</td> -->
                <td class="text-right font-bold">{{ number_format($total_quantity ?? 0, 2) }}</td>
                <td class="text-right font-bold">{{ number_format($total_amount ?? 0, 2) }}</td>
            </tr>
        </tbody>
    </table>

    @if(!empty($batch_dispatches) && count($batch_dispatches) > 0)
    <!-- Section: Payment Mode Batching / Trip Verification List -->
    <div class="statement-title-container" style="margin-top: 25px; border-bottom: 2px solid #0284c7; padding-bottom: 4px;">
        <h3 style="font-size: 11pt; font-weight: bold; color: #0369a1; margin: 0; text-transform: uppercase;">Every Batching / Trip Verification List ({{ count($batch_dispatches) }} Trips)</h3>
        <span style="font-size: 8.5pt; color: #64748b;">Itemized payment mode trip dispatch tickets for audit and settlement verification</span>
    </div>

    <table class="data-table">
        <thead>
            <tr>
                <th width="4%">Trip #</th>
                <th width="12%">Date & Time</th>
                <th width="13%">Dispatch / DSP #</th>
                <th width="12%">Payment Mode</th>
                <th width="15%">Customer Name</th>
                <th width="14%">Unload Site</th>
                <th width="10%">Truck / Mixer</th>
                <th width="12%">Grade / Mix</th>
                <th width="6%">Deliv (m³)</th>
                <th width="6%">Total Amt (₹)</th>
            </tr>
        </thead>
        <tbody>
            @foreach($batch_dispatches as $index => $trip)
                <tr>
                    <td class="text-center font-bold">{{ $index + 1 }}</td>
                    <td class="text-center" style="font-size: 7.5pt;">{{ $trip['dispatch_time'] ?? '-' }}</td>
                    <td class="font-bold text-center">{{ $trip['docket_no'] ?? $trip['dispatch_no'] ?? '-' }}</td>
                    <td class="font-bold text-center" style="color: #047857;">{{ $trip['payment_mode'] ?? '-' }}</td>
                    <td class="font-bold">{{ $trip['customer_name'] ?? '-' }}</td>
                    <td>{{ $trip['site_name'] ?? '-' }}</td>
                    <td class="text-center font-bold" style="color: #0369a1;">{{ $trip['truck_no'] ?? '-' }}</td>
                    <td class="text-center font-bold" style="color: #4338ca;">{{ $trip['concrete_grade'] ?? $trip['mix_name'] ?? '-' }}</td>
                    <td class="text-right font-bold">{{ number_format($trip['delivered_qty'] ?? $trip['quantity'] ?? 0, 2) }}</td>
                    <td class="text-right font-bold">{{ number_format($trip['amount_total'] ?? 0, 2) }}</td>
                </tr>
            @endforeach
            <tr class="total-row">
                <td colspan="8" class="text-center font-bold">Total Batch Dispatches ({{ count($batch_dispatches) }} Trips)</td>
                <td class="text-right font-bold">{{ number_format(collect($batch_dispatches)->sum('delivered_qty'), 2) }}</td>
                <td class="text-right font-bold">{{ number_format(collect($batch_dispatches)->sum('amount_total'), 2) }}</td>
            </tr>
        </tbody>
    </table>
    @endif

    <div style="margin-top: 30px; border-top: 1px solid #cbd5e1; padding-top: 8px; font-size: 8pt; color: #64748b; display: table; width: 100%;">
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
