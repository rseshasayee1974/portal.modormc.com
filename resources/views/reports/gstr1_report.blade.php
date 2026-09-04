<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>GSTR-1 Report</title>
    <style>
        {!! $css ?? $report_css ?? (file_exists(public_path('css/reports/gstr1_report.css')) ? file_get_contents(public_path('css/reports/gstr1_report.css')) : (file_exists(public_path('css/reports/report_pdf.css')) ? file_get_contents(public_path('css/reports/report_pdf.css')) : '')) !!}
    </style>
</head>
<body>
    <table class="header-table">
        <tr>
            <td style="width: 50%;">
                <div class="address-box">
                    <span class="address-title">GST Compliance Report</span>
                    <span class="address-name">GSTR-1 Outward Supplies</span>
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
        <h2 class="title">GSTR-1 Return (Period: {{ $start }} to {{ $end }})</h2>
    </div>

    <!-- B2B Section -->
    <div class="section-header">B2B Supplies (Sales to Registered Taxpayers)</div>
    <table class="data-table">
        <thead>
            <tr>
                <th width="4%">#</th>
                <th>GSTIN</th>
                <th>Customer Name</th>
                <th>Invoice No</th>
                <th>Date</th>
                <th>Invoice Value</th>
                <th>Taxable Value</th>
                <th>CGST</th>
                <th>SGST</th>
                <th>IGST</th>
                <th>POS</th>
            </tr>
        </thead>
        <tbody>
            @if(count($b2b) > 0)
                @foreach($b2b as $index => $row)
                    <tr>
                        <td class="text-center">{{ $index + 1 }}</td>
                        <td>{{ $row['gstin'] }}</td>
                        <td>{{ $row['customer_name'] }}</td>
                        <td>{{ $row['invoice_no'] }}</td>
                        <td class="text-center">{{ $row['invoice_date'] }}</td>
                        <td class="text-right">₹ {{ number_format($row['invoice_value'], 2) }}</td>
                        <td class="text-right">₹ {{ number_format($row['taxable_value'], 2) }}</td>
                        <td class="text-right">₹ {{ number_format($row['cgst'], 2) }}</td>
                        <td class="text-right">₹ {{ number_format($row['sgst'], 2) }}</td>
                        <td class="text-right">₹ {{ number_format($row['igst'], 2) }}</td>
                        <td class="text-center">{{ $row['place_of_supply'] }}</td>
                    </tr>
                @endforeach
                <tr class="total-row">
                    <td colspan="5" class="text-right">Total:</td>
                    <td class="text-right">₹ {{ number_format(collect($b2b)->sum('invoice_value'), 2) }}</td>
                    <td class="text-right">₹ {{ number_format(collect($b2b)->sum('taxable_value'), 2) }}</td>
                    <td class="text-right">₹ {{ number_format(collect($b2b)->sum('cgst'), 2) }}</td>
                    <td class="text-right">₹ {{ number_format(collect($b2b)->sum('sgst'), 2) }}</td>
                    <td class="text-right">₹ {{ number_format(collect($b2b)->sum('igst'), 2) }}</td>
                    <td></td>
                </tr>
            @else
                <tr><td colspan="11" class="text-center">No B2B Supplies found.</td></tr>
            @endif
        </tbody>
    </table>

    <!-- B2C Section -->
    <div class="section-header">B2C Supplies (Sales to Unregistered Customers)</div>
    <table class="data-table">
        <thead>
            <tr>
                <th width="4%">#</th>
                <th>Invoice No</th>
                <th>Date</th>
                <th>Invoice Value</th>
                <th>Taxable Value</th>
                <th>CGST</th>
                <th>SGST</th>
                <th>IGST</th>
                <th>POS</th>
            </tr>
        </thead>
        <tbody>
            @if(count($b2c) > 0)
                @foreach($b2c as $index => $row)
                    <tr>
                        <td class="text-center">{{ $index + 1 }}</td>
                        <td>{{ $row['invoice_no'] }}</td>
                        <td class="text-center">{{ $row['invoice_date'] }}</td>
                        <td class="text-right">₹ {{ number_format($row['invoice_value'], 2) }}</td>
                        <td class="text-right">₹ {{ number_format($row['taxable_value'], 2) }}</td>
                        <td class="text-right">₹ {{ number_format($row['cgst'], 2) }}</td>
                        <td class="text-right">₹ {{ number_format($row['sgst'], 2) }}</td>
                        <td class="text-right">₹ {{ number_format($row['igst'], 2) }}</td>
                        <td class="text-center">{{ $row['place_of_supply'] }}</td>
                    </tr>
                @endforeach
                <tr class="total-row">
                    <td colspan="3" class="text-right">Total:</td>
                    <td class="text-right">₹ {{ number_format(collect($b2c)->sum('invoice_value'), 2) }}</td>
                    <td class="text-right">₹ {{ number_format(collect($b2c)->sum('taxable_value'), 2) }}</td>
                    <td class="text-right">₹ {{ number_format(collect($b2c)->sum('cgst'), 2) }}</td>
                    <td class="text-right">₹ {{ number_format(collect($b2c)->sum('sgst'), 2) }}</td>
                    <td class="text-right">₹ {{ number_format(collect($b2c)->sum('igst'), 2) }}</td>
                    <td></td>
                </tr>
            @else
                <tr><td colspan="9" class="text-center">No B2C Supplies found.</td></tr>
            @endif
        </tbody>
    </table>

    <!-- CDNR Section -->
    <div class="section-header">CDNR (Credit & Debit Notes Registered)</div>
    <table class="data-table">
        <thead>
            <tr>
                <th width="4%">#</th>
                <th>GSTIN</th>
                <th>Customer Name</th>
                <th>Note No</th>
                <th>Date</th>
                <th>Type</th>
                <th>Note Value</th>
                <th>Taxable Value</th>
                <th>CGST</th>
                <th>SGST</th>
                <th>IGST</th>
            </tr>
        </thead>
        <tbody>
            @if(count($cdnr) > 0)
                @foreach($cdnr as $index => $row)
                    <tr>
                        <td class="text-center">{{ $index + 1 }}</td>
                        <td>{{ $row['gstin'] }}</td>
                        <td>{{ $row['customer_name'] }}</td>
                        <td>{{ $row['note_no'] }}</td>
                        <td class="text-center">{{ $row['note_date'] }}</td>
                        <td class="text-center">{{ $row['note_type'] }}</td>
                        <td class="text-right">₹ {{ number_format($row['note_value'], 2) }}</td>
                        <td class="text-right">₹ {{ number_format($row['taxable_value'], 2) }}</td>
                        <td class="text-right">₹ {{ number_format($row['cgst'], 2) }}</td>
                        <td class="text-right">₹ {{ number_format($row['sgst'], 2) }}</td>
                        <td class="text-right">₹ {{ number_format($row['igst'], 2) }}</td>
                    </tr>
                @endforeach
                <tr class="total-row">
                    <td colspan="6" class="text-right">Total:</td>
                    <td class="text-right">₹ {{ number_format(collect($cdnr)->sum('note_value'), 2) }}</td>
                    <td class="text-right">₹ {{ number_format(collect($cdnr)->sum('taxable_value'), 2) }}</td>
                    <td class="text-right">₹ {{ number_format(collect($cdnr)->sum('cgst'), 2) }}</td>
                    <td class="text-right">₹ {{ number_format(collect($cdnr)->sum('sgst'), 2) }}</td>
                    <td class="text-right">₹ {{ number_format(collect($cdnr)->sum('igst'), 2) }}</td>
                </tr>
            @else
                <tr><td colspan="11" class="text-center">No Credit/Debit Notes found.</td></tr>
            @endif
        </tbody>
    </table>

    <!-- EXP Section -->
    <div class="section-header">EXP (Export Supplies)</div>
    <table class="data-table">
        <thead>
            <tr>
                <th width="4%">#</th>
                <th>Export Type</th>
                <th>Invoice No</th>
                <th>Date</th>
                <th>Invoice Value</th>
                <th>Taxable Value</th>
                <th>IGST</th>
            </tr>
        </thead>
        <tbody>
            @if(count($exp) > 0)
                @foreach($exp as $index => $row)
                    <tr>
                        <td class="text-center">{{ $index + 1 }}</td>
                        <td class="text-center">{{ $row['export_type'] }}</td>
                        <td>{{ $row['invoice_no'] }}</td>
                        <td class="text-center">{{ $row['invoice_date'] }}</td>
                        <td class="text-right">₹ {{ number_format($row['invoice_value'], 2) }}</td>
                        <td class="text-right">₹ {{ number_format($row['taxable_value'], 2) }}</td>
                        <td class="text-right">₹ {{ number_format($row['igst'], 2) }}</td>
                    </tr>
                @endforeach
                <tr class="total-row">
                    <td colspan="4" class="text-right">Total:</td>
                    <td class="text-right">₹ {{ number_format(collect($exp)->sum('invoice_value'), 2) }}</td>
                    <td class="text-right">₹ {{ number_format(collect($exp)->sum('taxable_value'), 2) }}</td>
                    <td class="text-right">₹ {{ number_format(collect($exp)->sum('igst'), 2) }}</td>
                </tr>
            @else
                <tr><td colspan="7" class="text-center">No Export Supplies found.</td></tr>
            @endif
        </tbody>
    </table>
</body>
</html>
