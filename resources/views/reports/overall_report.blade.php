<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Daily Overall Business & Operational MIS Report</title>
    <style>
        {!! $css ?? $report_css ?? (file_exists(public_path('css/reports/report_pdf.css')) ? file_get_contents(public_path('css/reports/report_pdf.css')) : '') !!}
        .summary-table { width: 100%; border-collapse: collapse; table-layout: fixed; margin: 14px 0 18px; }
        .summary-table th, .summary-table td { border: 1px solid #cbd5e1; padding: 8px 7px; vertical-align: middle; text-align: left; font-size: 8pt; width: 25%; }
        .summary-table th { background: #1d2d3e; color: #fff; font-size: 7pt; }
        .summary-table tr:nth-child(even) td { background: #f1f5f9; }
        .summary-table .summary-amount { text-align: right; white-space: nowrap; font-weight: bold; }
        .summary-table tr { page-break-inside: avoid; }
    </style>
</head>
<body>

    <table class="header-table">
        <tr>
            <td style="width: 60%; vertical-align: top;">
                <div class="address-box">
                    <span class="address-title">Plant / Operational Unit:</span>
                    <span class="address-name">{{ $plant->name ?? 'Ready Mix Concrete Plant' }}</span>
                    @if(!empty($plant->addresses) && $plant->addresses->isNotEmpty())
                        @php $addr = $plant->addresses->first(); @endphp
                        {{ $addr->line_1 ?? '' }}@if(!empty($addr->line_2)), {{ $addr->line_2 }}@endif<br>
                        {{ $addr->city ?? '' }} - {{ $addr->zipcode ?? '' }}@if(!empty($addr->state)), {{ $addr->state->name }}@endif<br>
                    @endif
                    @if(!empty($plant->gstin))
                        <strong>GSTIN:</strong> {{ $plant->gstin }}<br>
                    @endif
                    @if(!empty($plant->phone))
                        <strong>Phone:</strong> {{ $plant->phone }}
                    @endif
                </div>
            </td>
            
            <td style="width: 40%; text-align: right; vertical-align: top;">
                <div class="address-box">
                    <span class="address-name" style="color: #0f172a;">EXECUTIVE DAY BOOK</span>
                    <span style="font-size: 7pt; color: #475569;">Daily Integrated MIS Report</span><br>
                    <span style="font-size: 7pt; color: #64748b;">
                        Generated: {{ $generated_at ?? now()->format('d/m/Y h:i A') }}
                    </span>
                </div>
            </td>
        </tr>
    </table>

    <div class="title-banner">
        DAILY OVERALL BUSINESS & OPERATIONAL MIS REPORT
        <div class="period-text">
            @if(!empty($filters['start_date']) && !empty($filters['end_date']))
                Reporting Period: {{ \Carbon\Carbon::parse($filters['start_date'])->format('d/m/Y') }} to {{ \Carbon\Carbon::parse($filters['end_date'])->format('d/m/Y') }}
            @else
                Reporting Date: {{ now()->format('d/m/Y') }}
            @endif
        </div>
    </div>

    <!-- Fixed columns keep labels and amounts aligned in the PDF renderer. -->
    <table class="summary-table">
        <thead><tr><th>Summary Metric</th><th class="summary-amount">Amount / Quantity</th><th>Summary Metric</th><th class="summary-amount">Amount / Quantity</th></tr></thead>
        <tbody>
            <tr><td>Daily Billed Sales</td><td class="summary-amount">₹ {{ number_format($executive_summary['sales_revenue'] ?? 0, 2) }}</td><td>Total Collections</td><td class="summary-amount">₹ {{ number_format($executive_summary['total_receipts'] ?? 0, 2) }}</td></tr>
            <tr><td>Payments Made</td><td class="summary-amount">₹ {{ number_format($executive_summary['total_payments'] ?? 0, 2) }}</td><td>Net Cash Flow</td><td class="summary-amount">₹ {{ number_format($executive_summary['net_cash_flow'] ?? 0, 2) }}</td></tr>
            <tr><td>Dispatched Volume</td><td class="summary-amount">{{ number_format($executive_summary['total_dispatched_volume'] ?? 0, 2) }} m³</td><td>Batches Mixed</td><td class="summary-amount">{{ number_format($executive_summary['total_batches_count'] ?? 0) }} Batches</td></tr>
            <tr><td>Cash Collections</td><td class="summary-amount">₹ {{ number_format($executive_summary['cash_receipts'] ?? 0, 2) }}</td><td>Bank / Online Collections</td><td class="summary-amount">₹ {{ number_format($executive_summary['bank_receipts'] ?? 0, 2) }}</td></tr>
            <tr><td>Pending Credit Balance</td><td class="summary-amount">₹ {{ number_format($executive_summary['credit_sales_amount'] ?? 0, 2) }}</td><td>Pump Charges Billed</td><td class="summary-amount">₹ {{ number_format($executive_summary['pump_charges_earned'] ?? 0, 2) }}</td></tr>
            <tr><td>Hire / Shipping Charges</td><td class="summary-amount">₹ {{ number_format($executive_summary['hire_charges_billed'] ?? 0, 2) }}</td><td>Net GST Liability</td><td class="summary-amount">₹ {{ number_format($executive_summary['net_gst_payable'] ?? 0, 2) }}</td></tr>
        </tbody>
    </table>

    <!-- Section 1: Dispatches Log -->
    <div class="section-header">
        <h3 class="section-title">1. Dispatches & Logistics Summary ({{ count($dispatches['list'] ?? []) }} Trips)</h3>
        <span class="section-sub">Total Dispatched: {{ number_format($dispatches['delivered_qty'] ?? 0, 2) }} m³ | Net Weight: {{ number_format($dispatches['net_weight'] ?? 0, 2) }} T</span>
    </div>

    <table class="data-table">
        <thead>
            <tr>
                <th width="4%" class="text-center">#</th>
                <th width="12%">Batch Number</th>
                <th width="22%">Customer Name</th>
                <th width="16%">Unloading Site</th>
                <th width="10%">Truck</th>
                <th width="12%">Driver</th>
                <th width="8%" class="text-right">Qty (m³)</th>
                <th width="8%" class="text-right">Net Wt (T)</th>
                <th width="8%" class="text-right">Amount (₹)</th>
            </tr>
        </thead>
        <tbody>
            @forelse($dispatches['list'] ?? [] as $d)
                <tr>
                    <td class="text-center">{{ $d['index'] }}</td>
                    <td style="font-weight: bold; color: #0284c7;">{{ $d['batch_number'] ?? '-' }}</td>
                    <td style="font-weight: bold; color: #0f172a;">{{ $d['customer_name'] }}</td>
                    <td style="color: #475569;">{{ $d['site_name'] }}</td>
                    <td style="font-weight: bold; color: #4338ca;">{{ $d['truck_no'] }}</td>
                    <td style="color: #475569;">{{ $d['driver_name'] }}</td>
                    <td class="text-right" style="font-weight: bold;">{{ number_format($d['delivered_qty'], 2) }}</td>
                    <td class="text-right">{{ number_format($d['net_weight'], 2) }}</td>
                    <td class="text-right" style="font-weight: bold;">{{ number_format($d['total_amount'], 2) }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="9" class="text-center" style="color: #94a3b8; padding: 8px;">No concrete dispatches recorded for this date.</td>
                </tr>
            @endforelse
            <tr class="total-row">
                <td colspan="6" class="text-center uppercase">Total Dispatches</td>
                <td class="text-right">{{ number_format($dispatches['delivered_qty'] ?? 0, 2) }}</td>
                <td class="text-right">{{ number_format($dispatches['net_weight'] ?? 0, 2) }}</td>
                <td class="text-right">₹ {{ number_format($dispatches['total_value'] ?? 0, 2) }}</td>
            </tr>
        </tbody>
    </table>

    <!-- Section 2: Invoicing & Compliance -->
    <div class="section-header">
        <h3 class="section-title">2. Invoicing, Billing & Compliance ({{ count($invoicing['list'] ?? []) }} Invoices)</h3>
        <span class="section-sub">Total Billed: ₹ {{ number_format($invoicing['sales_total_billed'] ?? 0, 2) }} | Pending Credit: ₹ {{ number_format($invoicing['sales_balance_due'] ?? 0, 2) }}</span>
    </div>

    <table class="data-table">
        <thead>
            <tr>
                <th width="4%" class="text-center">#</th>
                <th width="14%">Invoice No</th>
                <th width="24%">Customer</th>
                <th width="12%">GSTIN</th>
                <th width="10%" class="text-right">Subtotal</th>
                <th width="10%" class="text-right">GST Tax</th>
                <th width="12%" class="text-right">Total (₹)</th>
                <th width="14%" class="text-center">Status / Balance</th>
            </tr>
        </thead>
        <tbody>
            @forelse($invoicing['list'] ?? [] as $inv)
                <tr>
                    <td class="text-center">{{ $inv['index'] }}</td>
                    <td style="font-weight: bold; color: #0284c7;">{{ $inv['invoice_no'] }}</td>
                    <td style="font-weight: bold; color: #0f172a;">{{ $inv['customer_name'] }}</td>
                    <td style="color: #64748b;">{{ $inv['gstin'] }}</td>
                    <td class="text-right">{{ number_format($inv['subtotal'], 2) }}</td>
                    <td class="text-right">{{ number_format($inv['tax_amount'], 2) }}</td>
                    <td class="text-right" style="font-weight: bold;">{{ number_format($inv['total_amount'], 2) }}</td>
                    <td class="text-center">
                        @if($inv['balance_amount'] > 0)
                            <span class="badge badge-danger">Due: ₹ {{ number_format($inv['balance_amount'], 2) }}</span>
                        @else
                            <span class="badge badge-success">Paid</span>
                        @endif
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="8" class="text-center" style="color: #94a3b8; padding: 8px;">No sales invoices generated for this date.</td>
                </tr>
            @endforelse
            <tr class="total-row">
                <td colspan="4" class="text-center uppercase">Total Invoiced</td>
                <td class="text-right">{{ number_format($invoicing['sales_subtotal'] ?? 0, 2) }}</td>
                <td class="text-right">{{ number_format($invoicing['sales_tax_amount'] ?? 0, 2) }}</td>
                <td class="text-right">₹ {{ number_format($invoicing['sales_total_billed'] ?? 0, 2) }}</td>
                <td class="text-center" style="color: #b91c1c;">Due: ₹ {{ number_format($invoicing['sales_balance_due'] ?? 0, 2) }}</td>
            </tr>
        </tbody>
    </table>

    <!-- Section 3: Cash Flow Day Book & Tax Breakdown -->
    <table style="width: 100%; border-collapse: collapse;">
        <tr>
            <!-- Left: Receipts -->
            <td style="width: 48%; vertical-align: top;">
                <div class="section-header">
                    <h3 class="section-title">3A. Collections & Receipts (₹ {{ number_format($cash_flow['total_receipts'] ?? 0, 2) }})</h3>
                </div>
                <table class="data-table">
                    <thead>
                        <tr>
                            <th width="20%">Voucher</th>
                            <th width="40%">Customer</th>
                            <th width="20%">Mode</th>
                            <th width="20%" class="text-right">Amount (₹)</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($cash_flow['receipts_list'] ?? [] as $r)
                            <tr>
                                <td style="font-weight: bold;">{{ $r['voucher_no'] }}</td>
                                <td>{{ $r['customer'] }}</td>
                                <td>{{ $r['mode'] }}</td>
                                <td class="text-right" style="font-weight: bold; color: #166534;">{{ number_format($r['amount'], 2) }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="4" class="text-center" style="color: #94a3b8;">No receipts logged.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </td>

            <td style="width: 4%;"></td>

            <!-- Right: Payments -->
            <td style="width: 48%; vertical-align: top;">
                <div class="section-header">
                    <h3 class="section-title">3B. Outflows & Payments (₹ {{ number_format($cash_flow['total_payments'] ?? 0, 2) }})</h3>
                </div>
                <table class="data-table">
                    <thead>
                        <tr>
                            <th width="20%">Voucher</th>
                            <th width="40%">Beneficiary / Account</th>
                            <th width="20%">Mode</th>
                            <th width="20%" class="text-right">Amount (₹)</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($cash_flow['payments_list'] ?? [] as $p)
                            <tr>
                                <td style="font-weight: bold;">{{ $p['voucher_no'] }}</td>
                                <td>{{ $p['beneficiary'] }}</td>
                                <td>{{ $p['mode'] }}</td>
                                <td class="text-right" style="font-weight: bold; color: #9a3412;">{{ number_format($p['amount'], 2) }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="4" class="text-center" style="color: #94a3b8;">No payments logged.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </td>
        </tr>
    </table>

</body>
</html>
