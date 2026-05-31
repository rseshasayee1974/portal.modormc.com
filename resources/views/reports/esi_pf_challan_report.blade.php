<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>ESI & PF Challan Summary Report</title>
    <style>
        @page { margin: 35px; }
        body { font-family: 'DejaVu Sans', sans-serif; font-size: 8pt; color: #1e293b; line-height: 1.3; }
        .header-table { width: 100%; border-collapse: collapse; margin-bottom: 15px; }
        .header-table td { padding: 0; vertical-align: top; }
        .address-box { font-size: 8pt; color: #334155; }
        .address-title { font-weight: bold; color: #64748b; font-size: 8pt; text-transform: uppercase; }
        .address-name { font-weight: bold; font-size: 10.5pt; color: #0f172a; display: block; margin-bottom: 2px; }
        .title-container { border-bottom: 2px solid #1d2d3e; padding-bottom: 5px; margin-bottom: 12px; }
        .title { font-size: 12pt; font-weight: bold; color: #1d2d3e; margin: 0; text-transform: uppercase; }
        .table-section-title { background-color: #1d2d3e; color: #ffffff; font-weight: bold; padding: 6px 8px; font-size: 8.5pt; margin-top: 15px; margin-bottom: 0px; border: 1px solid #1d2d3e; }
        table.data-table { width: 100%; border-collapse: collapse; margin-bottom: 15px; border: 1px solid #cbd5e1; }
        table.data-table th { background-color: #f1f5f9; color: #0f172a; border: 1px solid #cbd5e1; padding: 5px 4px; font-size: 7.5pt; font-weight: bold; text-align: center; }
        table.data-table td { padding: 5px 6px; border: 1px solid #cbd5e1; font-size: 7.5pt; vertical-align: middle; }
        table.data-table tr.total-row { background-color: #e2e8f0; font-weight: bold; }
        .text-right { text-align: right; }
        .text-center { text-align: center; }
        .text-left { text-align: left; }
        .footer { font-size: 7.5pt; color: #64748b; text-align: center; margin-top: 20px; border-top: 1px solid #cbd5e1; padding-top: 5px; font-style: italic; }
    </style>
</head>
<body>
    <table class="header-table">
        <tr>
            <td style="width: 50%;">
                <div class="address-box">
                    <span class="address-title">Payroll Compliance Report</span>
                    <span class="address-name">ESI & PF Statutory Challan</span>
                    <strong>Report Period:</strong> {{ $start }} to {{ $end }}<br>
                </div>
            </td>
            <td style="width: 50%; text-align: right;">
                <div class="address-box">
                    <span class="address-title">Plant / Branch:</span>
                    <span class="address-name">{{ $plant->name ?? '' }}</span>
                    @if($plant && $plant->addresses->isNotEmpty())
                        @php $plAddr = $plant->addresses->first(); @endphp
                        {{ $plAddr->address_line1 ?? '' }}<br>
                        {{ $plAddr->city ?? '' }}, {{ $plAddr->state->state_name ?? '' }} - {{ $plAddr->pincode ?? '' }}
                    @endif
                </div>
            </td>
        </tr>
    </table>

    <div class="title-container">
        <h2 class="title">Statutory Challan Computation Details</h2>
    </div>

    <!-- 1. Provident Fund (PF) Table -->
    <div class="table-section-title">1. Provident Fund (PF) Challan Details</div>
    <table class="data-table">
        <thead>
            <tr>
                <th>Emp Code</th>
                <th>Employee Name</th>
                <th>UAN</th>
                <th width="10%">Gross Wages</th>
                <th width="10%">EPF Wages</th>
                <th width="10%">EPS Wages</th>
                <th width="11%">Employee PF (12%)</th>
                <th width="11%">Employer EPS (8.33%)</th>
                <th width="11%">Employer EPF (3.67%)</th>
                <th width="11%">Total PF Payable</th>
            </tr>
        </thead>
        <tbody>
            @forelse($pf as $row)
                <tr>
                    <td class="text-center">{{ $row['employee_code'] }}</td>
                    <td class="text-left">{{ $row['name'] }}</td>
                    <td class="text-center">{{ $row['uan'] }}</td>
                    <td class="text-right">₹{{ number_format($row['gross_wages'], 2) }}</td>
                    <td class="text-right">₹{{ number_format($row['epf_wages'], 2) }}</td>
                    <td class="text-right">₹{{ number_format($row['eps_wages'], 2) }}</td>
                    <td class="text-right">₹{{ number_format($row['employee_contribution'], 2) }}</td>
                    <td class="text-right">₹{{ number_format($row['employer_eps_share'], 2) }}</td>
                    <td class="text-right">₹{{ number_format($row['employer_epf_share'], 2) }}</td>
                    <td class="text-right font-bold">₹{{ number_format($row['total_contribution'], 2) }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="10" class="text-center" style="color: #64748b; padding: 15px 0;">No PF contributions logged for this period.</td>
                </tr>
            @endforelse

            @if(count($pf) > 0)
                <tr class="total-row">
                    <td colspan="3" class="text-center">Total Summary</td>
                    <td class="text-right">₹{{ number_format($pf_totals['gross_wages'], 2) }}</td>
                    <td class="text-right">₹{{ number_format($pf_totals['epf_wages'], 2) }}</td>
                    <td class="text-right">₹{{ number_format($pf_totals['eps_wages'], 2) }}</td>
                    <td class="text-right">₹{{ number_format($pf_totals['employee_contribution'], 2) }}</td>
                    <td class="text-right">₹{{ number_format($pf_totals['employer_eps_share'], 2) }}</td>
                    <td class="text-right">₹{{ number_format($pf_totals['employer_epf_share'], 2) }}</td>
                    <td class="text-right">₹{{ number_format($pf_totals['total_contribution'], 2) }}</td>
                </tr>
            @endif
        </tbody>
    </table>

    <!-- 2. Employee State Insurance (ESI) Table -->
    <div class="table-section-title" style="page-break-before: auto;">2. Employee State Insurance (ESI) Challan Details</div>
    <table class="data-table">
        <thead>
            <tr>
                <th>Emp Code</th>
                <th>Employee Name</th>
                <th>ESI Number</th>
                <th width="10%">Days Worked</th>
                <th width="12%">Gross Wages</th>
                <th width="15%">Employee ESI (0.75%)</th>
                <th width="15%">Employer ESI (3.25%)</th>
                <th width="15%">Total ESI Payable</th>
            </tr>
        </thead>
        <tbody>
            @forelse($esi as $row)
                <tr>
                    <td class="text-center">{{ $row['employee_code'] }}</td>
                    <td class="text-left">{{ $row['name'] }}</td>
                    <td class="text-center">{{ $row['esi_number'] }}</td>
                    <td class="text-center">{{ $row['days_worked'] }}</td>
                    <td class="text-right">₹{{ number_format($row['gross_wages'], 2) }}</td>
                    <td class="text-right">₹{{ number_format($row['employee_contribution'], 2) }}</td>
                    <td class="text-right">₹{{ number_format($row['employer_contribution'], 2) }}</td>
                    <td class="text-right font-bold">₹{{ number_format($row['total_contribution'], 2) }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="8" class="text-center" style="color: #64748b; padding: 15px 0;">No ESI contributions logged for this period.</td>
                </tr>
            @endforelse

            @if(count($esi) > 0)
                <tr class="total-row">
                    <td colspan="3" class="text-center">Total Summary</td>
                    <td class="text-center">{{ $esi_totals['days_worked'] }}</td>
                    <td class="text-right">₹{{ number_format($esi_totals['gross_wages'], 2) }}</td>
                    <td class="text-right">₹{{ number_format($esi_totals['employee_contribution'], 2) }}</td>
                    <td class="text-right">₹{{ number_format($esi_totals['employer_contribution'], 2) }}</td>
                    <td class="text-right">₹{{ number_format($esi_totals['total_contribution'], 2) }}</td>
                </tr>
            @endif
        </tbody>
    </table>

    <div class="footer">
        Powered by ModoRMC ERP - Modern Compliance System
    </div>
</body>
</html>
