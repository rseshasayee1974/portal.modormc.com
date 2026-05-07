<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>PATRON STATEMENT</title>
    <style>
        body {
            font-family: 'Helvetica', 'Arial', sans-serif;
            font-size: 10pt;
            color: #1e293b;
            margin: 0;
            padding: 0;
        }
        .header {
            text-align: center;
            margin-bottom: 30px;
            border-bottom: 4px solid #4f46e5;
            padding-bottom: 10px;
        }
        .header h1 {
            font-size: 24pt;
            margin: 0;
            color: #4f46e5;
            text-transform: uppercase;
            letter-spacing: 3px;
        }
        .header p {
            margin: 5px 0;
            color: #334155;
            font-weight: 900;
            font-size: 14pt;
        }
        .period {
            font-size: 9pt;
            color: #64748b;
            text-transform: uppercase;
            letter-spacing: 1px;
        }
        table.data-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
        }
        table.data-table th {
            background-color: #f1f5f9;
            border-bottom: 2px solid #4f46e5;
            padding: 12px 10px;
            text-align: left;
            font-size: 8pt;
            text-transform: uppercase;
            letter-spacing: 1px;
            color: #4f46e5;
        }
        table.data-table td {
            padding: 10px;
            border-bottom: 1px solid #e2e8f0;
            font-size: 9pt;
            vertical-align: top;
        }
        .text-right { text-align: right; }
        .text-center { text-align: center; }
        .font-bold { font-weight: bold; }
        .dr-type { color: #059669; font-weight: 800; }
        .cr-type { color: #dc2626; font-weight: 800; }
        .opening-row { background-color: #f8fafc; font-weight: bold; }
        .footer {
            position: fixed;
            bottom: 0;
            width: 100%;
            font-size: 8pt;
            color: #94a3b8;
            text-align: right;
            padding: 10px;
        }
    </style>
</head>
<body>
    <div class="header">
        <h1>PATRON STATEMENT</h1>
        <p>{{ $target_name }}</p>
        <div class="period">Period: {{ date('d M Y', strtotime($start)) }} to {{ date('d M Y', strtotime($end)) }}</div>
    </div>

    <table class="data-table">
        <thead>
            <tr>
                <th width="12%">Date</th>
                <th>Transaction Details</th>
                <th width="15%">Ref No</th>
                <th width="15%" class="text-right">Amount</th>
                <th width="8%" class="text-center">DR/CR</th>
                <th width="15%" class="text-right">Balance</th>
            </tr>
        </thead>
        <tbody>
            @php $running_balance = $opening_balance; @endphp
            <tr class="opening-row">
                <td>{{ date('d-m-Y', strtotime($start)) }}</td>
                <td>Opening Balance Brought Forward</td>
                <td class="text-center">---</td>
                <td class="text-right">{{ number_format(abs($opening_balance), 2) }}</td>
                <td class="text-center">
                    <span class="{{ $opening_balance >= 0 ? 'dr-type' : 'cr-type' }}">
                        {{ $opening_balance >= 0 ? 'DR' : 'CR' }}
                    </span>
                </td>
                <td class="text-right">{{ number_format($running_balance, 2) }}</td>
            </tr>

            @foreach($transactions as $trx)
                @php 
                    $running_balance += ($trx['debit'] - $trx['credit']);
                @endphp
                <tr>
                    <td>{{ date('d-m-Y', strtotime($trx['date'])) }}</td>
                    <td>
                        <div class="font-bold">{{ $trx['narration'] }}</div>
                        <div style="font-size: 8pt; color: #64748b; margin-top: 3px;">Type: {{ $trx['voucher_type'] }}</div>
                    </td>
                    <td>{{ $trx['voucher_no'] }}</td>
                    <td class="text-right">{{ number_format($trx['amount'], 2) }}</td>
                    <td class="text-center">
                        <span class="{{ $trx['type'] == 'Dr' ? 'dr-type' : 'cr-type' }}">
                            {{ strtoupper($trx['type']) }}
                        </span>
                    </td>
                    <td class="text-right font-bold">{{ number_format(abs($running_balance), 2) }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <div class="footer">
        Page 1 of 1 | Generated on {{ date('d/m/Y H:i') }}
    </div>
</body>
</html>
