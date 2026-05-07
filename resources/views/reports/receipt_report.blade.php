<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>RECEIPT VOUCHER LIST</title>
    <style>
        body { font-family: 'Helvetica', sans-serif; font-size: 10pt; color: #1e293b; margin: 0; padding: 0; }
        .header { text-align: center; margin-bottom: 30px; border-bottom: 4px solid #10b981; padding-bottom: 10px; }
        .header h1 { font-size: 24pt; margin: 0; color: #10b981; text-transform: uppercase; letter-spacing: 3px; }
        .header p { margin: 5px 0; color: #334155; font-weight: 900; font-size: 14pt; }
        table.data-table { width: 100%; border-collapse: collapse; margin-top: 20px; }
        table.data-table th { background-color: #ecfdf5; border-bottom: 2px solid #10b981; padding: 12px 10px; text-align: left; font-size: 8pt; text-transform: uppercase; color: #065f46; }
        table.data-table td { padding: 10px; border-bottom: 1px solid #d1fae5; font-size: 9pt; }
        .text-right { text-align: right; }
        .text-center { text-align: center; }
        .font-bold { font-weight: bold; }
        .footer { position: fixed; bottom: 0; width: 100%; font-size: 8pt; color: #94a3b8; text-align: right; padding: 10px; }
    </style>
</head>
<body>
    <div class="header">
        <h1>RECEIPT VOUCHERS</h1>
        <p>{{ $target_name }}</p>
        <div style="font-size: 9pt; color: #64748b;">Period: {{ date('d M Y', strtotime($start)) }} to {{ date('d M Y', strtotime($end)) }}</div>
    </div>
    <table class="data-table">
        <thead>
            <tr>
                <th width="12%">Date</th>
                <th>Received From / Description</th>
                <th width="15%">Voucher No</th>
                <th width="15%" class="text-right">Recd Amt</th>
                <th width="15%" class="text-right">Running Total</th>
            </tr>
        </thead>
        <tbody>
            @php $total = 0; @endphp
            @foreach($transactions as $trx)
                @php $total += $trx['amount']; @endphp
                <tr>
                    <td>{{ date('d-m-Y', strtotime($trx['date'])) }}</td>
                    <td>{{ $trx['narration'] }}</td>
                    <td>{{ $trx['voucher_no'] }}</td>
                    <td class="text-right font-bold">{{ number_format($trx['amount'], 2) }}</td>
                    <td class="text-right font-bold">{{ number_format($total, 2) }}</td>
                </tr>
            @endforeach
        </tbody>
        <tfoot>
            <tr style="background-color: #ecfdf5;">
                <td colspan="3" class="text-right font-bold" style="padding: 12px;">TOTAL RECEIPTS:</td>
                <td class="text-right font-bold" style="padding: 12px;">{{ number_format($total, 2) }}</td>
                <td class="text-right font-bold" style="padding: 12px;">{{ number_format($total, 2) }}</td>
            </tr>
        </tfoot>
    </table>
    <div class="footer">Generated on {{ date('d/m/Y H:i') }}</div>
</body>
</html>
