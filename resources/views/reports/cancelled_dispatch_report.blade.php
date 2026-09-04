<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Cancelled Dispatch Report</title>
    <style>
        {!! $css ?? $report_css ?? (file_exists(public_path('css/reports/cancelled_dispatch_report.css')) ? file_get_contents(public_path('css/reports/cancelled_dispatch_report.css')) : (file_exists(public_path('css/reports/report_pdf.css')) ? file_get_contents(public_path('css/reports/report_pdf.css')) : '')) !!}
    </style>
</head>
<body>
    <table class="header-table">
        <tr>
            <td style="width: 60%;">
                <div class="address-box">
                    <span class="address-title">Plant</span>
                    <span class="address-name">{{ $plant->name ?? 'MODO RMC' }}</span>
                    @if($plant && $plant->addresses && $plant->addresses->first())
                        @php $addr = $plant->addresses->first(); @endphp
                        {{ $addr->line_1 ?? '' }}{{ $addr->city ? ', ' . $addr->city : '' }}{{ $addr->state ? ', ' . $addr->state->name : '' }} - {{ $addr->zipcode ?? '' }}<br>
                    @endif
                    @if(!empty($plant->gstin))
                        <strong>GSTIN:</strong> {{ $plant->gstin }}
                    @endif
                </div>
            </td>
            <td style="width: 40%; text-align: right;">
                <div class="address-box">
                    <span class="address-title">Audit Report</span>
                    <strong style="font-size: 9pt; color: #0f172a;">Cancelled Dispatches & Batches</strong><br>
                    <strong>Period:</strong> {{ $start }} to {{ $end }}<br>
                    <strong>Generated:</strong> {{ now()->format('d-m-Y H:i') }}
                </div>
            </td>
        </tr>
    </table>

    <div class="statement-title-container">
        <h1 class="statement-title">CANCELLED DISPATCH REPORT</h1>
        <div class="statement-subtitle">Log of cancelled dispatches, batches, sales order reversals, and credit notes</div>
    </div>

    <table class="data-table">
        <thead>
            <tr>
                <th style="width: 7%;">Dispatch #</th>
                <th style="width: 7%;">Batch #</th>
                <th style="width: 13%;">Customer & Site</th>
                <th style="width: 11%;">Grade</th>
                <th style="width: 6%;">Truck</th>
                <th style="width: 6%;" class="text-right">Qty (m³)</th>
                <th style="width: 7%;" class="text-right">Total (₹)</th>
                <th style="width: 8%;">Inv / Credit Note</th>
                <th style="width: 9%;">Cancelled At / By</th>
                <th style="width: 26%;">Cancellation Reason / Notes (50+ words)</th>
            </tr>
        </thead>
        <tbody>
            @forelse($transactions ?? $items ?? [] as $row)
                <tr>
                    <td class="font-bold" style="color: #0f172a;">
                        {{ $row['dispatch_no'] ?? '-' }}<br>
                        <span class="badge-cancelled">Cancelled</span>
                    </td>
                    <td>
                        {{ $row['batch_no'] ?? '-' }}<br>
                        <span style="font-size: 6.5pt; color: #64748b;">SO: {{ $row['sales_order_no'] ?? '-' }}</span>
                    </td>
                    <td>
                        <strong style="color: #1e293b;">{{ $row['customer_name'] ?? '-' }}</strong><br>
                        <span style="font-size: 6.5pt; color: #64748b;">{{ $row['site_name'] ?? '-' }}</span>
                    </td>
                    <td>{{ $row['grade_name'] ?? '-' }}</td>
                    <td>{{ $row['truck_no'] ?? '-' }}</td>
                    <td class="text-right font-bold">{{ number_format($row['quantity'] ?? 0, 2) }}</td>
                    <td class="text-right">{{ number_format($row['amount_total'] ?? 0, 2) }}</td>
                    <td>
                        <span style="font-size: 7pt;">Inv: {{ $row['invoice_number'] ?? '-' }}</span><br>
                        <span style="font-size: 7pt; color: #0284c7;">CN: {{ $row['credit_note_number'] ?? '-' }}</span>
                    </td>
                    <td>
                        <span style="font-size: 7pt;">{{ $row['cancelled_at'] ?? '-' }}</span><br>
                        <span style="font-size: 6.5pt; color: #64748b;">By: {{ $row['cancelled_by'] ?? '-' }}</span>
                    </td>
                    <td class="notes-cell">
                        {{ $row['cancelled_notes'] ?? '-' }}
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="10" class="text-center" style="padding: 25px; color: #64748b;">
                        No cancelled dispatches found for the selected date range.
                    </td>
                </tr>
            @endforelse
        </tbody>
    </table>

    <div class="summary-box">
        <table class="summary-table">
            <tr>
                <td style="width: 30%;">
                    <strong>Total Cancelled Dispatches:</strong> {{ $total_cancelled_dispatches ?? count($transactions ?? []) }}
                </td>
                <td style="width: 35%; text-align: center;">
                    <strong>Total Reversed Volume:</strong> {{ number_format($total_quantity ?? 0, 2) }} m³
                </td>
                <td style="width: 35%; text-align: right;">
                    <strong>Total Reversed Value:</strong> ₹ {{ number_format($total_amount ?? 0, 2) }}
                </td>
            </tr>
        </table>
    </div>
</body>
</html>
