<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Receipt Log Statement</title>
    <style>
        {!! $css ?? $report_css ?? (file_exists(public_path('css/reports/receipt_report.css')) ? file_get_contents(public_path('css/reports/receipt_report.css')) : (file_exists(public_path('css/reports/report_pdf.css')) ? file_get_contents(public_path('css/reports/report_pdf.css')) : '')) !!}
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
                    <span class="address-name">{{ $target_name ?? 'All Receipt Vouchers' }}</span>
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
        <h2 class="statement-title">Receipt Log Statement</h2>
        <span class="statement-period">Period: {{ !empty($start) ? \Carbon\Carbon::parse($start)->format('d-m-Y') : '-' }} to {{ !empty($end) ? \Carbon\Carbon::parse($end)->format('d-m-Y') : '-' }}</span>
    </div>

    <!-- Statement Table -->
    <table class="data-table">
        <thead>
            <tr>
                <th style="width: 5%; text-align: center;">#</th>
                <th style="width: 10%; text-align: center;">Date</th>
                <th style="width: 15%;">Voucher / Ref #</th>
                <th style="width: 22%;">Received From (Party)</th>
                <th style="width: 18%;">Received Into (Account)</th>
                <th style="width: 10%; text-align: center;">Mode</th>
                <th style="width: 8%; text-align: center;">Status</th>
                <th style="width: 12%; text-align: right;">Amount (₹)</th>
            </tr>
        </thead>
        <tbody>
            @php
                $grandTotal = 0;
                $rows = $transactions ?? ($data['transactions'] ?? []);
            @endphp

            <!-- Transactions Rows -->
            @forelse($rows as $idx => $trx)
                @php
                    $amt = (float)($trx['amount'] ?? 0);
                    $grandTotal += $amt;
                @endphp
                <tr>
                    <td style="text-align: center; color: #64748b;">{{ $idx + 1 }}</td>
                    <td style="text-align: center; color: #64748b;">{{ !empty($trx['date']) ? \Carbon\Carbon::parse($trx['date'])->format('d-m-Y') : '-' }}</td>
                    <td>
                        <strong style="color: #1e293b;">{{ $trx['voucher_no'] ?? '-' }}</strong>
                    </td>
                    <td>
                        <div style="font-weight: bold; color: #1e293b;">{{ $trx['party_name'] ?? 'Direct / Unspecified' }}</div>
                    </td>
                    <td style="color: #475569;">
                        {{ $trx['account_name'] ?? 'Bank / Cash' }}
                    </td>
                    <td style="text-align: center;">
                        <span style="font-size: 8pt; background-color: #f1f5f9; padding: 2px 6px; border-radius: 3px; color: #475569;">
                            {{ $trx['payment_mode'] ?? 'Cash' }}
                        </span>
                    </td>
                    <td style="text-align: center;">
                        <span class="badge-cr" style="font-size: 7.5pt;">{{ $trx['status'] ?? 'Received' }}</span>
                    </td>
                    <td class="text-right font-bold" style="color: #0f172a; background-color: #f8fafc;">
                        ₹ {{ number_format($amt, 2) }}
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="8" class="text-center" style="padding: 15px; color: #94a3b8; font-style: italic;">No receipt vouchers recorded for this period.</td>
                </tr>
            @endforelse

            <!-- Total Summary Row -->
            <tr class="closing-row">
                <td colspan="7" class="text-right font-bold" style="padding: 10px 14px; text-transform: uppercase; font-size: 8.5pt; color: #cbd5e1;">
                    Total Receipts ({{ count($rows) }} Vouchers)
                </td>
                <td class="text-right font-bold" style="padding: 10px 14px; font-size: 11pt; color: #ffffff;">
                    ₹ {{ number_format($grandTotal, 2) }}
                </td>
            </tr>
        </tbody>
    </table>

</body>
</html>
