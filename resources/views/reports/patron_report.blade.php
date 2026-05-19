<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Patron Statement of Accounts</title>
    <style>
        @page {
            margin: 35px;
        }
        body {
            font-family: 'DejaVu Sans', sans-serif;
            font-size: 9pt;
            color: #1e293b;
            margin: 0;
            padding: 0;
            line-height: 1.4;
        }

        /* Layout header table */
        .header-table {
            width: 100%;
            border-collapse: collapse;
            border: 0;
            margin-bottom: 10px;
        }
        .header-table td {
            border: 0;
            padding: 0;
            vertical-align: top;
        }
        
        /* Address styling */
        .address-box {
            font-size: 8pt;
            color: #334155;
            line-height: 1.35;
        }
        .address-title {
            font-weight: bold;
            color: #64748b;
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

        /* Statement Title Section */
        .statement-title-container {
            text-align: right;
            margin-top: 15px;
            margin-bottom: 20px;
        }
        .statement-title {
            font-size: 11.5pt;
            font-weight: bold;
            color: #0f172a;
            margin: 0;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        .statement-period {
            font-size: 9.5pt;
            color: #475569;
            font-weight: bold;
            margin: 3px 0;
            display: block;
        }
        .horizontal-divider {
            border: 0;
            border-top: 1.5px solid #cbd5e1;
            margin: 3px 0;
        }

        /* Account Summary Box */
        .summary-table {
            width: 320px;
            border-collapse: collapse;
            border: 1px solid #cbd5e1;
            margin-left: auto;
            margin-bottom: 25px;
            font-size: 8.5pt;
        }
        .summary-table th {
            background-color: #e2e8f0;
            color: #0f172a;
            padding: 5px 8px;
            text-align: left;
            font-weight: bold;
            font-size: 9pt;
            border-bottom: 1px solid #cbd5e1;
        }
        .summary-table td {
            padding: 4.5px 8px;
            border-bottom: 1px solid #f1f5f9;
        }
        .summary-table tr.balance-due-row {
            background-color: #f1f5f9;
            font-weight: bold;
            font-size: 9pt;
            border-top: 1.5px solid #cbd5e1;
        }

        /* Data Table */
        table.data-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 15px;
            border: 1px solid #475569;
        }
        table.data-table th {
            background-color: #334155;
            color: #ffffff;
            border: 1px solid #475569;
            padding: 8px 5px;
            font-size: 8pt;
            font-weight: bold;
            text-align: center;
        }
        table.data-table td {
            padding: 7px 8px;
            border: 1px solid #cbd5e1;
            font-size: 8.5pt;
            vertical-align: middle;
        }
        
        /* Rows styling */
        .table-balance-due-row {
            background-color: #e2e8f0;
            font-weight: bold;
            font-size: 9pt;
        }
        
        /* Helpers */
        .text-right { text-align: right; }
        .text-center { text-align: center; }
        .font-bold { font-weight: bold; }
    </style>
</head>
<body>

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

    <!-- Statement Title bar on the Right -->
    <div class="statement-title-container">
        <hr class="horizontal-divider">
        <h3 class="statement-title">Patron Statement of Accounts</h3>
        <hr class="horizontal-divider">
        <span class="statement-period">{{ $start }} to {{ $end }}</span>
        <hr class="horizontal-divider">
    </div>

    @php
        // Net balance due calculation:
        $net_balance_due = $opening_balance;
        foreach($transactions as $trx) {
            $net_balance_due += ($trx['debit'] - $trx['credit']);
        }
    @endphp

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
                <td>Invoiced(Tax)</td>
                <td class="text-right">
                    {{ $invoiced_tax > 0 ? '₹ ' . number_format($invoiced_tax, 2) : '0' }}
                </td>
            </tr>
            <tr>
                <td>Invoiced(Non-Tax)</td>
                <td class="text-right">
                    {{ $invoiced_nontax > 0 ? '₹ ' . number_format($invoiced_nontax, 2) : '0' }}
                </td>
            </tr>
            <tr style="font-weight: bold;">
                <td>Total Invoiced Amount</td>
                <td class="text-right">
                    {{ ($invoiced_tax + $invoiced_nontax) > 0 ? '₹ ' . number_format($invoiced_tax + $invoiced_nontax, 2) : '0' }}
                </td>
            </tr>
            <tr>
                <td>Sales Discount</td>
                <td class="text-right">
                    {{ $sales_discount > 0 ? '₹ ' . number_format($sales_discount, 2) : '0' }}
                </td>
            </tr>
            <tr>
                <td>Purchased</td>
                <td class="text-right">
                    {{ $purchased > 0 ? '₹ ' . number_format($purchased, 2) : '0' }}
                </td>
            </tr>
            <tr>
                <td>Amount Received</td>
                <td class="text-right">
                    {{ $amount_received > 0 ? '₹ ' . number_format($amount_received, 2) : '0' }}
                </td>
            </tr>
            <tr>
                <td>Amount Paid</td>
                <td class="text-right">
                    {{ $amount_paid > 0 ? '₹ ' . number_format($amount_paid, 2) : '0' }}
                </td>
            </tr>
            <tr>
                <td>Credits</td>
                <td class="text-right">
                    {{ $credits > 0 ? '₹ ' . number_format($credits, 2) : '0' }}
                </td>
            </tr>
            <tr class="balance-due-row">
                <td>Balance Due</td>
                <td class="text-right">
                    {{ $net_balance_due < 0 ? 'Cr' : 'Dr' }} ₹ {{ number_format(abs($net_balance_due), 2) }}
                </td>
            </tr>
        </tbody>
    </table>

    <!-- Statement Details Table -->
    <table class="data-table">
        <thead>
            <tr>
                <th width="4%">S/No</th>
                <th width="10%">Date</th>
                <th width="14%">Transactions</th>
                <th width="28%">Details</th>
                <th width="10%">Type</th>
                <th width="12%">Invoice/(Bill)</th>
                <th width="12%">(Receipt)/Payment</th>
                <th width="10%">Discount</th>
                <th width="12%">Balance</th>
            </tr>
        </thead>
        <tbody>
            @php 
                $running_balance = $opening_balance;
            @endphp
            
            <!-- Opening Balance Row (Row 1) -->
            <tr>
                <td class="text-center">1</td>
                <td class="text-center">{{ $start }}</td>
                <td class="font-bold">***Opening Balance***</td>
                <td class="text-center">/</td>
                <td class="text-center">-</td>
                <td class="text-right">
                    @if($opening_balance < 0)
                        (₹ {{ number_format(abs($opening_balance), 2) }})
                    @elseif($opening_balance > 0)
                        ₹ {{ number_format($opening_balance, 2) }}
                    @else
                        0
                    @endif
                </td>
                <td class="text-center">0</td>
                <td class="text-center">0</td>
                <td class="text-right font-bold">
                    {{ $opening_balance < 0 ? 'Cr' : 'Dr' }} ₹ {{ number_format(abs($opening_balance), 2) }}
                </td>
            </tr>

            <!-- Transactions Rows -->
            @foreach($transactions as $index => $trx)
                @php
                    $running_balance += ($trx['debit'] - $trx['credit']);
                    
                    // Determine readable transaction name
                    $trx_name = '';
                    $trx_type = '';
                    if ($trx['voucher_type'] == 'PURCHASE') {
                        $trx_name = 'Bill';
                        $trx_type = 'Bills';
                    } elseif ($trx['voucher_type'] == 'SALES') {
                        $trx_name = 'Invoice';
                        $trx_type = 'Invoices';
                    } elseif ($trx['voucher_type'] == 'PAYMENT') {
                        $trx_name = 'Payment';
                        $trx_type = 'Payments';
                    } elseif ($trx['voucher_type'] == 'RECEIPT') {
                        $trx_name = 'Receipt';
                        $trx_type = 'Receipts';
                    } else {
                        $trx_name = ucfirst(strtolower($trx['voucher_type']));
                        $trx_type = $trx_name . 's';
                    }

                    // Format details string
                    $due_suffix = $trx['due_date'] ? "due on " . \Carbon\Carbon::parse($trx['due_date'])->format('Y-m-d') : "";
                    $details_str = "# " . $trx['voucher_no'] . ($due_suffix ? " - " . $due_suffix : "") . " /";
                @endphp
                <tr>
                    <td class="text-center">{{ $index + 2 }}</td>
                    <td class="text-center">{{ \Carbon\Carbon::parse($trx['date'])->format('d-m-Y') }}</td>
                    <td class="font-bold">{{ $trx_name }}</td>
                    <td>{{ $details_str }}</td>
                    <td class="text-center">{{ $trx_type }}</td>
                    
                    <!-- Invoice / Bill Column -->
                    <td class="text-right">
                        @if($trx['voucher_type'] == 'PURCHASE')
                            (₹ {{ number_format($trx['credit'], 2) }})
                        @elseif($trx['voucher_type'] == 'SALES')
                            ₹ {{ number_format($trx['debit'], 2) }}
                        @else
                            0
                        @endif
                    </td>
                    
                    <!-- Receipt / Payment Column -->
                    <td class="text-right">
                        @if($trx['voucher_type'] == 'RECEIPT')
                            (₹ {{ number_format($trx['credit'], 2) }})
                        @elseif($trx['voucher_type'] == 'PAYMENT')
                            ₹ {{ number_format($trx['debit'], 2) }}
                        @else
                            0
                        @endif
                    </td>
                    
                    <!-- Discount Column -->
                    <td class="text-center">0</td>
                    
                    <!-- Running Balance Column -->
                    <td class="text-right font-bold">
                        {{ $running_balance < 0 ? 'Cr' : 'Dr' }} ₹ {{ number_format(abs($running_balance), 2) }}
                    </td>
                </tr>
            @endforeach

            <!-- Final Table Balance Due Row -->
            <tr class="table-balance-due-row">
                <td colspan="8" class="text-center font-bold">Balance Due</td>
                <td class="text-right font-bold" style="color: #1e3a8a;">
                    {{ $net_balance_due < 0 ? 'Cr' : 'Dr' }} ₹ {{ number_format(abs($net_balance_due), 2) }}
                </td>
            </tr>
        </tbody>
    </table>

</body>
</html>
