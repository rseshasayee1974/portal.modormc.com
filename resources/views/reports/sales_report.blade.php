<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>SALES REPORT</title>
    <style>
        body { font-family: 'Helvetica', sans-serif; font-size: 10pt; color: #1e293b; margin: 0; padding: 0; }
        .header { text-align: center; margin-bottom: 30px; border-bottom: 4px solid #f59e0b; padding-bottom: 10px; }
        .header h1 { font-size: 24pt; margin: 0; color: #f59e0b; text-transform: uppercase; letter-spacing: 3px; }
        .header p { margin: 5px 0; color: #334155; font-weight: 900; font-size: 14pt; }
        table.data-table { width: 100%; border-collapse: collapse; margin-top: 20px; }
        table.data-table th { background-color: #fffbeb; border-bottom: 2px solid #f59e0b; padding: 12px 10px; text-align: left; font-size: 8pt; text-transform: uppercase; color: #92400e; }
        table.data-table td { padding: 10px; border-bottom: 1px solid #fef3c7; font-size: 9pt; }
        .text-right { text-align: right; }
        .text-center { text-align: center; }
        .font-bold { font-weight: bold; }
        .footer { position: fixed; bottom: 0; width: 100%; font-size: 8pt; color: #94a3b8; text-align: right; padding: 10px; }
    </style>
</head>
<body>
    <div class="header">
        <h1>SALES REPORT</h1>
        <p>{{ $target_name }}</p>
        <div style="font-size: 9pt; color: #64748b;">Period: {{ date('d M Y', strtotime($start)) }} to {{ date('d M Y', strtotime($end)) }}</div>
    </div>
    <table class="data-table">
        <thead>
            <tr>
                <th width="12%">Date</th>
                <th>Description</th>
                <th width="15%">Invoice No</th>
                <th width="15%" class="text-right">Sales Amount</th>
                <th width="8%" class="text-center">Status</th>
                <th width="15%" class="text-right">Total</th>
            </tr>
        </thead>
        <tbody>
            @php $total = 0; @endphp
            @foreach($transactions as $trx)
                @php $total += $trx['credit']; @endphp
                <tr>
                    <td>{{ date('d-m-Y', strtotime($trx['date'])) }}</td>
                    <td>{{ $trx['narration'] }}</td>
                    <td>{{ $trx['voucher_no'] }}</td>
                    <td class="text-right font-bold">{{ number_format($trx['credit'], 2) }}</td>
                    <td class="text-center"><span style="color: #059669; font-weight: bold;">SALES</span></td>
                    <td class="text-right font-bold">{{ number_format($total, 2) }}</td>
                </tr>
            @endforeach
        </tbody>
        <tfoot>
            <tr style="background-color: #fffbeb;">
                <td colspan="3" class="text-right font-bold" style="padding: 12px;">GRAND TOTAL:</td>
                <td class="text-right font-bold" style="padding: 12px;">{{ number_format($total, 2) }}</td>
                <td></td>
                <td class="text-right font-bold" style="padding: 12px;">{{ number_format($total, 2) }}</td>
            </tr>
        </tfoot>
    </table>
    <div class="footer">Generated on {{ date('d/m/Y H:i') }}</div>
</body>
</html>
