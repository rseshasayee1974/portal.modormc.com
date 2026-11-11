<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Payslip - {{ $payslip->payslip_no }}</title>
    @include('pdfs.partials._common_styles')
    <style>
        @page {
            size: A4 portrait;
            margin: 8mm 9mm;
        }
        .payslip-root {
            background: #fff;
            position: relative;
            width: 500pt !important;
            max-width: 500pt !important;
            min-width: 0 !important;
            margin: 0 auto !important;
            padding: 0 !important;
            border: 0 !important;
        }
        .payslip-root .print-brand-header,
        .payslip-root .payslip-header,
        .payslip-root .info-section,
        .payslip-root .matrix-container,
        .payslip-root .summary-banner,
        .payslip-root .sig-container,
        .payslip-root .powered-footer {
            width: 500pt !important;
            max-width: 500pt !important;
            table-layout: fixed !important;
        }
        .payslip-root .matrix-table {
            width: 250pt !important;
            max-width: 250pt !important;
            table-layout: fixed !important;
        }
        @media screen {
            body { padding: 24px 16px !important; }
            .payslip-root {
                width: 100% !important;
                max-width: 820px !important;
                margin: 0 auto !important;
                padding: 24px !important;
                border: 1px solid #cbd5e1 !important;
                border-radius: 8px;
                box-shadow: 0 10px 25px -5px rgba(15, 23, 42, .12);
            }
        }
        @if ($is_pdf ?? false)
        .payslip-root {
            width: 500pt !important;
            max-width: 500pt !important;
            margin: 0 auto !important;
            padding: 0 !important;
            border: 0 !important;
            box-shadow: none !important;
        }
        @endif

        /* HEADER */
        .payslip-header {
            display: table;
            table-layout: fixed;
            width: 100%;
            border-bottom: 2px solid #1e293b;
            padding-bottom: 14px;
            margin-bottom: 16px;
            page-break-inside: avoid;
        }
        .header-left {
            display: table-cell;
            vertical-align: top;
        }
        .header-right {
            display: table-cell;
            vertical-align: top;
            text-align: right;
        }
        .co-name {
            font-size: 16px;
            font-weight: 800;
            color: #1e293b;
            text-transform: uppercase;
        }
        .co-detail {
            font-size: 11px;
            color: #64748b;
            line-height: 1.4;
        }
        .payslip-title {
            font-size: 23px;
            font-weight: 900;
            color: #1e293b;
            letter-spacing: 0.05em;
        }
        .payslip-period {
            font-size: 12px;
            font-weight: 700;
            color: #4f46e5;
            margin-top: 4px;
        }

        /* EMPLOYEE & ATTENDANCE INFO BLOCK */
        .info-section {
            width: 100%;
            table-layout: fixed;
            border-collapse: collapse;
            margin-bottom: 12px;
            border: 1px solid #cbd5e1;
            page-break-inside: avoid;
        }
        .info-section td {
            padding: 8px 10px;
            vertical-align: top;
            font-size: 11px;
            border: 1px solid #e2e8f0;
        }
        .info-hdr {
            background: #f8fafc;
            font-weight: 700;
            color: #475569;
            text-transform: uppercase;
            width: 18%;
        }
        .ytd-section .info-hdr {
            padding-right: 5px;
            padding-left: 5px;
            font-size: 9px;
            white-space: nowrap;
        }
        .info-val {
            color: #1e293b;
            width: 32%;
        }

        /* EARNINGS & DEDUCTIONS DUAL COLUMN MATRIX */
        .matrix-container {
            display: table;
            table-layout: fixed;
            width: 100%;
            border: 1px solid #cbd5e1;
            margin-bottom: 14px;
        }
        .matrix-col {
            display: table-cell;
            width: 50%;
            vertical-align: top;
        }
        .matrix-col-left {
            border-right: 1px solid #cbd5e1;
        }
        .matrix-table {
            width: 100%;
            border-collapse: collapse;
        }
        .matrix-table th {
            background: #f1f5f9;
            border-bottom: 1px solid #cbd5e1;
            padding: 6px 7px;
            font-size: 9px;
            font-weight: 700;
            text-transform: uppercase;
            color: #334155;
            letter-spacing: 0.02em;
        }
        .matrix-table td {
            padding: 8px 10px;
            font-size: 11px;
            border-bottom: 1px solid #e2e8f0;
            color: #475569;
        }
        .matrix-table tr.total-row td {
            font-weight: 700;
            color: #1e293b;
            background: #f8fafc;
            border-top: 1px solid #cbd5e1;
            border-bottom: none;
            font-size: 11px;
        }

        /* NET PAY SUMMARY BANNER */
        .summary-banner {
            background: #f5f3ff;
            border: 1.5px solid #ddd6fe;
            border-radius: 6px;
            margin-top: 16px;
            margin-bottom: 18px;
            display: table;
            table-layout: fixed;
            width: 100%;
            page-break-inside: avoid;
        }
        .banner-left {
            display: table-cell;
            vertical-align: middle;
            width: 70%;
            padding: 14px 16px;
        }
        .banner-right {
            display: table-cell;
            vertical-align: middle;
            text-align: right;
            width: 30%;
            padding: 14px 16px;
        }
        .net-pay-title {
            font-size: 9px;
            font-weight: 700;
            color: #6d28d9;
            text-transform: uppercase;
            letter-spacing: 0.1em;
        }
        .net-pay-val {
            font-size: 21px;
            font-weight: 700;
            color: #6d28d9;
        }
        .net-pay-words {
            font-size: 10px;
            color: #4c1d95;
            font-style: italic;
            margin-top: 2px;
            font-weight: 700;
        }

        /* SIGNATURE SECTION */
        .sig-container {
            display: table;
            table-layout: fixed;
            width: 100%;
            margin-top: 64px;
            margin-bottom: 24px;
            page-break-inside: avoid;
        }
        .sig-block {
            display: table-cell;
            width: 50%;
            text-align: center;
            vertical-align: bottom;
        }
        .sig-line {
            display: inline-block;
            width: 220px;
            border-top: 1px dashed #94a3b8;
            padding-top: 6px;
            font-size: 10px;
            color: #64748b;
            font-weight: 600;
        }
        @media print {
            body { padding: 0 !important; }
            .payslip-root {
                width: 500pt !important;
                max-width: 500pt !important;
                margin: 0 auto !important;
                padding: 0 !important;
                border: 0 !important;
                border-radius: 0 !important;
                box-shadow: none !important;
            }
            .powered-footer .pf-left,
            .powered-footer .pf-right { padding: 8px 6px !important; }
        }
    </style>
