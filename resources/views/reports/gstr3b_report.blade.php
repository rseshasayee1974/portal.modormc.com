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
@include('pdfs.partials._brand_header')

    <table class="header-table">
        <tr>
            <td style="width: 50%;">
                
            </td>
            <td style="width: 50%; text-align: right;">
                
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
                <td class="text-right">₹ {{ \App\Services\PrintDataFormatter::money($table31['a']['taxable']) }}</td>
                <td class="text-right">₹ {{ \App\Services\PrintDataFormatter::money($table31['a']['igst']) }}</td>
                <td class="text-right">₹ {{ \App\Services\PrintDataFormatter::money($table31['a']['cgst']) }}</td>
                <td class="text-right">₹ {{ \App\Services\PrintDataFormatter::money($table31['a']['sgst']) }}</td>
            </tr>
            <tr>
                <td>(b) Outward Taxable Supplies (zero rated / exports)</td>
                <td class="text-right">₹ {{ \App\Services\PrintDataFormatter::money($table31['b']['taxable']) }}</td>
                <td class="text-right">₹ {{ \App\Services\PrintDataFormatter::money($table31['b']['igst']) }}</td>
                <td class="text-right">₹ {{ \App\Services\PrintDataFormatter::money($table31['b']['cgst']) }}</td>
                <td class="text-right">₹ {{ \App\Services\PrintDataFormatter::money($table31['b']['sgst']) }}</td>
            </tr>
            <tr>
                <td>(c) Other Outward Supplies (nil rated, exempted)</td>
                <td class="text-right">₹ {{ \App\Services\PrintDataFormatter::money($table31['c']['taxable']) }}</td>
                <td class="text-right">₹ {{ \App\Services\PrintDataFormatter::money($table31['c']['igst']) }}</td>
                <td class="text-right">₹ {{ \App\Services\PrintDataFormatter::money($table31['c']['cgst']) }}</td>
                <td class="text-right">₹ {{ \App\Services\PrintDataFormatter::money($table31['c']['sgst']) }}</td>
            </tr>
            <tr>
                <td>(d) Inward Supplies (liable to reverse charge)</td>
                <td class="text-right">₹ {{ \App\Services\PrintDataFormatter::money($table31['d']['taxable']) }}</td>
                <td class="text-right">₹ {{ \App\Services\PrintDataFormatter::money($table31['d']['igst']) }}</td>
                <td class="text-right">₹ {{ \App\Services\PrintDataFormatter::money($table31['d']['cgst']) }}</td>
                <td class="text-right">₹ {{ \App\Services\PrintDataFormatter::money($table31['d']['sgst']) }}</td>
            </tr>
            <tr>
                <td>(e) Non-GST Outward Supplies</td>
                <td class="text-right">₹ {{ \App\Services\PrintDataFormatter::money($table31['e']['taxable']) }}</td>
                <td class="text-right">₹ {{ \App\Services\PrintDataFormatter::money($table31['e']['igst']) }}</td>
                <td class="text-right">₹ {{ \App\Services\PrintDataFormatter::money($table31['e']['cgst']) }}</td>
                <td class="text-right">₹ {{ \App\Services\PrintDataFormatter::money($table31['e']['sgst']) }}</td>
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
                <td class="text-right">₹ {{ \App\Services\PrintDataFormatter::money($table4['import_goods']['igst']) }}</td>
                <td class="text-right">₹ {{ \App\Services\PrintDataFormatter::money($table4['import_goods']['cgst']) }}</td>
                <td class="text-right">₹ {{ \App\Services\PrintDataFormatter::money($table4['import_goods']['sgst']) }}</td>
            </tr>
            <tr>
                <td style="padding-left: 20px;">(2) Import of services</td>
                <td class="text-right">₹ {{ \App\Services\PrintDataFormatter::money($table4['import_services']['igst']) }}</td>
                <td class="text-right">₹ {{ \App\Services\PrintDataFormatter::money($table4['import_services']['cgst']) }}</td>
                <td class="text-right">₹ {{ \App\Services\PrintDataFormatter::money($table4['import_services']['sgst']) }}</td>
            </tr>
            <tr>
                <td style="padding-left: 20px;">(3) Inward supplies liable to reverse charge (other than 1 & 2 above)</td>
                <td class="text-right">₹ {{ \App\Services\PrintDataFormatter::money($table4['reverse_charge']['igst']) }}</td>
                <td class="text-right">₹ {{ \App\Services\PrintDataFormatter::money($table4['reverse_charge']['cgst']) }}</td>
                <td class="text-right">₹ {{ \App\Services\PrintDataFormatter::money($table4['reverse_charge']['sgst']) }}</td>
            </tr>
            <tr>
                <td style="padding-left: 20px;">(4) Inward supplies from Input Service Distributor (ISD)</td>
                <td class="text-right">₹ {{ \App\Services\PrintDataFormatter::money($table4['isd_itc']['igst']) }}</td>
                <td class="text-right">₹ {{ \App\Services\PrintDataFormatter::money($table4['isd_itc']['cgst']) }}</td>
                <td class="text-right">₹ {{ \App\Services\PrintDataFormatter::money($table4['isd_itc']['sgst']) }}</td>
            </tr>
            <tr class="font-bold">
                <td style="padding-left: 20px;">(5) All other ITC (Purchase Bills Input Tax Credit)</td>
                <td class="text-right">₹ {{ \App\Services\PrintDataFormatter::money($table4['other_itc']['igst']) }}</td>
                <td class="text-right">₹ {{ \App\Services\PrintDataFormatter::money($table4['other_itc']['cgst']) }}</td>
                <td class="text-right">₹ {{ \App\Services\PrintDataFormatter::money($table4['other_itc']['sgst']) }}</td>
            </tr>
        </tbody>
    </table>
</body>
</html>
