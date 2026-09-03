<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Unload Site Consolidated Report</title>
    <style>
        @page {
            size: A4 landscape;
            margin: 10mm 10mm 15mm 10mm;
            @bottom-right {
                content: "Page " counter(page) " of " counter(pages);
                font-size: 8pt;
                color: #64748b;
            }
        }
        body {
            font-family: 'Helvetica Neue', Helvetica, Arial, sans-serif;
            font-size: 8.5pt;
            color: #1e293b;
            line-height: 1.35;
            margin: 0;
            padding: 0;
        }
        .header-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 12px;
        }
        .header-table td {
            vertical-align: top;
            padding: 0;
        }
        .address-box {
            font-size: 8.5pt;
            line-height: 1.35;
            color: #475569;
        }
        .address-title {
            font-size: 8.5pt;
            font-weight: bold;
            color: #0f172a;
            text-transform: uppercase;
            margin-bottom: 2px;
            display: block;
        }
        .address-name {
            font-size: 10pt;
            font-weight: bold;
            color: #0f172a;
            display: block;
            margin-bottom: 2px;
        }
        .statement-title-container {
            border-bottom: 2px solid #0284c7;
            padding-bottom: 4px;
            margin-bottom: 10px;
        }
        .statement-title {
            font-size: 13pt;
            font-weight: bold;
            color: #0284c7;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin: 0;
            display: inline-block;
        }
        .statement-period {
            float: right;
            font-size: 9pt;
            font-weight: bold;
            color: #475569;
            margin-top: 3px;
        }
        .data-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 15px;
        }
        .data-table th {
            background-color: #f1f5f9;
            color: #334155;
            font-size: 7.5pt;
            font-weight: bold;
            text-transform: uppercase;
            letter-spacing: 0.3px;
            border-top: 1px solid #cbd5e1;
            border-bottom: 1px solid #cbd5e1;
            padding: 6px 4px;
            text-align: left;
        }
        .data-table td {
            padding: 5px 4px;
            border-bottom: 0.5px solid #e2e8f0;
            font-size: 8pt;
            color: #1e293b;
            vertical-align: middle;
        }
        .data-table tr:nth-child(even) td {
            background-color: #f8fafc;
        }
        .total-row td {
            background-color: #f1f5f9 !important;
            border-top: 1.5px solid #94a3b8;
            border-bottom: 1.5px solid #94a3b8;
            font-weight: bold;
            color: #0f172a;
            padding: 6px 4px;
            font-size: 8pt;
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
                    <span class="address-name">Unload Site Consolidated Overview</span>
                    <span>Consolidated dispatch summary grouped by delivery & unloading site destinations</span>
                </div>
            </td>
            
            <td style="width: 48%; text-align: right;">
                <div class="address-box">
                    <span class="address-name" style="color: #0284c7;">{{ $plant->name ?? 'Ready Mix Concrete Operations' }}</span>
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
        <h2 class="statement-title">Unload Site Consolidated Report</h2>
        <span class="statement-period">Reporting Period: {{ $start }} to {{ $end }}</span>
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
                <th width="12%">Total Amt</th>
            </tr>
        </thead>
        <tbody>
            @foreach(($transactions ?? $items ?? []) as $index => $row)
                <tr>
                    <td class="text-center">{{ $index + 1 }}</td>
                    <td class="font-bold">{{ $row['site_name'] }}</td>
                    <td>{{ $row['customer_name'] ?? '-' }}</td>
                    <td class="text-center font-bold">{{ $row['trips_count'] ?? 1 }}</td>
                    {{-- <td class="text-right">{{ number_format($row['batch_size'] ?? 0, 2) }}</td> --}}
                    <td class="text-right font-bold">{{ number_format($row['quantity'] ?? 0, 2) }}</td>
                    <td class="text-right font-bold">₹ {{ number_format($row['amount_total'] ?? 0, 2) }}</td>
                </tr>
            @endforeach
            
            <tr class="total-row">
                <td colspan="3" class="text-center font-bold">Total Site Volume</td>
                <td class="text-center font-bold">{{ $total_trips ?? collect($transactions ?? $items ?? [])->sum('trips_count') }}</td>
                {{-- <td class="text-right font-bold">{{ number_format($total_batch_size ?? 0, 2) }}</td> --}}
                <td class="text-right font-bold">{{ number_format($total_quantity ?? 0, 2) }}</td>
                <td class="text-right font-bold">₹ {{ number_format($total_amount ?? 0, 2) }}</td>
            </tr>
        </tbody>
    </table>

    @if(!empty($batch_dispatches) && count($batch_dispatches) > 0)
    <!-- Section: Unload Site Batching / Trip Verification List -->
    <div class="statement-title-container" style="margin-top: 25px; border-bottom: 2px solid #0284c7; padding-bottom: 4px;">
        <h3 style="font-size: 11pt; font-weight: bold; color: #0369a1; margin: 0; text-transform: uppercase;">Every Batching / Trip Verification List ({{ count($batch_dispatches) }} Trips)</h3>
        <span style="font-size: 8.5pt; color: #64748b;">Itemized site trip dispatch tickets for audit and delivery verification</span>
    </div>

    <table class="data-table">
        <thead>
            <tr>
                <th width="4%">Trip #</th>
                <th width="12%">Date & Time</th>
                <th width="13%">Dispatch / DSP #</th>
                <th width="15%">Unload Site</th>
                <th width="15%">Customer Name</th>
                <th width="10%">Truck / Mixer</th>
                <th width="13%">Grade / Mix</th>
                <th width="6%">Batch (m³)</th>
                <th width="6%">Deliv (m³)</th>
                <th width="6%">Total Amt</th>
            </tr>
        </thead>
        <tbody>
            @foreach($batch_dispatches as $index => $trip)
                <tr>
                    <td class="text-center font-bold">{{ $index + 1 }}</td>
                    <td class="text-center" style="font-size: 7.5pt;">{{ $trip['dispatch_time'] ?? '-' }}</td>
                    <td class="font-bold text-center">{{ $trip['docket_no'] ?? $trip['dispatch_no'] ?? '-' }}</td>
                    <td class="font-bold">{{ $trip['site_name'] ?? '-' }}</td>
                    <td>{{ $trip['customer_name'] ?? '-' }}</td>
                    <td class="text-center font-bold" style="color: #0369a1;">{{ $trip['truck_no'] ?? '-' }}</td>
                    <td class="text-center font-bold" style="color: #4338ca;">{{ $trip['concrete_grade'] ?? $trip['mix_name'] ?? '-' }}</td>
                    <td class="text-right">{{ number_format($trip['batch_size'] ?? 0, 2) }}</td>
                    <td class="text-right font-bold">{{ number_format($trip['delivered_qty'] ?? $trip['quantity'] ?? 0, 2) }}</td>
                    <td class="text-right font-bold">₹ {{ number_format($trip['amount_total'] ?? 0, 2) }}</td>
                </tr>
            @endforeach
            <tr class="total-row">
                <td colspan="7" class="text-center font-bold">Total Batch Dispatches ({{ count($batch_dispatches) }} Trips)</td>
                <td class="text-right font-bold">{{ number_format(collect($batch_dispatches)->sum('batch_size'), 2) }}</td>
                <td class="text-right font-bold">{{ number_format(collect($batch_dispatches)->sum('delivered_qty'), 2) }}</td>
                <td class="text-right font-bold">₹ {{ number_format(collect($batch_dispatches)->sum('amount_total'), 2) }}</td>
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