</head>
<body>
@include('pdfs.partials._print_actions')
<div class="payslip-root inv-root">
@include('pdfs.partials._brand_header')

    {{-- HEADER --}}
    <div class="payslip-header">
        
        <div class="header-right">
            <div class="payslip-title">PAYSLIP</div>
            <div class="co-detail">Payslip No: <strong>{{ $payslip->payslip_no }}</strong></div>
            <div class="payslip-period">For Period: {{ strtoupper($payslip->payrollPeriod?->name ?? 'Period unavailable') }}</div>
            <div class="co-detail" style="margin-top:2px;">Cycle: {{ $payslip->payrollPeriod?->from_date?->format('d/m/Y') ?? 'N/A' }} to {{ $payslip->payrollPeriod?->to_date?->format('d/m/Y') ?? 'N/A' }}</div>
        </div>
    </div>

    {{-- EMPLOYEE & ATTENDANCE INFORMATION GRID --}}
    <table class="info-section">
        <tr>
            <td class="info-hdr">Employee Code</td>
            <td class="info-val bold text-indigo">{{ $payslip->personnel?->employee_code }}</td>
            <td class="info-hdr">Working Days</td>
            <td class="info-val bold">{{ $payslip->working_days + 0 }}</td>
        </tr>
        <tr>
            <td class="info-hdr">Employee Name</td>
            <td class="info-val bold">{{ $payslip->personnel?->first_name }} {{ $payslip->personnel?->last_name }}</td>
            <td class="info-hdr">Days Present</td>
            <td class="info-val bold text-green">{{ $payslip->present_days + 0 }}</td>
        </tr>
        <tr>
            <td class="info-hdr">Department</td>
            <td class="info-val">{{ $payslip->personnel?->department?->name ?? 'N/A' }}</td>
            <td class="info-hdr">Paid Leaves</td>
            <td class="info-val bold text-indigo">{{ $payslip->paid_leave_days + 0 }}</td>
        </tr>
        <tr>
            <td class="info-hdr">Designation</td>
            <td class="info-val">{{ $payslip->personnel?->designation?->name ?? 'N/A' }}</td>
            <td class="info-hdr">Days Absent</td>
            <td class="info-val bold text-red">{{ $payslip->absent_days + 0 }}</td>
        </tr>
        <tr>
            <td class="info-hdr">PF UAN</td>
            <td class="info-val">{{ $payslip->personnel?->uan ?? '-' }}</td>
            <td class="info-hdr">Bank Name</td>
            <td class="info-val">{{ $payslip->personnel?->bank_name ?? '-' }}</td>
        </tr>
        <tr>
            <td class="info-hdr">ESI Number</td>
            <td class="info-val">{{ $payslip->personnel?->esi_number ?? '-' }}</td>
            <td class="info-hdr">Bank A/C No.</td>
            <td class="info-val bold">{{ $payslip->personnel?->bank_account_no ?? '-' }}</td>
        </tr>
    </table>

    {{-- FINANCIAL YEAR-TO-DATE TOTALS --}}
    <table class="info-section ytd-section" style="margin-top: 8px;">
        <tr>
            <td class="info-hdr">FY Gross to Date</td>
            <td class="info-val bold">&#8377;{{ number_format($yearToDate['gross'] ?? 0, 2) }}</td>
            <td class="info-hdr">FY TDS to Date</td>
            <td class="info-val bold text-red">&#8377;{{ number_format($yearToDate['tds'] ?? 0, 2) }}</td>
        </tr>
    </table>

    {{-- DUAL COLUMN EARNINGS & DEDUCTIONS MATRIX --}}
    @php
        $earnings = $payslip->items->where('type', 'earning')->values();
        $deductions = $payslip->items->where('type', 'deduction')->values();
        $maxRows = max($earnings->count(), $deductions->count());
    @endphp

    <div class="matrix-container">
        <!-- Earnings Column -->
        <div class="matrix-col matrix-col-left">
            <table class="matrix-table">
                <thead>
                    <tr>
                        <th class="text-left">Earnings Description</th>
                        <th class="text-right" style="width: 100px;">Amount (&#8377;)</th>
                    </tr>
                </thead>
                <tbody>
                    @for($i = 0; $i < $maxRows; $i++)
                        @php $item = $earnings->get($i); @endphp
                        <tr>
                            <td>{!! $item ? e($item->component_name) : '&nbsp;' !!}</td>
                            <td class="text-right bold">{{ $item ? number_format($item->amount, 2) : '' }}</td>
                        </tr>
                    @endfor
                    <tr class="total-row">
                        <td>Total Earnings (Gross)</td>
                        <td class="text-right bold">&#8377;{{ number_format($payslip->total_earnings, 2) }}</td>
                    </tr>
                </tbody>
            </table>
        </div>

        <!-- Deductions Column -->
        <div class="matrix-col">
            <table class="matrix-table">
                <thead>
                    <tr>
                        <th class="text-left">Deductions Description</th>
                        <th class="text-right" style="width: 100px;">Amount (&#8377;)</th>
                    </tr>
                </thead>
                <tbody>
                    @for($i = 0; $i < $maxRows; $i++)
                        @php $item = $deductions->get($i); @endphp
                        <tr>
                            <td>{!! $item ? e($item->component_name) : '&nbsp;' !!}</td>
                            <td class="text-right bold text-red">{{ $item ? number_format($item->amount, 2) : '' }}</td>
                        </tr>
                    @endfor
                    <tr class="total-row">
                        <td>Total Deductions</td>
                        <td class="text-right bold text-red">&#8377;{{ number_format($payslip->total_deductions, 2) }}</td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>

    {{-- NET PAY BANNER --}}
    <div class="summary-banner">
        <div class="banner-left">
            <div class="net-pay-title">Net Take-Home Salary</div>
            <div class="net-pay-words">
                {{ \App\Services\PrintDataFormatter::numberToWords($payslip->net_salary) }}
            </div>
        </div>
        <div class="banner-right">
            <div class="net-pay-val">&#8377;{{ number_format($payslip->net_salary, 2) }}</div>
        </div>
    </div>

    {{-- SIGNATURE LINES --}}
    <div class="sig-container">
        <div class="sig-block">
            <div class="sig-line">Employee Signature</div>
        </div>
        <div class="sig-block">
            <div class="sig-line">Authorized Signatory</div>
        </div>
    </div>

    @include('pdfs.partials._footer')
</div>
</body>
</html>
