<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>{{ strtoupper($type) }} REPORT</title>
    <style>
        body {
            font-family: 'Helvetica', 'Arial', sans-serif;
            font-size: 10pt;
            color: #334155;
            margin: 0;
            padding: 0;
        }
        .header {
            text-align: center;
            margin-bottom: 30px;
            border-bottom: 2px solid #1e293b;
            padding-bottom: 10px;
        }
        .header h1 {
            font-size: 20pt;
            margin: 0;
            color: #1e293b;
            text-transform: uppercase;
            letter-spacing: 2px;
        }
        .header p {
            margin: 5px 0;
            color: #64748b;
            font-weight: bold;
        }
        table.data-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
        }
        table.data-table th {
            background-color: #f8fafc;
            border-bottom: 2px solid #e2e8f0;
            padding: 10px;
            text-align: left;
            font-size: 9pt;
            text-transform: uppercase;
            letter-spacing: 1px;
        }
        table.data-table td {
            padding: 8px 10px;
            border-bottom: 1px solid #f1f5f9;
            font-size: 9pt;
        }
        .text-right { text-align: right; }
        .text-center { text-align: center; }
        .font-bold { font-weight: bold; }
        .dr-type { color: #10b981; }
        .cr-type { color: #ef4444; }
        .opening-row { background-color: #f1f5f9; }
    </style>
</head>
<body>
    <div class="header">
        <h1>{{ $type }} STATEMENT</h1>
        <p>{{ $target_name }}</p>
        <p style="font-size: 9pt; color: #94a3b8;">Period: {{ $start }} to {{ $end }}</p>
    </div>

    <table class="data-table">
        <thead>
            <tr>
                <th width="15%">Date</th>
                <th>Particulars</th>
                <th width="15%">Voucher No</th>
                <th width="15%" class="text-right">Amount</th>
                <th width="8%" class="text-center">Type</th>
                <th width="15%" class="text-right">Balance</th>
            </tr>
        </thead>
        <tbody>
            @php $running_balance = $opening_balance; @endphp
            <tr class="opening-row">
                <td>{{ $start }}</td>
                <td class="font-bold">Opening Balance</td>
                <td class="text-center">---</td>
                <td class="text-right">{{ number_format(abs($opening_balance), 2) }}</td>
                <td class="text-center">
                    <span class="{{ $opening_balance >= 0 ? 'dr-type' : 'cr-type' }}">
                        {{ $opening_balance >= 0 ? 'DR' : 'CR' }}
                    </span>
                </td>
                <td class="text-right font-bold">{{ number_format($opening_balance, 2) }}</td>
            </tr>

            @foreach($transactions as $trx)
                @php 
                    $running_balance += ($trx['debit'] - $trx['credit']);
                @endphp
                <tr>
                    <td>{{ $trx['date'] }}</td>
                    <td>
                        <div>{{ $trx['narration'] }}</div>
                        <div style="font-size: 7pt; color: #94a3b8; margin-top: 2px;">{{ $trx['voucher_type'] }}</div>
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
</body>
</html>
