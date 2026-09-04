<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Cancelled Dispatch Report</title>
    <style>
        @page {
            size: A4 landscape;
            margin: 20px;
        }
        body {
            font-family: 'DejaVu Sans', sans-serif;
            font-size: 8pt;
            color: #1e293b;
            margin: 0;
            padding: 0;
            line-height: 1.35;
        }

        .header-table {
            width: 100%;
            border-collapse: collapse;
            border: 0;
            margin-bottom: 15px;
        }
        .header-table td {
            border: 0;
            padding: 0;
            vertical-align: top;
        }
        
        .address-box {
            font-size: 8pt;
            color: #334155;
            line-height: 1.3;
        }
        .address-title {
            font-weight: bold;
            color: #e11d48;
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
            border-top: 2px solid #e11d48;
            border-bottom: 2px solid #e11d48;
            padding: 6px 0;
            margin-bottom: 12px;
            text-align: center;
        }
        .statement-title {
            font-size: 12pt;
            font-weight: bold;
            color: #be123c;
            text-transform: uppercase;
            letter-spacing: 1px;
            margin: 0;
        }
        .statement-subtitle {
            font-size: 8pt;
            color: #64748b;
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
            font-weight: bold;
            font-size: 7.5pt;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            padding: 6px 5px;
            border-bottom: 1.5px solid #cbd5e1;
            text-align: left;
        }
        .data-table td {
            padding: 5px;
            font-size: 7.5pt;
            border-bottom: 1px solid #f1f5f9;
            vertical-align: top;
        }
        .data-table tr:nth-child(even) td {
            background-color: #fafafa;
        }

        .text-right { text-align: right; }
        .text-center { text-align: center; }
        .font-bold { font-weight: bold; }
        .badge-cancelled {
            background-color: #ffe4e6;
            color: #be123c;
            padding: 2px 5px;
            border-radius: 4px;
            font-size: 6.5pt;
            font-weight: bold;
            display: inline-block;
            text-transform: uppercase;
        }

        .notes-cell {
            color: #475569;
            font-style: italic;
            line-height: 1.25;
            font-size: 7pt;
            max-width: 250px;
            word-wrap: break-word;
        }

        .summary-box {
            margin-top: 10px;
            border-top: 1.5px solid #cbd5e1;
            padding-top: 8px;
        }
        .summary-table {
            width: 100%;
            border-collapse: collapse;
        }
        .summary-table td {
            padding: 4px 8px;
            font-size: 8pt;
        }
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
