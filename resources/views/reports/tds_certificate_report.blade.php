<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>TDS Certificate Statement</title>
    <style>
        @page { margin: 40px; }
        body { font-family: 'DejaVu Sans', sans-serif; font-size: 9pt; color: #1e293b; line-height: 1.4; }
        .header-title { font-size: 14pt; font-weight: bold; text-align: center; color: #1e3a8a; text-transform: uppercase; margin-bottom: 25px; border-bottom: 2px double #1e3a8a; padding-bottom: 10px; }
        .details-table { width: 100%; border-collapse: collapse; margin-bottom: 25px; }
        .details-table td { border: 1px solid #cbd5e1; padding: 8px 10px; font-size: 8.5pt; width: 50%; vertical-align: top; }
        .details-title { font-weight: bold; color: #475569; text-transform: uppercase; font-size: 8pt; margin-bottom: 5px; display: block; }
        .party-name { font-weight: bold; font-size: 10pt; color: #0f172a; display: block; margin-bottom: 4px; }
        table.data-table { width: 100%; border-collapse: collapse; margin-top: 10px; border: 1px solid #cbd5e1; }
        table.data-table th { background-color: #f1f5f9; color: #0f172a; border: 1px solid #cbd5e1; padding: 8px; font-size: 8pt; font-weight: bold; text-align: center; }
        table.data-table td { padding: 8px; border: 1px solid #cbd5e1; font-size: 8pt; vertical-align: middle; }
        .total-row { background-color: #f8fafc; font-weight: bold; }
        .text-right { text-align: right; }
        .text-center { text-align: center; }
        .signature-section { margin-top: 60px; text-align: right; }
        .signature-line { border-top: 1px solid #94a3b8; display: inline-block; width: 200px; margin-top: 50px; text-align: center; font-size: 8.5pt; font-weight: bold; color: #475569; }
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
                <th>Taxable Value</th>
                <th>TDS Section</th>
                <th>TDS Rate</th>
                <th>TDS Amount</th>
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
                        <td class="text-right">₹ {{ number_format($row['taxable_amount'], 2) }}</td>
                        <td>{{ $row['tds_section'] }}</td>
                        <td class="text-center">{{ number_format($row['tds_rate'], 1) }} %</td>
                        <td class="text-right" style="font-weight: bold;">₹ {{ number_format($row['tds_amount'], 2) }}</td>
                    </tr>
                @endforeach
                <tr class="total-row">
                    <td colspan="4" class="text-right">Total:</td>
                    <td class="text-right">₹ {{ number_format(collect($transactions)->sum('taxable_amount'), 2) }}</td>
                    <td colspan="2"></td>
                    <td class="text-right">₹ {{ number_format(collect($transactions)->sum('tds_amount'), 2) }}</td>
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
