<head>
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
    <title>{{ !empty($is_single_patron) ? 'Patron Statement of Accounts' : 'Customer Outstanding & Aging Report' }}</title>
    <style>
        {!! $css ?? $report_css ?? (file_exists(public_path('css/reports/report_pdf.css')) ? file_get_contents(public_path('css/reports/report_pdf.css')) : '') !!}

        @page {
            margin: 12mm 10mm 15mm 10mm;
            size: A4 portrait !important;
        }

        body, table, th, td, div, span, p, h1, h2, h3, strong, b {
            font-family: 'DejaVu Sans', sans-serif !important;
        }

        body {
            font-size: 8pt;
            color: #1e293b;
            line-height: 1.25;
            margin: 0;
            padding: 0;
        }

        .statement-header-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 8px;
        }

        .statement-header-table td {
            vertical-align: top;
            padding: 0;
        }

        .logo-img {
            max-height: 48px;
            max-width: 180px;
        }

        .tagline {
            font-size: 7.5pt;
            color: #64748b;
            font-style: italic;
            margin-top: 3px;
        }

        .address-block {
            font-size: 8pt;
            color: #334155;
            line-height: 1.35;
        }

        .address-block strong {
            color: #0f172a;
        }

        .to-box {
            font-size: 8pt;
            line-height: 1.35;
            color: #1e293b;
        }

        .to-title {
            font-size: 8pt;
            color: #64748b;
            margin-bottom: 2px;
        }

        .to-name {
            font-size: 9.5pt;
            font-weight: bold;
            color: #0f172a;
            margin-bottom: 2px;
        }

        /* Statement Title Bar */
        .title-right-container {
            text-align: right;
            margin-bottom: 6px;
        }

        .statement-main-title {
            font-size: 11pt;
            font-weight: bold;
            color: #0f172a;
            letter-spacing: 0.2px;
            margin: 0;
        }

        .statement-period-text {
            font-size: 8pt;
            color: #475569;
            margin-top: 2px;
        }

        /* Account Summary Box */
        .summary-box-table {
            width: 320px;
            float: right;
            border-collapse: collapse;
            border: 1px solid #cbd5e1;
            margin-top: 4px;
            margin-bottom: 8px;
            background: #ffffff;
        }

        .summary-box-table th {
            background: #f1f5f9;
            color: #0f172a;
            font-size: 8.5pt;
            font-weight: bold;
            text-align: left;
            padding: 4px 8px;
            border-bottom: 1px solid #cbd5e1;
        }

        .summary-box-table td {
            padding: 2.5px 8px;
            font-size: 7.5pt;
            color: #334155;
            border-bottom: 1px solid #f1f5f9;
        }

        .summary-box-table tr.total-row td {
            font-weight: bold;
            border-top: 1px solid #cbd5e1;
            border-bottom: 1px solid #cbd5e1;
            background: #f8fafc;
        }

        .summary-box-table tr.balance-row td {
            font-weight: bold;
            font-size: 8.5pt;
            background: #f8fafc;
            border-top: 1px solid #cbd5e1;
            color: #0f172a;
            padding: 4px 8px;
        }

        .balance-due-strip {
            width: 100%;
            border-collapse: collapse;
            margin-top: 6px;
            margin-bottom: 10px;
            clear: both;
        }

        .balance-due-strip td {
            padding: 5px 8px;
            font-size: 9pt;
            font-weight: bold;
            background: #f8fafc;
            border-top: 1px solid #e2e8f0;
            border-bottom: 1px solid #e2e8f0;
        }

        /* Main Ledger Table */
        .ledger-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 6px;
            font-size: 7.5pt;
            clear: both;
        }

        .ledger-table th {
            background: #1e293b;
            color: #ffffff;
            font-size: 7.5pt;
            font-weight: bold;
            padding: 6px 4px;
            border: 1px solid #1e293b;
            text-align: left;
            line-height: 1.15;
        }

        .ledger-table td {
            padding: 5px 4px;
            border-bottom: 1px solid #e2e8f0;
            vertical-align: top;
            line-height: 1.25;
            color: #1e293b;
        }

        .ledger-table tr:nth-child(even) td {
            background-color: #f8fafc;
        }

        .ledger-table tr.opening-row td {
            background-color: #ffffff;
            font-weight: bold;
        }

        .text-center { text-align: center; }
        .text-right { text-align: right; }
        .font-bold { font-weight: bold; }
        .amount-cell { white-space: nowrap !important; text-align: right; }
        .nowrap { white-space: nowrap !important; }

        .details-cell {
            color: #334155;
            font-size: 7pt;
            word-wrap: break-word;
        }

        .footer-banner {
            width: 100%;
            border-collapse: collapse;
            margin-top: 6px;
            background: #e2e8f0;
            font-weight: bold;
            font-size: 8.5pt;
        }

        .footer-banner td {
            padding: 5px 8px;
            border: 1px solid #cbd5e1;
        }

        .page-footer-note {
            margin-top: 25px;
            font-size: 7.5pt;
            color: #64748b;
            text-align: left;
        }
    </style>
