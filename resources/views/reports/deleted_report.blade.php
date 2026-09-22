<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Deleted Report</title>
    <style>
        @page {
            size: A4 landscape !important;
            margin: 8mm;
        }
        * {
            box-sizing: border-box;
        }
        body {
            font-family: 'DejaVu Sans', sans-serif;
            font-size: 7.5pt;
            color: #1e293b;
            margin: 5px;
            line-height: 1.3;
        }
        .header-table {
            width: 100%;
            margin-bottom: 10px;
            border-collapse: collapse;
        }
        .header-table td {
            border: 0;
            padding: 3px 5px;
            vertical-align: top;
        }
        .address-box {
            font-size: 8pt;
            line-height: 1.35;
        }
        .address-title {
            font-size: 7pt;
            font-weight: bold;
            color: #64748b;
            text-transform: uppercase;
            display: block;
        }
        .address-name {
            font-size: 11pt;
            font-weight: bold;
            color: #0f172a;
            display: block;
            margin-bottom: 2px;
        }
        .statement-title-container {
            background: #1e293b;
            color: #ffffff;
            padding: 8px 12px;
            text-align: center;
            border-radius: 4px;
            margin-bottom: 12px;
        }
        .statement-title {
            font-size: 13pt;
            font-weight: bold;
            margin: 0;
            letter-spacing: 0.5px;
        }
        .statement-subtitle {
            font-size: 8pt;
            color: #94a3b8;
            margin-top: 3px;
        }
        .kpi-table {
            width: 100%;
            margin-bottom: 12px;
            border-collapse: separate;
            border-spacing: 6px;
        }
        .kpi-card {
            background: #f8fafc;
            border: 1px solid #cbd5e1;
            padding: 6px 10px;
            text-align: center;
            border-radius: 4px;
        }
        .kpi-card.danger {
            background: #fff1f2;
            border-color: #fecdd3;
        }
        .kpi-label {
            font-size: 6.5pt;
            font-weight: bold;
            text-transform: uppercase;
            color: #64748b;
            display: block;
        }
        .kpi-card.danger .kpi-label {
            color: #e11d48;
        }
        .kpi-value {
            font-size: 11pt;
            font-weight: bold;
            color: #0f172a;
            margin-top: 2px;
            display: block;
        }
        .kpi-card.danger .kpi-value {
            color: #be123c;
        }
        .data-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 7pt;
            margin-bottom: 15px;
        }
        .data-table th {
            background: #0f172a;
            color: #ffffff;
            font-weight: bold;
            text-align: left;
            padding: 5px 6px;
            font-size: 6.8pt;
            text-transform: uppercase;
            border: 1px solid #334155;
        }
        .data-table td {
            padding: 4px 6px;
            border: 1px solid #e2e8f0;
            vertical-align: top;
        }
        .data-table tbody tr:nth-child(even) {
            background: #f8fafc;
        }
        .badge {
            display: inline-block;
            padding: 1px 5px;
            font-size: 6pt;
            font-weight: bold;
            border-radius: 3px;
            text-transform: uppercase;
        }
        .badge-invoice { background: #dbeafe; color: #1e40af; border: 1px solid #bfdbfe; }
        .badge-bill { background: #f3e8ff; color: #6b21a8; border: 1px solid #e9d5ff; }
        .badge-payment { background: #fef3c7; color: #92400e; border: 1px solid #fde68a; }
        .badge-receipt { background: #d1fae5; color: #065f46; border: 1px solid #a7f3d0; }
        .badge-batch { background: #cffafe; color: #155e75; border: 1px solid #a5f3fc; }
        .badge-dispatch { background: #ffedd5; color: #9a3412; border: 1px solid #fed7aa; }
        .badge-expense { background: #fee2e2; color: #991b1b; border: 1px solid #fecaca; }
        .badge-ewaybill { background: #e0e7ff; color: #3730a3; border: 1px solid #c7d2fe; }
        .badge-journal { background: #ede9fe; color: #5b21b6; border: 1px solid #ddd6fe; }
        .badge-discount { background: #fce7f3; color: #9d174d; border: 1px solid #fbcfe8; }
        .badge-default { background: #f1f5f9; color: #475569; border: 1px solid #cbd5e1; }
        .text-right { text-align: right; }
        .text-center { text-align: center; }
        .font-bold { font-weight: bold; }
        .summary-box {
            background: #f1f5f9;
            border: 1px solid #cbd5e1;
            padding: 8px 12px;
            border-radius: 4px;
        }
        .summary-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 8pt;
        }
        .summary-table td {
            border: 0;
            padding: 2px 4px;
        }
    </style>
</head>
<body>
    <table class="header-table">
        <tr>
            <td style="width: 55%;">
                <div class="address-box">
                    <span class="address-title">Plant / Organization</span>
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
            <td style="width: 45%; text-align: right;">
                <div class="address-box">
                    <span class="address-title">Audit Log</span>
                    <strong style="font-size: 9pt; color: #be123c;">DELETED & CANCELLED RECORDS</strong><br>
                    <strong>Period:</strong> {{ $start }} to {{ $end }}<br>
                    @if(!empty($patron))
                        <strong>Filtered Customer:</strong> {{ $patron->legal_name }}<br>
                    @endif
                    <strong>Generated:</strong> {{ now()->format('d-m-Y H:i') }}
                </div>
            </td>
        </tr>
    </table>

    <div class="statement-title-container">
        <h1 class="statement-title">DELETED REPORT</h1>
        <div class="statement-subtitle">Audit trail of deleted invoices, bills, payments, receipts, batches, dispatches, expenses, e-way bills, journals & discounts</div>
    </div>

    @php
        $rows = $transactions ?? $items ?? [];
        $totalCount = $total_deleted ?? count($rows);
        $totalVal = $total_amount ?? collect($rows)->sum('amount');
        $counts = $type_counts ?? [];
    @endphp

    <table class="kpi-table">
        <tr>
            <td class="kpi-card danger" style="width: 25%;">
                <span class="kpi-label">Total Deleted Records</span>
                <span class="kpi-value">{{ number_format($totalCount) }}</span>
            </td>
            <td class="kpi-card danger" style="width: 25%;">
                <span class="kpi-label">Total Monetary Value</span>
                <span class="kpi-value">₹ {{ number_format($totalVal, 2) }}</span>
            </td>
            <td class="kpi-card" style="width: 50%;">
                <span class="kpi-label">Category Breakdown</span>
                <span style="font-size: 7pt; color: #334155; margin-top: 3px; display: block;">
                    @forelse($counts as $cat => $cnt)
                        <strong>{{ $cat }}:</strong> {{ $cnt }}@if(!$loop->last) &bull; @endif
                    @empty
                        No breakdown data available
                    @endforelse
                </span>
            </td>
        </tr>
    </table>

    <table class="data-table">
        <thead>
            <tr>
                <th style="width: 3%;" class="text-center">#</th>
                <th style="width: 9%;">Type</th>
                <th style="width: 11%;">Doc / Ref #</th>
                <th style="width: 17%;">Customer / Party</th>
                <th style="width: 8%;">Doc Date</th>
                <th style="width: 10%;">Deleted At</th>
                <th style="width: 10%;">Deleted By</th>
                <th style="width: 10%;" class="text-right">Amount (₹) / Qty</th>
                <th style="width: 22%;">Details / Notes</th>
            </tr>
        </thead>
        <tbody>
            @forelse($rows as $idx => $row)
                @php
                    $eType = $row['entity_type'] ?? 'Unknown';
                    $badgeClass = match(strtolower(str_replace(['-', ' '], '', $eType))) {
                        'invoice' => 'badge-invoice',
                        'bill' => 'badge-bill',
                        'payment' => 'badge-payment',
                        'receipt' => 'badge-receipt',
                        'batch' => 'badge-batch',
                        'dispatch' => 'badge-dispatch',
                        'expense' => 'badge-expense',
                        'ewaybill' => 'badge-ewaybill',
                        'journalentry', 'journal' => 'badge-journal',
                        'discount' => 'badge-discount',
                        default => 'badge-default'
                    };
                @endphp
                <tr>
                    <td class="text-center" style="color: #64748b;">{{ $idx + 1 }}</td>
                    <td>
                        <span class="badge {{ $badgeClass }}">{{ $eType }}</span>
                    </td>
                    <td class="font-bold" style="color: #0f172a;">
                        {{ $row['reference_no'] ?? '-' }}
                    </td>
                    <td>
                        <strong style="color: #1e293b;">{{ $row['customer_name'] ?? 'N/A' }}</strong>
                    </td>
                    <td>{{ $row['original_date'] ?? '-' }}</td>
                    <td>
                        <strong style="color: #be123c;">{{ $row['deleted_at'] ?? '-' }}</strong>
                    </td>
                    <td>
                        <span style="color: #475569;">{{ $row['deleted_by'] ?? 'System' }}</span>
                    </td>
                    <td class="text-right font-bold">
                        {{ number_format($row['amount'] ?? 0, 2) }}
                    </td>
                    <td style="color: #475569; font-size: 6.5pt;">
                        {{ $row['notes'] ?? '-' }}
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="9" class="text-center" style="padding: 20px; color: #64748b;">
                        No deleted records found for the selected date range and filters.
                    </td>
                </tr>
            @endforelse
        </tbody>
    </table>

    <div class="summary-box">
        <table class="summary-table">
            <tr>
                <td style="width: 35%;">
                    <strong>Total Deleted Records:</strong> {{ number_format($totalCount) }}
                </td>
                <td style="width: 35%; text-align: center;">
                    <strong>Filtered Customer:</strong> {{ !empty($patron) ? $patron->legal_name : 'All Customers / Parties' }}
                </td>
                <td style="width: 30%; text-align: right;">
                    <strong>Total Deleted Value:</strong> ₹ {{ number_format($totalVal, 2) }}
                </td>
            </tr>
        </table>
    </div>
</body>
</html>
