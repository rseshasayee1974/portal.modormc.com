<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>GSTR-3B Summary Report</title>
    <style>
        {!! $css ?? $report_css ?? (file_exists(public_path('css/reports/gstr3b_report.css')) ? file_get_contents(public_path('css/reports/gstr3b_report.css')) : (file_exists(public_path('css/reports/report_pdf.css')) ? file_get_contents(public_path('css/reports/report_pdf.css')) : '')) !!}
    </style>
</head>
<body>
    <table class="header-table">
        <tr>
            <td style="width: 50%;">
                <div class="address-box">
                    <span class="address-title">GST Compliance Report</span>
                    <span class="address-name">GSTR-3B Self-Assessment Summary</span>
                    <strong>GSTIN:</strong> {{ $plant->gstin ?? 'N/A' }}<br>
                </div>
            </td>
            <td style="width: 50%; text-align: right;">
                <div class="address-box">
                    <span class="address-title">Plant / Branch:</span>
                    <span class="address-name">{{ $plant->name ?? '' }}</span>
                    @if($plant && $plant->addresses->isNotEmpty())
                        @php $plAddr = $plant->addresses->first(); @endphp
                        {{ $plAddr->line_1 ?? '' }}<br>
                        {{ $plAddr->city ?? '' }}, {{ $plAddr->state->state_name ?? '' }} - {{ $plAddr->zipcode ?? '' }}
                    @endif
                </div>
            </td>
        </tr>
    </table>

    <div class="title-container">
        <h2 class="title">GSTR-3B Return Summary (Period: {{ $start }} to {{ $end }})</h2>
    </div>

    <!-- Table 3.1 Outward supplies -->
    <div class="table-section-title">Table 3.1: Details of Outward Supplies and Inward Supplies liable to Reverse Charge</div>
    <table class="data-table">
        <thead>
            <tr>
                <th>Nature of Supplies</th>
                <th width="20%">Total Taxable Value</th>
                <th width="18%">Integrated Tax (IGST)</th>
                <th width="18%">Central Tax (CGST)</th>
                <th width="18%">State/UT Tax (SGST)</th>
            </tr>
        </thead>
        <tbody>
            <tr>
                <td>(a) Outward Taxable Supplies (other than zero rated, nil rated, exempted)</td>
                <td class="text-right">₹ {{ number_format($table31['a']['taxable'], 2) }}</td>
                <td class="text-right">₹ {{ number_format($table31['a']['igst'], 2) }}</td>
                <td class="text-right">₹ {{ number_format($table31['a']['cgst'], 2) }}</td>
                <td class="text-right">₹ {{ number_format($table31['a']['sgst'], 2) }}</td>
            </tr>
            <tr>
                <td>(b) Outward Taxable Supplies (zero rated / exports)</td>
                <td class="text-right">₹ {{ number_format($table31['b']['taxable'], 2) }}</td>
                <td class="text-right">₹ {{ number_format($table31['b']['igst'], 2) }}</td>
                <td class="text-right">₹ {{ number_format($table31['b']['cgst'], 2) }}</td>
                <td class="text-right">₹ {{ number_format($table31['b']['sgst'], 2) }}</td>
            </tr>
            <tr>
                <td>(c) Other Outward Supplies (nil rated, exempted)</td>
                <td class="text-right">₹ {{ number_format($table31['c']['taxable'], 2) }}</td>
                <td class="text-right">₹ {{ number_format($table31['c']['igst'], 2) }}</td>
                <td class="text-right">₹ {{ number_format($table31['c']['cgst'], 2) }}</td>
                <td class="text-right">₹ {{ number_format($table31['c']['sgst'], 2) }}</td>
            </tr>
            <tr>
                <td>(d) Inward Supplies (liable to reverse charge)</td>
                <td class="text-right">₹ {{ number_format($table31['d']['taxable'], 2) }}</td>
                <td class="text-right">₹ {{ number_format($table31['d']['igst'], 2) }}</td>
                <td class="text-right">₹ {{ number_format($table31['d']['cgst'], 2) }}</td>
                <td class="text-right">₹ {{ number_format($table31['d']['sgst'], 2) }}</td>
            </tr>
            <tr>
                <td>(e) Non-GST Outward Supplies</td>
                <td class="text-right">₹ {{ number_format($table31['e']['taxable'], 2) }}</td>
                <td class="text-right">₹ {{ number_format($table31['e']['igst'], 2) }}</td>
                <td class="text-right">₹ {{ number_format($table31['e']['cgst'], 2) }}</td>
                <td class="text-right">₹ {{ number_format($table31['e']['sgst'], 2) }}</td>
            </tr>
        </tbody>
    </table>

    <!-- Table 4 ITC -->
    <div class="table-section-title">Table 4: Details of Eligible Input Tax Credit (ITC)</div>
    <table class="data-table">
        <thead>
            <tr>
                <th>Details</th>
                <th width="20%">Integrated Tax (IGST)</th>
                <th width="20%">Central Tax (CGST)</th>
                <th width="20%">State/UT Tax (SGST)</th>
            </tr>
        </thead>
        <tbody>
            <tr class="font-bold" style="background-color: #f8fafc;">
                <td colspan="4">(A) ITC Available (whether in full or part)</td>
            </tr>
            <tr>
                <td style="padding-left: 20px;">(1) Import of goods</td>
                <td class="text-right">₹ {{ number_format($table4['import_goods']['igst'], 2) }}</td>
                <td class="text-right">₹ {{ number_format($table4['import_goods']['cgst'], 2) }}</td>
                <td class="text-right">₹ {{ number_format($table4['import_goods']['sgst'], 2) }}</td>
            </tr>
            <tr>
                <td style="padding-left: 20px;">(2) Import of services</td>
                <td class="text-right">₹ {{ number_format($table4['import_services']['igst'], 2) }}</td>
                <td class="text-right">₹ {{ number_format($table4['import_services']['cgst'], 2) }}</td>
                <td class="text-right">₹ {{ number_format($table4['import_services']['sgst'], 2) }}</td>
            </tr>
            <tr>
                <td style="padding-left: 20px;">(3) Inward supplies liable to reverse charge (other than 1 & 2 above)</td>
                <td class="text-right">₹ {{ number_format($table4['reverse_charge']['igst'], 2) }}</td>
                <td class="text-right">₹ {{ number_format($table4['reverse_charge']['cgst'], 2) }}</td>
                <td class="text-right">₹ {{ number_format($table4['reverse_charge']['sgst'], 2) }}</td>
            </tr>
            <tr>
                <td style="padding-left: 20px;">(4) Inward supplies from Input Service Distributor (ISD)</td>
                <td class="text-right">₹ {{ number_format($table4['isd_itc']['igst'], 2) }}</td>
                <td class="text-right">₹ {{ number_format($table4['isd_itc']['cgst'], 2) }}</td>
                <td class="text-right">₹ {{ number_format($table4['isd_itc']['sgst'], 2) }}</td>
            </tr>
            <tr class="font-bold">
                <td style="padding-left: 20px;">(5) All other ITC (Purchase Bills Input Tax Credit)</td>
                <td class="text-right">₹ {{ number_format($table4['other_itc']['igst'], 2) }}</td>
                <td class="text-right">₹ {{ number_format($table4['other_itc']['cgst'], 2) }}</td>
                <td class="text-right">₹ {{ number_format($table4['other_itc']['sgst'], 2) }}</td>
            </tr>
        </tbody>
    </table>
</body>
</html>
