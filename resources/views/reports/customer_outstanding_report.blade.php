<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Customer Outstanding & Aging Report</title>
    <style>
        {!! $css ?? $report_css ?? (file_exists(public_path('css/reports/report_pdf.css')) ? file_get_contents(public_path('css/reports/report_pdf.css')) : '') !!}
    </style>
</head>
<body>

    <table class="header-table">
        <tr>
            <td style="width: 55%; vertical-align: top;">
                <div class="address-box">
                    <span class="address-title">Entity / Plant:</span>
                    <span class="address-name" style="color: #0369a1;">{{ $plant->name ?? 'Ready Mix Concrete Operations' }}</span>
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
            
            <td style="width: 45%; text-align: right; vertical-align: top;">
                <div class="address-box">
                    <span class="address-title">Customer Filter:</span>
                    @if(!empty($patron))
                        <span class="address-name">{{ $patron->legal_name }}</span>
                        <strong>Code:</strong> {{ $patron->code ?? '-' }} | <strong>GSTIN:</strong> {{ $patron->gstin ?? '-' }}<br>
                    @else
                        <span class="address-name">All Active Customers</span>
                        <span>Party-wise Outstanding Balances & Aging</span><br>
                    @endif
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

    <!-- Section 1: Customer Consolidated Outstanding & Aging Table -->
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
                    <td class="text-right">{{ number_format($row['total_invoiced'] ?? 0, 2) }}</td>
                    <td class="text-right" style="color: #166534;">{{ number_format($row['total_receipt'] ?? ($row['total_paid'] ?? 0), 2) }}</td>
                    <td class="text-right" style="color: #0284c7;">{{ number_format($row['total_payment'] ?? 0, 2) }}</td>
                    <td class="text-right" style="font-weight: bold; color: {{ ($row['total_outstanding'] ?? 0) > 0 ? '#b91c1c' : '#334155' }};">
                        {{ number_format($row['total_outstanding'] ?? 0, 2) }}
                    </td>
                    <td class="text-right" style="color: #1e40af;">
                        {{ ($row['aging_0_30'] ?? 0) > 0 ? number_format($row['aging_0_30'], 2) : '-' }}
                    </td>
                    <td class="text-right" style="color: #b45309;">
                        {{ ($row['aging_31_60'] ?? 0) > 0 ? number_format($row['aging_31_60'], 2) : '-' }}
                    </td>
                    <td class="text-right" style="color: #c2410c;">
                        {{ ($row['aging_61_90'] ?? 0) > 0 ? number_format($row['aging_61_90'], 2) : '-' }}
                    </td>
                    <td class="text-right" style="color: {{ ($row['aging_90_plus'] ?? 0) > 0 ? '#be123c' : '#94a3b8' }}; font-weight: bold;">
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
                <td class="text-right">{{ number_format($total_invoiced_amount ?? 0, 2) }}</td>
                <td class="text-right">{{ number_format($total_receipt_amount ?? ($total_paid_amount ?? 0), 2) }}</td>
                <td class="text-right">{{ number_format($total_payment_amount ?? 0, 2) }}</td>
                <td class="text-right" style="color: #b91c1c;">₹ {{ number_format($total_outstanding_amount ?? 0, 2) }}</td>
                <td class="text-right" style="color: #1e40af;">{{ number_format($aging_0_30 ?? 0, 2) }}</td>
                <td class="text-right" style="color: #b45309;">{{ number_format($aging_31_60 ?? 0, 2) }}</td>
                <td class="text-right" style="color: #c2410c;">{{ number_format($aging_61_90 ?? 0, 2) }}</td>
                <td class="text-right" style="color: #be123c;">₹ {{ number_format($aging_90_plus ?? 0, 2) }}</td>
            </tr>
        </tbody>
    </table>

</body>
</html>

