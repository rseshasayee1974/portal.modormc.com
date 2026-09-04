<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Patron Statement of Accounts</title>
    <style>
        {!! $css ?? $report_css ?? (file_exists(public_path('css/reports/patron_report.css')) ? file_get_contents(public_path('css/reports/patron_report.css')) : (file_exists(public_path('css/reports/report_pdf.css')) ? file_get_contents(public_path('css/reports/report_pdf.css')) : '')) !!}
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
        <h2 class="statement-title">Patron Statement of Accounts</h2>
        <span class="statement-period">Period: {{ \Carbon\Carbon::parse($start)->format('d-m-Y') }} to {{ \Carbon\Carbon::parse($end)->format('d-m-Y') }}</span>
    </div>

    @php
        $balance = (float)($opening_balance ?? 0);
        foreach($transactions as $trx) {
            $balance += (($trx['debit'] ?? 0) - ($trx['credit'] ?? 0));
        }
    @endphp

    @if(isset($patron) && $patron && (!empty($invoiced_tax) || !empty($invoiced_nontax) || !empty($purchased) || !empty($amount_received) || !empty($amount_paid)))
    <!-- Account Summary Box -->
    <table class="summary-table">
        <thead>
            <tr>
                <th colspan="2">Account Summary</th>
            </tr>
        </thead>
        <tbody>
            <tr>
                <td>Opening Balance</td>
                <td class="text-right font-bold">
                    {{ $opening_balance < 0 ? 'Cr' : 'Dr' }} ₹ {{ number_format(abs($opening_balance), 2) }}
                </td>
            </tr>
            <tr>
                <td>Invoiced (Tax)</td>
                <td class="text-right">{{ $invoiced_tax > 0 ? '₹ ' . number_format($invoiced_tax, 2) : '₹ 0.00' }}</td>
            </tr>
            <tr>
                <td>Invoiced (Non-Tax)</td>
                <td class="text-right">{{ $invoiced_nontax > 0 ? '₹ ' . number_format($invoiced_nontax, 2) : '₹ 0.00' }}</td>
            </tr>
            <tr style="font-weight: bold;">
                <td>Total Invoiced Amount</td>
                <td class="text-right">₹ {{ number_format(($invoiced_tax + $invoiced_nontax), 2) }}</td>
            </tr>
            <tr>
                <td>Sales Discount</td>
                <td class="text-right">{{ $sales_discount > 0 ? '₹ ' . number_format($sales_discount, 2) : '₹ 0.00' }}</td>
            </tr>
            <tr>
                <td>Purchased</td>
                <td class="text-right">{{ $purchased > 0 ? '₹ ' . number_format($purchased, 2) : '₹ 0.00' }}</td>
            </tr>
            <tr>
                <td>Amount Received</td>
                <td class="text-right">{{ $amount_received > 0 ? '₹ ' . number_format($amount_received, 2) : '₹ 0.00' }}</td>
            </tr>
            <tr>
                <td>Amount Paid</td>
                <td class="text-right">{{ $amount_paid > 0 ? '₹ ' . number_format($amount_paid, 2) : '₹ 0.00' }}</td>
            </tr>
            <tr class="balance-due-row">
                <td>Net Balance Due</td>
                <td class="text-right">
                    {{ $balance < 0 ? 'Cr' : 'Dr' }} ₹ {{ number_format(abs($balance), 2) }}
                </td>
            </tr>
        </tbody>
    </table>
    @endif

    <!-- Statement Table matching StandardLedgerReport.vue -->
    <table class="data-table">
        <thead>
            <tr>
                <th style="width: 12%;">Date</th>
                <th style="width: 40%;">Particulars</th>
                <th style="width: 16%;">Reference</th>
                <th style="width: 12%; text-align: right;">Amount</th>
                <th style="width: 8%; text-align: center;">Type</th>
                <th style="width: 12%; text-align: right;">Balance</th>
            </tr>
        </thead>
        <tbody>
            @php
                $running_balance = (float)($opening_balance ?? 0);
            @endphp

            <!-- Opening Balance Row -->
            <tr class="opening-row">
                <td style="color: #94a3b8; font-style: italic; font-size: 8pt;">{{ \Carbon\Carbon::parse($start)->format('d-m-Y') }}</td>
                <td class="font-bold" style="color: #1d2d3e; text-transform: uppercase;">Opening Balance</td>
                <td>---</td>
                <td class="text-right font-bold">₹ {{ number_format(abs($running_balance), 2) }}</td>
                <td class="text-center">
                    <span class="badge-dr">{{ $running_balance >= 0 ? 'DR' : 'CR' }}</span>
                </td>
                <td class="text-right font-bold" style="color: #1d2d3e;">
                    ₹ {{ number_format(abs($running_balance), 2) }}
                    <small style="font-size: 7.5pt; color: #94a3b8; text-transform: uppercase;">{{ $running_balance >= 0 ? 'Dr' : 'Cr' }}</small>
                </td>
            </tr>

            <!-- Transactions Rows -->
            @forelse($transactions as $trx)
                @php
                    $running_balance += (($trx['debit'] ?? 0) - ($trx['credit'] ?? 0));
                    $isDr = ($trx['type'] ?? 'Dr') === 'Dr';
                @endphp
                <tr>
                    <td style="color: #64748b;">{{ \Carbon\Carbon::parse($trx['date'])->format('d-m-Y') }}</td>
                    <td>
                        <div class="font-bold" style="color: #1e293b;">{{ $trx['narration'] ?? '-' }}</div>
                        <span class="badge-voucher">{{ $trx['voucher_type'] ?? 'JOURNAL' }}</span>
                    </td>
                    <td class="font-bold" style="color: #1e293b;">{{ $trx['voucher_no'] ?? '-' }}</td>
                    <td class="text-right font-bold" style="color: #0f172a;">₹ {{ number_format($trx['amount'] ?? 0, 2) }}</td>
                    <td class="text-center">
                        <span class="{{ $isDr ? 'badge-dr' : 'badge-cr' }}">{{ strtoupper($trx['type'] ?? 'Dr') }}</span>
                    </td>
                    <td class="text-right font-bold" style="color: #0f172a; background-color: #f8fafc;">
                        ₹ {{ number_format(abs($running_balance), 2) }}
                        <small style="font-size: 7.5pt; color: #94a3b8; text-transform: uppercase;">{{ $running_balance >= 0 ? 'Dr' : 'Cr' }}</small>
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="6" class="text-center" style="padding: 15px; color: #94a3b8; font-style: italic;">No patron transactions recorded for this period.</td>
                </tr>
            @endforelse

            <!-- Net Closing Balance Row -->
            <tr class="closing-row">
                <td colspan="3" class="text-right font-bold" style="padding: 10px 14px; text-transform: uppercase; font-size: 8.5pt; color: #cbd5e1;">
                    Net Closing Balance
                </td>
                <td colspan="3" class="text-right font-bold" style="padding: 10px 14px; font-size: 11pt; color: #ffffff;">
                    ₹ {{ number_format(abs($running_balance), 2) }}
                    <span style="font-size: 8.5pt; text-transform: uppercase; opacity: 0.8; margin-left: 4px;">{{ $running_balance >= 0 ? 'Debit' : 'Credit' }}</span>
                </td>
            </tr>
        </tbody>
    </table>

</body>
</html>
