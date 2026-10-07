<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>General Ledger Statement</title>
    <style>
        {!! $css ?? $report_css ?? (file_exists(public_path('css/reports/ledger_report.css')) ? file_get_contents(public_path('css/reports/ledger_report.css')) : (file_exists(public_path('css/reports/report_pdf.css')) ? file_get_contents(public_path('css/reports/report_pdf.css')) : '')) !!}
        @page { margin: 12mm 10mm; }
        .data-table { width: 100%; table-layout: fixed; font-size: 7pt; }
        .data-table th, .data-table td { padding: 4px 3px; }
        .data-table td { vertical-align: top; overflow-wrap: break-word; word-wrap: break-word; }
        .data-table th:nth-child(1), .data-table td:nth-child(1),
        .data-table th:nth-child(6), .data-table td:nth-child(6),
        .data-table th:nth-child(7), .data-table td:nth-child(7),
        .data-table th:nth-child(8), .data-table td:nth-child(8) { white-space: nowrap; }
    </style>
</head>
<body>

    <!-- Subtle Diagonal Watermark -->
    <div class="watermark">{{ $plant->name ?? 'DEMO LOGIN' }}</div>

    <!-- Header section with logo and addresses in a borderless table layout -->
    <table class="header-table">
        <tr>
            <td style="width: 52%;">
                <div class="address-box" style="margin-top: 5px;">
                    <span class="address-title">Scope / Target:</span>
                    <span class="address-name">{{ $target_name }}</span>
                    @if(isset($patron) && $patron)
                        @if($patron->addresses->isNotEmpty())
                            @php $pAddr = $patron->addresses->first(); @endphp
                            {{ $pAddr->line_1 ?? '' }}@if($pAddr->line_2), {{ $pAddr->line_2 }}@endif<br>
                            {{ $pAddr->city ?? '' }} - {{ $pAddr->zipcode ?? '' }}<br>
                        @endif
                        <strong>GSTIN/UIN # :</strong> {{ $patron->gstin ?? 'N/A' }}<br>
                    @endif
                </div>
            </td>
            
            <td style="width: 48%; text-align: right;">
                <!-- Company / Plant details -->
                <div class="address-box" style="margin-top: 5px; padding-left: 20px;">
                    <span class="address-title">Address:</span>
                    <span class="address-name">{{ $plant->name ?? 'DEMO LOGIN' }}</span>
                    @if($plant && $plant->addresses->isNotEmpty())
                        @php $plAddr = $plant->addresses->first(); @endphp
                        {{ $plAddr->line_1 ?? '' }}<br>
                        @if($plAddr->line_2){{ $plAddr->line_2 }}<br>@endif
                        {{ $plAddr->city ?? '' }}, {{ $plAddr->state->state_name ?? $plAddr->state_code ?? '' }} - {{ $plAddr->zipcode ?? '' }}<br>
                    @else
                        3/150, Akkiyampatti (Po),<br>
                        Sendamangalam (Tk),<br>
                        Namakkal (Dt), Tamil Nadu - 637409<br>
                    @endif
                    <strong>GSTIN/UIN :</strong> {{ $plant->gstin ?? '' }}<br>
                </div>
            </td>
        </tr>
    </table>

    <!-- Statement Title Bar -->
    <div class="statement-title-container">
        <h2 class="statement-title">General Ledger Statement</h2>
        <span class="statement-period">Period: {{ \Carbon\Carbon::parse($start)->format('d-m-Y') }} to {{ \Carbon\Carbon::parse($end)->format('d-m-Y') }}</span>
    </div>

    <!-- Statement Table matching StandardLedgerReport.vue -->
    <table class="data-table">
        <thead>
            <tr>
                <th style="width: 9%;">Date</th>
                <th style="width: 14%;">Ledger / Journal</th>
                <th style="width: 26%;">Particulars</th>
                <th style="width: 8%;">Voucher Type</th>
                <th style="width: 11%;">Reference</th>
                <th style="width: 10%; text-align: right;">Debit</th>
                <th style="width: 10%; text-align: right;">Credit</th>
                <th style="width: 12%; text-align: right;">Balance</th>
            </tr>
        </thead>
        <tbody>
            @php
                $balance = (float)($opening_balance ?? 0);
            @endphp

            <!-- Opening Balance Row -->
            <tr class="opening-row">
                <td style="color: #94a3b8; font-style: italic; font-size: 8pt;">{{ \Carbon\Carbon::parse($start)->format('d-m-Y') }}</td>
                <td>---</td>
                <td class="font-bold" style="color: #1d2d3e; text-transform: uppercase;">Opening Balance</td>
                <td>---</td>
                <td>---</td>
                <td class="text-right font-bold">{{ $balance > 0 ? '₹ ' . number_format($balance, 2) : '---' }}</td>
                <td class="text-right font-bold">{{ $balance < 0 ? '₹ ' . number_format(abs($balance), 2) : '---' }}</td>
                <td class="text-right font-bold" style="color: #1d2d3e;">
                    ₹ {{ number_format(abs($balance), 2) }}
                    {{-- <small style="font-size: 7.5pt; color: #94a3b8; text-transform: uppercase;">{{ $balance >= 0 ? 'Dr' : 'Cr' }}</small> --}}
                </td>
            </tr>

            <!-- Transactions Rows -->
            @forelse($transactions as $trx)
                @php
                    $balance += (($trx['debit'] ?? 0) - ($trx['credit'] ?? 0));
                @endphp
                <tr>
                    <td style="color: #64748b;">{{ \Carbon\Carbon::parse($trx['date'])->format('d-m-Y') }}</td>
                    <td class="font-bold" style="color: #1e293b;">{{ $trx['ledger_name'] ?? 'General Account' }}</td>
                    <td>
                        <div class="font-bold" style="color: #1e293b;">{{ $trx['narration'] ?? '-' }}</div>
                    </td>
                    <td><span class="badge-voucher">{{ $trx['voucher_type'] ?? 'JOURNAL' }}</span></td>
                    <td class="font-bold" style="color: #1e293b;">{{ $trx['voucher_no'] ?? '-' }}</td>
                    <td class="text-right font-bold" style="color: #0f172a;">{{ ($trx['debit'] ?? 0) > 0 ? '₹ ' . number_format($trx['debit'], 2) : '---' }}</td>
                    <td class="text-right font-bold" style="color: #0f172a;">{{ ($trx['credit'] ?? 0) > 0 ? '₹ ' . number_format($trx['credit'], 2) : '---' }}</td>
                    <td class="text-right font-bold" style="color: #0f172a; background-color: #f8fafc;">
                        ₹ {{ number_format(abs($balance), 2) }}
                        {{-- <small style="font-size: 7.5pt; color: #94a3b8; text-transform: uppercase;">{{ $balance >= 0 ? 'Dr' : 'Cr' }}</small> --}}
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="8" class="text-center" style="padding: 15px; color: #94a3b8; font-style: italic;">No ledger entries recorded for this period.</td>
                </tr>
            @endforelse

            <!-- Net Closing Balance Row -->
            <tr class="closing-row">
                <td colspan="5" class="text-right font-bold" style="padding: 10px 14px; text-transform: uppercase; font-size: 8.5pt; color: #cbd5e1;">
                    Net Closing Balance
                </td>
                <td colspan="3" class="text-right font-bold" style="padding: 10px 14px; font-size: 11pt; color: #ffffff;">
                    ₹ {{ number_format(abs($balance), 2) }}
                    <span style="font-size: 8.5pt; text-transform: uppercase; opacity: 0.8; margin-left: 4px;">{{ $balance >= 0 ? 'Debit' : 'Credit' }}</span>
                </td>
            </tr>
        </tbody>
    </table>

</body>
</html>