</head>
<body>

@php
    $plantLogoBase64 = null;
    $rawLogoPath = $plant->logo_path ?? null;
    if ($rawLogoPath) {
        $cleanLogo = ltrim(str_replace(['public/', 'storage/', '/storage/'], '', $rawLogoPath), '/');
        $possiblePaths = [
            storage_path('app/public/' . $cleanLogo),
            public_path('storage/' . $cleanLogo),
        ];
        foreach ($possiblePaths as $pPath) {
            if (file_exists($pPath)) {
                $mime = mime_content_type($pPath) ?: 'image/png';
                $plantLogoBase64 = 'data:' . $mime . ';base64,' . base64_encode(file_get_contents($pPath));
                break;
            }
        }
    }
@endphp

@if(!empty($is_single_patron))
    <!-- ======================================================================= -->
    <!-- PATRON STATEMENT OF ACCOUNTS (V2 - Matching Uploaded Reference PDF)      -->
    <!-- ======================================================================= -->

    <!-- Top Header: Plant Logo on Left, Entity Address on Right -->
    <table class="statement-header-table">
        <tr>
            <td style="width: 48%;">
                @if($plantLogoBase64)
                    <img src="{{ $plantLogoBase64 }}" class="logo-img" alt="Plant Logo">
                @else
                    <div style="font-size: 14pt; font-weight: bold; color: #0f172a;">
                        {{ $plant->legal_name ?? ($plant->name ?? 'DEMO LOGIN') }}
                    </div>
                @endif
            </td>
            <td style="width: 52%; text-align: right;">
                <div class="address-block">
                    <strong>Address:</strong><br>
                    <strong>{{ $plant->name ?? 'DEMO LOGIN' }}</strong><br>
                    @if(!empty($plant->addresses) && $plant->addresses->isNotEmpty())
                        @php $plAddr = $plant->addresses->first(); @endphp
                        {{ $plAddr->line_1 ?? '' }}@if(!empty($plAddr->line_2)), {{ $plAddr->line_2 }}@endif<br>
                        {{ $plAddr->city ?? '' }} - {{ $plAddr->zipcode ?? '' }}@if(!empty($plAddr->state)), {{ $plAddr->state->name ?? $plAddr->state->state_name }}@endif<br>
                    @else
                        3/150, Akkiyampatti (Po),<br>
                        Sendamangalam (Tk), Namakkal (Dt), Tamil Nadu - 637409<br>
                    @endif
                    GSTIN/UIN #: {{ $plant->gstin ?? '' }}<br>
                    MSME - UDYAM-
                </div>
            </td>
        </tr>
    </table>

    <!-- Middle Header: To Address on Left, Title & Account Summary on Right -->
    <table class="statement-header-table" style="margin-top: 4px;">
        <tr>
            <td style="width: 48%; vertical-align: top;">
                <div class="to-box">
                    <div class="to-title">To:</div>
                    <div class="to-name">{{ $patron->legal_name ?? 'CUSTOMER' }}</div>
                    @if(!empty($patron->addresses) && $patron->addresses->isNotEmpty())
                        @php $pAddr = $patron->addresses->first(); @endphp
                        {{ $pAddr->line_1 ?? '' }}@if(!empty($pAddr->line_2)), {{ $pAddr->line_2 }}@endif<br>
                        {{ $pAddr->city ?? '' }} - {{ $pAddr->zipcode ?? '' }}<br>
                    @endif
                    <strong>GSTIN/UIN # :</strong> {{ $patron->gstin ?? '-' }}<br>
                    <strong>Contact #:</strong> {{ $phone ?? '-' }}
                </div>
            </td>
            <td style="width: 52%; text-align: right; vertical-align: top;">
                <div class="title-right-container">
                    <h2 class="statement-main-title">Patron Statement of Accounts</h2>
                    <div class="statement-period-text">
                        {{ $start_formatted ?? \Carbon\Carbon::parse($start)->format('d-m-Y') }} to {{ $end_formatted ?? \Carbon\Carbon::parse($end)->format('d-m-Y') }}
                    </div>
                </div>

                @if(!empty($account_summary))
                <table class="summary-box-table">
                    <thead>
                        <tr>
                            <th colspan="2">Account Summary</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td>Opening Balance</td>
                            <td class="text-right font-bold amount-cell">{!! str_replace('₹', '&#8377;', e($account_summary['opening_balance_display'] ?? '0')) !!}</td>
                        </tr>
                        <tr>
                            <td>Invoiced(Tax)</td>
                            <td class="text-right amount-cell">{!! str_replace('₹', '&#8377;', e($account_summary['invoiced_tax_display'] ?? '0')) !!}</td>
                        </tr>
                        <tr>
                            <td>Invoiced(Non-Tax)</td>
                            <td class="text-right amount-cell">{!! str_replace('₹', '&#8377;', e($account_summary['invoiced_nontax_display'] ?? '0')) !!}</td>
                        </tr>
                        <tr class="total-row">
                            <td>Total Invoiced Amount</td>
                            <td class="text-right amount-cell">{!! str_replace('₹', '&#8377;', e($account_summary['total_invoiced_display'] ?? '0')) !!}</td>
                        </tr>
                        <tr>
                            <td>Sales Discount</td>
                            <td class="text-right amount-cell">{!! str_replace('₹', '&#8377;', e($account_summary['sales_discount_display'] ?? '0')) !!}</td>
                        </tr>
                        <tr>
                            <td>Purchased</td>
                            <td class="text-right amount-cell">{!! str_replace('₹', '&#8377;', e($account_summary['purchased_display'] ?? '0')) !!}</td>
                        </tr>
                        <tr>
                            <td>Amount Received</td>
                            <td class="text-right amount-cell">{!! str_replace('₹', '&#8377;', e($account_summary['amount_received_display'] ?? '0')) !!}</td>
                        </tr>
                        <tr>
                            <td>Amount Paid</td>
                            <td class="text-right amount-cell">{!! str_replace('₹', '&#8377;', e($account_summary['amount_paid_display'] ?? '0')) !!}</td>
                        </tr>
                        <tr>
                            <td>Credits</td>
                            <td class="text-right amount-cell">{!! str_replace('₹', '&#8377;', e($account_summary['credits_display'] ?? '0')) !!}</td>
                        </tr>
                    </tbody>
                </table>
                @endif
            </td>
        </tr>
    </table>

    <!-- Balance Due Banner -->
    <table class="balance-due-strip">
        <tr>
            <td style="width: 50%;">Balance Due</td>
            <td style="width: 50%; text-align: right;" class="amount-cell">{!! str_replace('₹', '&#8377;', e($balance_due_display ?? ($account_summary['balance_due_display'] ?? '&#8377; 0.00'))) !!}</td>
        </tr>
    </table>

    <!-- Ledger Transactions Table -->
    <table class="ledger-table">
        <thead>
            <tr>
                <th style="width: 4%;" class="text-center">S/No</th>
                <th style="width: 8%;" class="nowrap">Date</th>
                <th style="width: 10%;">Transactions</th>
                <th style="width: 20%;">Details</th>
                <th style="width: 14%;" class="text-center nowrap">Type</th>
                <th style="width: 11%;" class="text-right nowrap">Invoice/(Bill)</th>
                <th style="width: 11%;" class="text-right nowrap">(Receipt)/<br>Payment</th>
                <th style="width: 4%;" class="text-right nowrap">Discount</th>
                <th style="width: 18%;" class="text-right nowrap">Balance</th>
            </tr>
        </thead>
        <tbody>
            @forelse($transactions ?? [] as $trx)
                <tr class="{{ !empty($trx['is_opening']) ? 'opening-row' : '' }}">
                    <td class="text-center">{{ $trx['s_no'] }}</td>
                    <td class="nowrap">{{ $trx['date'] }}</td>
                    <td class="font-bold">{{ $trx['transactions'] }}</td>
                    <td class="details-cell">{!! nl2br(e($trx['details'])) !!}</td>
                    <td class="text-center nowrap font-bold" style="font-size: 7.5pt;">{{ $trx['type'] ?? '-' }}</td>
                    <td class="amount-cell">{!! str_replace('₹', '&#8377;', e($trx['invoice_bill_display'] ?? '0')) !!}</td>
                    <td class="amount-cell">{!! str_replace('₹', '&#8377;', e($trx['receipt_payment_display'] ?? '0')) !!}</td>
                    <td class="amount-cell">{!! str_replace('₹', '&#8377;', e($trx['discount_display'] ?? '0')) !!}</td>
                    <td class="amount-cell font-bold">{!! str_replace('₹', '&#8377;', e($trx['balance_display'] ?? '-')) !!}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="9" class="text-center" style="padding: 15px; color: #94a3b8;">No transactions found for this patron in the selected period.</td>
                </tr>
            @endforelse
        </tbody>
    </table>

    <!-- Bottom Balance Due Banner -->
    <table class="footer-banner">
        <tr>
            <td style="width: 60%; text-align: center; text-transform: uppercase;">Balance Due</td>
            <td style="width: 40%; text-align: right;" class="amount-cell">{!! str_replace('₹', '&#8377;', e($balance_due_display ?? ($account_summary['balance_due_display'] ?? '&#8377; 0.00'))) !!}</td>
        </tr>
    </table>

    <div class="page-footer-note">
        {{ $plant->name ?? 'DEMO LOGIN' }} - Copyright {{ date('Y') }}
    </div>

