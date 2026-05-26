<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Sales Register Report</title>
    <style>
        @page {
            margin: 20px;
        }
        body {
            font-family: 'DejaVu Sans', sans-serif;
            font-size: 8pt;
            color: #1e293b;
            margin: 0;
            padding: 0;
            line-height: 1.2;
        }
        .title-container {
            border-bottom: 2px solid #1d2d3e;
            padding-bottom: 5px;
            margin-bottom: 10px;
        }
        .report-title {
            font-size: 12pt;
            font-weight: bold;
            color: #1d2d3e;
            margin: 0;
        }
        .filter-info {
            font-size: 8pt;
            color: #475569;
            margin-top: 3px;
        }
        table.data-table {
            width: 100%;
            border-collapse: collapse;
            border: 1px solid #cbd5e1;
        }
        table.data-table th {
            background-color: #f2f4f7;
            color: #1d2d3e;
            border: 1px solid #cbd5e1;
            padding: 5px 3px;
            font-size: 7pt;
            font-weight: bold;
            text-align: center;
            text-transform: uppercase;
        }
        table.data-table td {
            padding: 4px 5px;
            border: 1px solid #cbd5e1;
            font-size: 7.5pt;
            vertical-align: middle;
        }
        .total-row {
            background-color: #e2e8f0;
            font-weight: bold;
        }
        .text-right { text-align: right; }
        .text-center { text-align: center; }
        .font-bold { font-weight: bold; }
    </style>
</head>
<body>

    <div class="title-container">
        <h2 class="report-title">Sales Register Report</h2>
        <div class="filter-info">
            <strong>Period:</strong> {{ \Carbon\Carbon::parse($filters['from_date'])->format('d-m-Y') }} to {{ \Carbon\Carbon::parse($filters['to_date'])->format('d-m-Y') }} 
            &nbsp;|&nbsp; <strong>Generated At:</strong> {{ $generated_at }}
        </div>
    </div>

    <table class="data-table">
        <thead>
            <tr>
                <th>Inv No</th>
                <th>Inv Date</th>
                <th>Customer Name</th>
                <th>GSTIN</th>
                <th>Product Name</th>
                <th>Qty</th>
                <th>Rate</th>
                <th>Taxable Amt</th>
                <th>CGST</th>
                <th>SGST</th>
                <th>IGST</th>
                @foreach($tax_columns as $col)
                    <th>{{ $col['label'] }}</th>
                @endforeach
                <th>Net Amount</th>
            </tr>
        </thead>
        <tbody>
            @php
                $sumQty = 0;
                $sumTaxable = 0;
                $sumCgst = 0;
                $sumSgst = 0;
                $sumIgst = 0;
                $sumNet = 0;
            @endphp
            @forelse($items as $item)
                @php
                    $sumQty += $item['qty'];
                    $sumTaxable += $item['taxable_amount'];
                    $sumCgst += $item['cgst'];
                    $sumSgst += $item['sgst'];
                    $sumIgst += $item['igst'];
                    $sumNet += $item['net_amount'];
                @endphp
                <tr>
                    <td class="text-center font-bold">{{ $item['invoice_no'] }}</td>
                    <td class="text-center" style="white-space: nowrap;">{{ \Carbon\Carbon::parse($item['invoice_date'])->format('d-m-Y') }}</td>
                    <td>{{ $item['customer_name'] }}</td>
                    <td class="text-center">{{ $item['gst_number'] ?: 'N/A' }}</td>
                    <td>{{ $item['product_name'] }}</td>
                    <td class="text-right">{{ number_format($item['qty'], 2) }}</td>
                    <td class="text-right">₹{{ number_format($item['rate'], 2) }}</td>
                    <td class="text-right">₹{{ number_format($item['taxable_amount'], 2) }}</td>
                    <td class="text-right">₹{{ number_format($item['cgst'], 2) }}</td>
                    <td class="text-right">₹{{ number_format($item['sgst'], 2) }}</td>
                    <td class="text-right">₹{{ number_format($item['igst'], 2) }}</td>
                    @foreach($tax_columns as $col)
                        <td class="text-right">₹{{ number_format($item['taxes'][$col['key']] ?? 0, 2) }}</td>
                    @endforeach
                    <td class="text-right font-bold">₹{{ number_format($item['net_amount'], 2) }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="{{ 12 + count($tax_columns) }}" class="text-center" style="padding: 15px;">No records found for the selected period.</td>
                </tr>
            @endforelse

            @if(!empty($items))
                <tr class="total-row">
                    <td colspan="5" class="text-center font-bold">TOTAL</td>
                    <td class="text-right font-bold">{{ number_format($sumQty, 2) }}</td>
                    <td></td>
                    <td class="text-right font-bold">₹{{ number_format($sumTaxable, 2) }}</td>
                    <td class="text-right font-bold">₹{{ number_format($sumCgst, 2) }}</td>
                    <td class="text-right font-bold">₹{{ number_format($sumSgst, 2) }}</td>
                    <td class="text-right font-bold">₹{{ number_format($sumIgst, 2) }}</td>
                    @foreach($tax_columns as $col)
                        @php
                            $colSum = collect($items)->sum(fn($it) => $it['taxes'][$col['key']] ?? 0);
                        @endphp
                        <td class="text-right font-bold">₹{{ number_format($colSum, 2) }}</td>
                    @endforeach
                    <td class="text-right font-bold">₹{{ number_format($sumNet, 2) }}</td>
                </tr>
            @endif
        </tbody>
    </table>

</body>
</html>
