<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Ledger Statement</title>
    <style>
        @page {
            margin: 35px;
        }
        body {
            font-family: 'DejaVu Sans', sans-serif;
            font-size: 9.5pt;
            color: #1e293b;
            margin: 0;
            padding: 0;
            line-height: 1.4;
        }
        
        /* Watermark */
        .watermark {
            position: fixed;
            top: 55%;
            left: 50%;
            transform: translate(-50%, -50%) rotate(-35deg);
            font-size: 55pt;
            color: rgba(226, 232, 240, 0.22);
            z-index: -1000;
            font-weight: bold;
            text-transform: uppercase;
            white-space: nowrap;
            pointer-events: none;
        }

        /* Layout header table */
        .header-table {
            width: 100%;
            border-collapse: collapse;
            border: 0;
            margin-bottom: 25px;
        }
        .header-table td {
            border: 0;
            padding: 0;
            vertical-align: top;
        }
        
        /* Logo design */
        .logo-container {
            margin-bottom: 12px;
        }
        
        /* Address styling */
        .address-box {
            font-size: 8.5pt;
            color: #334155;
        }
        .address-title {
            font-weight: bold;
            color: #64748b;
            font-size: 8.5pt;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-bottom: 3px;
            display: block;
        }
        .address-name {
            font-weight: bold;
            font-size: 11pt;
            color: #0f172a;
            margin-bottom: 2px;
            display: block;
        }

        /* Statement Title */
        .statement-title-container {
            border-bottom: 2px solid #3b82f6;
            padding-bottom: 5px;
            margin-bottom: 20px;
        }
        .statement-title {
            font-size: 13pt;
            font-weight: bold;
            color: #1e3a8a;
            margin: 0;
        }

        /* Data Table */
        table.data-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 15px;
            border: 1px solid #94a3b8;
        }
        table.data-table th {
            background-color: #b9d1ea;
            color: #0f172a;
            border: 1px solid #94a3b8;
            padding: 9px 6px;
            font-size: 8.5pt;
            font-weight: bold;
            text-align: center;
            text-transform: uppercase;
        }
        table.data-table td {
            padding: 8px 10px;
            border: 1px solid #cbd5e1;
            font-size: 9pt;
            vertical-align: middle;
        }
        
        /* Rows styling */
        .opening-row {
            background-color: #dbeafe;
            font-weight: bold;
        }
        .total-row {
            background-color: #f1f5f9;
            font-weight: bold;
        }
        .balance-row {
            background-color: #f8fafc;
            font-weight: bold;
        }
        
        /* Helpers */
        .text-right { text-align: right; }
        .text-center { text-align: center; }
        .font-bold { font-weight: bold; }
    </style>
