<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Purchase Register Report</title>
    <style>
        {!! $css ?? $report_css ?? (file_exists(public_path('css/reports/purchase_register_pdf.css')) ? file_get_contents(public_path('css/reports/purchase_register_pdf.css')) : (file_exists(public_path('css/reports/report_pdf.css')) ? file_get_contents(public_path('css/reports/report_pdf.css')) : '')) !!}
    </style>
</head>
<body>

    <div class="title-container">
        <h2 class="report-title">Purchase Register Report</h2>
        <div class="filter-info">
            <strong>Period:</strong> {{ \Carbon\Carbon::parse($filters['from_date'] ?? $filters['start_date'] ?? $filters['start'] ?? now())->format('d-m-Y') }} to {{ \Carbon\Carbon::parse($filters['to_date'] ?? $filters['end_date'] ?? $filters['end'] ?? now())->format('d-m-Y') }} 
            &nbsp;|&nbsp; <strong>Generated At:</strong> {{ $generated_at }}
            &nbsp;|&nbsp; <strong>All amounts in INR (₹)</strong>
        </div>
    </div>

    <table class="data-table">
        <thead>
            <tr>
                <th style="width: 4%;">#</th>
                <th style="width: 10%;">Bill No</th>
                <th style="width: 9%;">Bill Date</th>
                <th style="width: 23%;">Supplier Name</th>
                <th style="width: 12%;">GSTIN</th>
                <th style="width: 16%;">Product</th>
                <th style="width: 7%;">Qty</th>
                <!-- <th style="width: 6%;">Rate (₹)</th> -->
                <th style="width: 9%;">Taxable Amt (₹)</th>
                <!-- <th style="width: 6%;">CGST (₹)</th>
                <th style="width: 6%;">SGST (₹)</th>
                <th style="width: 6%;">IGST (₹)</th> -->
                <!-- Dynamic tax rate columns -->
                <!-- @foreach($tax_columns ?? [] as $col)
                    <th>{{ $col['label'] }} (₹)</th>
                @endforeach -->
                <th style="width: 10%;">Net Amt (₹)</th>
            </tr>
        </thead>
        <tbody>
            @php
                $sumQty = 0;
                $sumTaxable = 0;
                $sumNet = 0;
            @endphp
            @forelse($items as $idx => $item)
                @php
                    $sumQty += $item['qty'];
                    $sumTaxable += $item['taxable_amount'];
                    $sumNet += $item['net_amount'];
                @endphp
                <tr>
                    <td class="text-center">{{ $idx + 1 }}</td>
                    <td class="font-bold">{{ $item['bill_no'] }}</td>
                    <td class="text-center">{{ \Carbon\Carbon::parse($item['bill_date'])->format('d-m-Y') }}</td>
                    <td class="font-bold">{{ $item['supplier_name'] }}</td>
                    <td class="text-center">{{ $item['gst_number'] ?: 'N/A' }}</td>
                    <td>{{ $item['product_name'] }}</td>
                    <td class="text-right font-bold">{{ number_format($item['qty'], 2) }}</td>
                    <!-- <td class="text-right">{{ number_format($item['purchase_rate'], 2) }}</td> -->
                    <td class="text-right">{{ number_format($item['taxable_amount'], 2) }}</td>
                    <!-- <td class="text-right">{{ number_format($item['cgst'], 2) }}</td>
                    <td class="text-right">{{ number_format($item['sgst'], 2) }}</td>
                    <td class="text-right">{{ number_format($item['igst'], 2) }}</td> -->
                    <!-- @foreach($tax_columns ?? [] as $col)
                        <td class="text-right">{{ number_format($item['taxes'][$col['key']] ?? 0, 2) }}</td>
                    @endforeach -->
                    <td class="text-right font-bold">{{ number_format($item['net_amount'], 2) }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="9" class="text-center" style="padding: 15px;">No records found for the selected period.</td>
                </tr>
            @endforelse

            @if(!empty($items))
                <tr class="total-row">
                    <td colspan="6" class="text-center font-bold">TOTAL PURCHASES</td>
                    <td class="text-right font-bold">{{ number_format($totals['qty'] ?? $sumQty, 2) }}</td>
                    <!-- <td></td> -->
                    <td class="text-right font-bold">{{ number_format($totals['taxable'] ?? $sumTaxable, 2) }}</td>
                    <!-- <td class="text-right font-bold">{{ number_format($totals['cgst'] ?? 0, 2) }}</td>
                    <td class="text-right font-bold">{{ number_format($totals['sgst'] ?? 0, 2) }}</td>
                    <td class="text-right font-bold">{{ number_format($totals['igst'] ?? 0, 2) }}</td> -->
                    <!-- @foreach($tax_columns ?? [] as $col)
                        @php
                            $colSum = collect($items)->sum(fn($it) => $it['taxes'][$col['key']] ?? 0);
                        @endphp
                        <td class="text-right font-bold">{{ number_format($colSum, 2) }}</td>
                    @endforeach -->
                    <td class="text-right font-bold">{{ number_format($totals['grand_total'] ?? $sumNet, 2) }}</td>
                </tr>
            @endif
        </tbody>
    </table>

</body>
</html>