@else
    <!-- ======================================================================= -->
    <!-- ALL CUSTOMERS AGING OVERVIEW                                            -->
    <!-- ======================================================================= -->

    <table class="header-table">
        <tr>
            <td style="width: 55%; vertical-align: top;">
                <div class="address-box">
                    <span class="address-title">Entity / Plant:</span>
                    <span class="address-name" style="color: #0369a1;">{{ $plant->name ?? 'Ready Mix Concrete Operations' }}</span>
                    @if(!empty($plant->addresses) && $plant->addresses->isNotEmpty())
                        @php $addr = $plant->addresses->first(); @endphp
                        {{ $addr->line_1 ?? '' }}@if(!empty($addr->line_2)), {{ $addr->line_2 }}@endif<br>
                        {{ $addr->city ?? '' }} - {{ $addr->zipcode ?? '' }}@if(!empty($addr->state)), {{ $addr->state->name ?? $addr->state->state_name }}@endif<br>
                    @endif
                    @if(!empty($plant->gstin))
                        <strong>GSTIN:</strong> {{ $plant->gstin }}<br>
                    @endif
                    @if(!empty($plant->phone))
                        <strong>Phone:</strong> {{ $plant->phone }}
                    @endif
                </div>
            </td>
            
            <td style="width: 45%; text-align: right; vertical-align: top;">
                <div class="address-box">
                    <span class="address-title">Customer Filter:</span>
                    <span class="address-name">All Active Customers</span>
                    <span>Party-wise Outstanding Balances & Aging</span><br>
                    <span style="font-size: 7.5pt; color: #64748b; margin-top: 4px; display: inline-block;">
                        Generated: {{ $generated_at ?? now()->format('d/m/Y h:i A') }}
                    </span>
                </div>
            </td>
        </tr>
    </table>

    <div class="title-banner">
        Customer Outstanding & Aging Report
        <div class="period-text">
            @if(!empty($filters['start']) && !empty($filters['end']))
                Period: {{ \Carbon\Carbon::parse($filters['start'])->format('d/m/Y') }} to {{ \Carbon\Carbon::parse($filters['end'])->format('d/m/Y') }}
            @elseif(!empty($filters['end']))
                As on: {{ \Carbon\Carbon::parse($filters['end'])->format('d/m/Y') }}
            @else
                As on: {{ now()->format('d/m/Y') }}
            @endif
        </div>
    </div>

    <!-- KPI Summary Row -->
    <table class="kpi-table">
        <tr>
            <td style="width: 20%;">
                <div class="kpi-card" style="background: #fef2f2; border-color: #fca5a5;">
                    <div class="kpi-label" style="color: #991b1b;">Total Outstanding</div>
                    <div class="kpi-val kpi-alert">₹ {{ number_format($total_outstanding_amount ?? 0, 2) }}</div>
                </div>
            </td>
            <td style="width: 16%;">
                <div class="kpi-card">
                    <div class="kpi-label">0-30 Days</div>
                    <div class="kpi-val">₹ {{ number_format($aging_0_30 ?? 0, 2) }}</div>
                </div>
            </td>
            <td style="width: 16%;">
                <div class="kpi-card">
                    <div class="kpi-label">31-60 Days</div>
                    <div class="kpi-val">₹ {{ number_format($aging_31_60 ?? 0, 2) }}</div>
                </div>
            </td>
            <td style="width: 16%;">
                <div class="kpi-card">
                    <div class="kpi-label">61-90 Days</div>
                    <div class="kpi-val">₹ {{ number_format($aging_61_90 ?? 0, 2) }}</div>
                </div>
            </td>
            <td style="width: 16%;">
                <div class="kpi-card" style="background: #fff1f2; border-color: #fecdd3;">
                    <div class="kpi-label" style="color: #be123c;">90+ Days</div>
                    <div class="kpi-val" style="color: #be123c;">₹ {{ number_format($aging_90_plus ?? 0, 2) }}</div>
                </div>
            </td>
            <td style="width: 16%;">
                <div class="kpi-card">
                    <div class="kpi-label">Open Invoices</div>
                    <div class="kpi-val">{{ $total_open_invoices ?? 0 }}</div>
                </div>
            </td>
        </tr>
    </table>

    <div class="section-header">
        <h3 class="section-title">Customer-wise Outstanding Summary ({{ count($transactions ?? []) }} Customers)</h3>
    </div>

    <table class="data-table">
        <thead>
            <tr>
                <th width="3%" class="text-center">#</th>
                <th width="7%">Code</th>
                <th width="19%">Customer</th>
                <th width="9%">GSTIN</th>
                <th width="10%" class="text-right">Total Invoiced</th>
                <th width="10%" class="text-right">Total Receipts</th>
                <th width="9%" class="text-right">Total Payments</th>
                <th width="11%" class="text-right">Balance Due (₹)</th>
                <th width="6%" class="text-right">0-30d (₹)</th>
                <th width="6%" class="text-right">30-60d (₹)</th>
                <th width="5%" class="text-right">60-90d (₹)</th>
                <th width="5%" class="text-right">90+d (₹)</th>
            </tr>
        </thead>
        <tbody>
            @forelse($transactions ?? [] as $idx => $row)
                <tr>
                    <td class="text-center">{{ $idx + 1 }}</td>
                    <td style="font-weight: bold; color: #475569;">{{ $row['customer_code'] ?? '-' }}</td>
                    <td style="font-weight: bold; color: #0f172a;">{{ $row['customer_name'] ?? '-' }}</td>
                    <td style="color: #64748b;">{{ $row['gstin'] ?? '-' }}</td>
                    <td class="text-right amount-cell nowrap">{{ number_format($row['total_invoiced'] ?? 0, 2) }}</td>
                    <td class="text-right amount-cell nowrap" style="color: #166534;">{{ number_format($row['total_receipt'] ?? ($row['total_paid'] ?? 0), 2) }}</td>
                    <td class="text-right amount-cell nowrap" style="color: #0284c7;">{{ number_format($row['total_payment'] ?? 0, 2) }}</td>
                    <td class="text-right amount-cell nowrap" style="font-weight: bold; color: {{ ($row['total_outstanding'] ?? 0) > 0 ? '#b91c1c' : '#334155' }};">
                        {{ number_format($row['total_outstanding'] ?? 0, 2) }}
                    </td>
                    <td class="text-right amount-cell nowrap" style="color: #1e40af;">
                        {{ ($row['aging_0_30'] ?? 0) > 0 ? number_format($row['aging_0_30'], 2) : '-' }}
                    </td>
                    <td class="text-right amount-cell nowrap" style="color: #b45309;">
                        {{ ($row['aging_31_60'] ?? 0) > 0 ? number_format($row['aging_31_60'], 2) : '-' }}
                    </td>
                    <td class="text-right amount-cell nowrap" style="color: #c2410c;">
                        {{ ($row['aging_61_90'] ?? 0) > 0 ? number_format($row['aging_61_90'], 2) : '-' }}
                    </td>
                    <td class="text-right amount-cell nowrap" style="color: {{ ($row['aging_90_plus'] ?? 0) > 0 ? '#be123c' : '#94a3b8' }}; font-weight: bold;">
                        {{ ($row['aging_90_plus'] ?? 0) > 0 ? number_format($row['aging_90_plus'], 2) : '-' }}
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="12" class="text-center" style="padding: 15px; color: #94a3b8;">No customer outstanding balances found.</td>
                </tr>
            @endforelse

            <tr class="total-row">
                <td colspan="4" class="text-center uppercase">Total Outstanding Receivables</td>
                <td class="text-right amount-cell nowrap">{{ number_format($total_invoiced_amount ?? 0, 2) }}</td>
                <td class="text-right amount-cell nowrap">{{ number_format($total_receipt_amount ?? ($total_paid_amount ?? 0), 2) }}</td>
                <td class="text-right amount-cell nowrap">{{ number_format($total_payment_amount ?? 0, 2) }}</td>
                <td class="text-right amount-cell nowrap" style="color: #b91c1c;">&#8377; {{ number_format($total_outstanding_amount ?? 0, 2) }}</td>
                <td class="text-right amount-cell nowrap" style="color: #1e40af;">{{ number_format($aging_0_30 ?? 0, 2) }}</td>
                <td class="text-right amount-cell nowrap" style="color: #b45309;">{{ number_format($aging_31_60 ?? 0, 2) }}</td>
                <td class="text-right amount-cell nowrap" style="color: #c2410c;">{{ number_format($aging_61_90 ?? 0, 2) }}</td>
                <td class="text-right amount-cell nowrap" style="color: #be123c;">&#8377; {{ number_format($aging_90_plus ?? 0, 2) }}</td>
            </tr>
        </tbody>
    </table>
@endif

</body>
</html>