</head>
<body>

    <!-- Subtle Diagonal Watermark -->
    <div class="watermark">{{ $plant->name ?? 'DEMO LOGIN' }}</div>

    <!-- Header section with logo and addresses in a borderless table layout -->
    <table class="header-table">
        <tr>
            <td style="width: 52%;">

                
                <!-- Client 'To' details -->
                <div class="address-box" style="margin-top: 15px;">
                    <span class="address-title">To:</span>
                    <span class="address-name">{{ $patron?->legal_name ?? $target_name }}</span>
                    @if($patron && $patron->addresses->isNotEmpty())
                        @php $pAddr = $patron->addresses->first(); @endphp
                        {{ $pAddr->line_1 ?? '' }}@if($pAddr->line_2), {{ $pAddr->line_2 }}@endif<br>
                        {{ $pAddr->city ?? '' }} - {{ $pAddr->zipcode ?? '' }}<br>
                    @else
                        , -<br>
                    @endif
                    <strong>GSTIN/UIN # :</strong> {{ $patron?->gstin ?? '' }}<br>
                    <strong>Contact #:</strong> {{ $patron ? ($patron->mobile_number ?? ($patron->contacts?->first()?->mobile ?? '0')) : '0' }}
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
                    <strong>GSTIN/UIN #:</strong> {{ $plant->gstin ?? '' }}<br>
                    <strong>MSME - UDYAM-</strong> {{ $plant->msme_no ?? '' }}
                </div>
            </td>
        </tr>
    </table>

    <!-- Ledger Statement Title Bar -->
    <div class="statement-title-container">
        <h2 class="statement-title">Ledger Statement (Period : {{ $start }} - {{ $end }})</h2>
    </div>

    <!-- Statement Table -->
    <table class="data-table">
        <thead>
            <tr>
                <th width="4%">#</th>
                <th width="12%">Date</th>
                <th width="12%">Type</th>
                <th width="42%">Ref</th>
                <th width="10%">Inv/Bill No</th>
                <th width="10%">Debit</th>
                <th width="10%">Credit</th>
            </tr>
        </thead>
        <tbody>
            <!-- Calculate opening balance column positioning -->
            @php
                $op_debit = $opening_balance > 0 ? $opening_balance : 0;
                $op_credit = $opening_balance < 0 ? abs($opening_balance) : 0;

                $total_debit = $op_debit;
                $total_credit = $op_credit;
            @endphp
            
            <!-- Opening Balance Row -->
            <tr class="opening-row">
                <td colspan="4" style="background-color: #b9d1ea; border: 1px solid #94a3b8; text-align: center; font-weight: bold; font-size: 9.5pt;">Opening Balance</td>
                <td style="background-color: #b9d1ea; border: 1px solid #94a3b8; text-align: center;">---</td>
                <td class="text-right" style="background-color: #b9d1ea; border: 1px solid #94a3b8;">
                    {{ $op_debit > 0 ? 'Rs.₹ ' . number_format($op_debit, 2) : 'Rs.0.00' }}
                </td>
                <td class="text-right" style="background-color: #b9d1ea; border: 1px solid #94a3b8;">
                    {{ $op_credit > 0 ? 'Rs.₹ ' . number_format($op_credit, 2) : 'Rs.0.00' }}
                </td>
            </tr>

            <!-- Transactions Rows -->
            @foreach($transactions as $index => $trx)
                @php
                    $total_debit += $trx['debit'];
                    $total_credit += $trx['credit'];
                @endphp
                <tr>
                    <td class="text-center">{{ $index + 1 }}</td>
                    <td class="text-center">{{ \Carbon\Carbon::parse($trx['date'])->format('d-m-Y') }}</td>
                    <td class="text-center">{{ ucfirst(strtolower($trx['voucher_type'])) }}</td>
                    <td>
                        @if($trx['voucher_type'] == 'PURCHASE')
                            <strong>Bill#: {{ $trx['voucher_no'] }}</strong><br>
                        @elseif($trx['voucher_type'] == 'SALES')
                            <strong>Inv#: {{ $trx['voucher_no'] }}</strong><br>
                        @else
                            <strong>Ref: {{ $trx['voucher_no'] }}</strong><br>
                        @endif
                        <span style="font-size: 8pt; color: #475569;">{{ $trx['narration'] }}</span>
                    </td>
                    <td class="text-center">{{ $trx['voucher_no'] }}</td>
                    <td class="text-right">
                        @if($trx['debit'] > 0)
                            ₹ {{ number_format($trx['debit'], 2) }}
                        @else
                            -
                        @endif
                    </td>
                    <td class="text-right">
                        @if($trx['credit'] > 0)
                            ₹ {{ number_format($trx['credit'], 2) }}
                        @else
                            -
                        @endif
                    </td>
                </tr>
            @endforeach

            <!-- Total Amount Row -->
            <tr class="total-row">
                <td colspan="5" class="text-center font-bold" style="font-size: 10pt;">Total Amount</td>
                <td class="text-right font-bold">
                    {{ $total_debit > 0 ? '₹ ' . number_format($total_debit, 2) : '0' }}
                </td>
                <td class="text-right font-bold">
                    {{ $total_credit > 0 ? '₹ ' . number_format($total_credit, 2) : '0' }}
                </td>
            </tr>

            <!-- Balance Amount Row -->
            @php
                $balance_amount = abs($total_debit - $total_credit);
                $balance_side = $total_debit >= $total_credit ? 'debit' : 'credit';
            @endphp
            <tr class="balance-row">
                <td colspan="5" class="text-center font-bold" style="font-size: 10pt;">Balance Amount</td>
                <td class="text-right font-bold">
                    @if($balance_side == 'debit')
                        ₹ {{ number_format($balance_amount, 2) }}
                    @else
                        -
                    @endif
                </td>
                <td class="text-right font-bold">
                    @if($balance_side == 'credit')
                        ₹ {{ number_format($balance_amount, 2) }}
                    @else
                        -
                    @endif
                </td>
            </tr>
        </tbody>
    </table>

</body>
</html>
