<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>TDS Certificate Statement</title>
    <style>
        {!! $css ?? $report_css ?? (file_exists(public_path('css/reports/tds_certificate_report.css')) ? file_get_contents(public_path('css/reports/tds_certificate_report.css')) : (file_exists(public_path('css/reports/report_pdf.css')) ? file_get_contents(public_path('css/reports/report_pdf.css')) : '')) !!}
    </style>
</head>
<body>
    <div class="header-title">TDS Deduction & Certificate Statement (Period: {{ $start }} to {{ $end }})</div>

    <table class="details-table">
        <tr>
            <td>
                <span class="details-title">Deductor Details (Source)</span>
                <span class="party-name">{{ $deductor['name'] }}</span>
                <strong>PAN:</strong> {{ $deductor['pan'] }}<br>
                <strong>GSTIN:</strong> {{ $deductor['gstin'] }}<br>
                <strong>Address:</strong> {{ $deductor['address'] }}
            </td>
            <td>
                <span class="details-title">Deductee Details (Recipient)</span>
                <span class="party-name">{{ $deductee['name'] }}</span>
                <strong>PAN:</strong> {{ $deductee['pan'] }}<br>
                <strong>GSTIN:</strong> {{ $deductee['gstin'] }}<br>
                <strong>Address:</strong> {{ $deductee['address'] }}
            </td>
        </tr>
    </table>

    <table class="data-table">
        <thead>
            <tr>
                <th width="5%">#</th>
                <th>Date</th>
                <th>Document No</th>
                <th>Doc Type</th>
                <th>Taxable Value (₹)</th>
                <th>TDS Section</th>
                <th>TDS Rate</th>
                <th>TDS Amount (₹)</th>
            </tr>
        </thead>
        <tbody>
            @if(count($transactions) > 0)
                @foreach($transactions as $index => $row)
                    <tr>
                        <td class="text-center">{{ $index + 1 }}</td>
                        <td class="text-center">{{ $row['date'] }}</td>
                        <td>{{ $row['doc_no'] }}</td>
                        <td>{{ $row['doc_type'] }}</td>
                        <td class="text-right">{{ number_format($row['taxable_amount'], 2) }}</td>
                        <td>{{ $row['tds_section'] }}</td>
                        <td class="text-center">{{ number_format($row['tds_rate'], 1) }} %</td>
                        <td class="text-right" style="font-weight: bold;">{{ number_format($row['tds_amount'], 2) }}</td>
                    </tr>
                @endforeach
                <tr class="total-row">
                    <td colspan="4" class="text-right">Total:</td>
                    <td class="text-right">{{ number_format(collect($transactions)->sum('taxable_amount'), 2) }}</td>
                    <td colspan="2"></td>
                    <td class="text-right">{{ number_format(collect($transactions)->sum('tds_amount'), 2) }}</td>
                </tr>
            @else
                <tr>
                    <td colspan="8" class="text-center text-slate-400 italic">No TDS transactions recorded for this period.</td>
                </tr>
            @endif
        </tbody>
    </table>

    <div class="signature-section">
        <div class="signature-line">
            Authorized Signatory<br>
            {{ $deductor['name'] }}
        </div>
    </div>
</body>
</html>
