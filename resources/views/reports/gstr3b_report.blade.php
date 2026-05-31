<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>GSTR-3B Summary Report</title>
    <style>
        @page { margin: 35px; }
        body { font-family: 'DejaVu Sans', sans-serif; font-size: 8.5pt; color: #1e293b; line-height: 1.35; }
        .header-table { width: 100%; border-collapse: collapse; margin-bottom: 20px; }
        .header-table td { padding: 0; vertical-align: top; }
        .address-box { font-size: 8.5pt; color: #334155; }
        .address-title { font-weight: bold; color: #64748b; font-size: 8.5pt; text-transform: uppercase; }
        .address-name { font-weight: bold; font-size: 11pt; color: #0f172a; display: block; margin-bottom: 2px; }
        .title-container { border-bottom: 2px solid #0284c7; padding-bottom: 5px; margin-bottom: 15px; }
        .title { font-size: 13pt; font-weight: bold; color: #0c4a6e; margin: 0; }
        .table-section-title { background-color: #f1f5f9; color: #0f172a; font-weight: bold; padding: 6px 8px; font-size: 9pt; margin-top: 15px; margin-bottom: 8px; border: 1px solid #cbd5e1; border-bottom: 0; }
        table.data-table { width: 100%; border-collapse: collapse; margin-bottom: 15px; border: 1px solid #cbd5e1; }
        table.data-table th { background-color: #f8fafc; color: #0f172a; border: 1px solid #cbd5e1; padding: 6px 5px; font-size: 8pt; font-weight: bold; text-align: center; }
        table.data-table td { padding: 6px 8px; border: 1px solid #cbd5e1; font-size: 8pt; vertical-align: middle; }
        .font-bold { font-weight: bold; }
        .text-right { text-align: right; }
        .text-center { text-align: center; }
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
