<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>{{ $title }}</title>
    <style>
        @page {
            size: A4 landscape;
            margin: 6mm 5mm 6mm 5mm;
        }
        body {
            font-family: DejaVu Sans, sans-serif;
            font-size: 7px;
            color: #1e293b;
            margin: 0;
            padding: 0;
        }
        .header-container {
            margin-bottom: 6px;
            border-bottom: 1.5px solid #cbd5e1;
            padding-bottom: 4px;
        }
        .header-title {
            font-size: 13px;
            font-weight: bold;
            color: #0f172a;
            margin: 0 0 3px 0;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        .meta-line {
            font-size: 7px;
            color: #475569;
        }
        .meta-line span {
            font-weight: 600;
            color: #0f172a;
        }
        .note-line {
            font-size: 6.5px;
            color: #64748b;
            margin-top: 2px;
        }
        table.report-table {
            width: 100%;
            border-collapse: collapse;
            table-layout: fixed;
        }
        table.report-table thead {
            display: table-header-group;
        }
        table.report-table th {
            background-color: #e2e8f0;
            color: #0f172a;
            font-size: 6.5px;
            font-weight: bold;
            text-transform: uppercase;
            border: 0.5px solid #94a3b8;
            padding: 3.5px 2px;
            vertical-align: middle;
            overflow-wrap: break-word;
            word-wrap: break-word;
        }
        table.report-table td {
            border: 0.5px solid #cbd5e1;
            padding: 3px 2px;
            font-size: 6.5px;
            vertical-align: middle;
            overflow-wrap: break-word;
            word-wrap: break-word;
            color: #1e293b;
        }
        table.report-table tr:nth-child(even) td {
            background-color: #f8fafc;
        }
        table.report-table tr {
            page-break-inside: avoid;
        }
        .text-left { text-align: left; }
        .text-center { text-align: center; }
        .text-right { text-align: right; }
        td.text-right {
            white-space: nowrap;
        }
        .total-row td {
            font-weight: bold;
            background-color: #e2e8f0 !important;
            color: #0f172a;
            border-top: 1.5px solid #64748b;
            border-bottom: 1.5px solid #64748b;
            font-size: 6.5px;
        }
        .empty-msg {
            text-align: center;
            padding: 20px;
            font-size: 8px;
            color: #64748b;
        }
    </style>
</head>
<body>
    @php
        $isSales = ($report['report_type'] ?? '') === 'sales_register' || str_contains(strtolower($title ?? ''), 'sales');
        $isDetail = ($report['register_view'] ?? 'summary') === 'detail';

        $hasTcs = !empty($report['totals']['tcs']) && (float)$report['totals']['tcs'] != 0;
        $hasTruck = $isDetail && collect($report['data'] ?? [])->contains(fn($r) => !empty($r['truck']));

        // Build single-row columns for Landscape A4
        $cols = [];
        $cols[] = ['key' => 'index', 'label' => '#', 'align' => 'text-center', 'w' => 2.5];
        $cols[] = ['key' => $isSales ? 'invoice_date' : 'bill_date', 'label' => 'Date', 'format' => 'date', 'align' => 'text-center', 'w' => 5.5];
        $cols[] = ['key' => $isSales ? 'invoice_no' : 'bill_no', 'label' => $isSales ? 'Invoice No' : 'Bill No', 'align' => 'text-left', 'w' => $isDetail ? 7.5 : 9];

        if (!$isDetail) {
            $cols[] = ['key' => $isSales ? 'bill_no' : 'po_number', 'label' => $isSales ? 'Bill No' : 'PO No', 'align' => 'text-left', 'w' => 8];
            $cols[] = ['key' => $isSales ? 'customer_name' : 'supplier_name', 'label' => $isSales ? 'Customer Name' : 'Supplier Name', 'align' => 'text-left', 'w' => 19];
            $cols[] = ['key' => 'gst_number', 'label' => 'GSTIN', 'align' => 'text-left', 'w' => 9];
        } else {
            $cols[] = ['key' => $isSales ? 'customer_name' : 'supplier_name', 'label' => $isSales ? 'Customer Name' : 'Supplier Name', 'align' => 'text-left', 'w' => $hasTruck ? 11 : 13];
            $cols[] = ['key' => 'gst_number', 'label' => 'GSTIN', 'align' => 'text-left', 'w' => 8.5];
            if ($hasTruck) {
                $cols[] = ['key' => 'truck', 'label' => 'Vehicle', 'align' => 'text-left', 'w' => 6];
            }
            $cols[] = ['key' => 'product_name', 'label' => 'Product', 'align' => 'text-left', 'w' => $hasTruck ? 10 : 12];
            $cols[] = ['key' => 'hsn_code', 'label' => 'HSN', 'align' => 'text-center', 'w' => 4.5];
            $cols[] = ['key' => 'qty', 'label' => 'Qty', 'format' => 'number', 'total' => 'qty', 'align' => 'text-right', 'w' => 4.5];
            $cols[] = ['key' => 'unit', 'label' => 'UOM', 'align' => 'text-center', 'w' => 3];
            $cols[] = ['key' => $isSales ? 'rate' : 'purchase_rate', 'label' => 'Rate', 'format' => 'number', 'align' => 'text-right', 'w' => 4.5];
        }

        $cols[] = ['key' => 'taxable_amount', 'label' => 'Taxable Amt', 'format' => 'number', 'total' => 'taxable', 'align' => 'text-right', 'w' => 7.5];
        $cols[] = ['key' => 'cgst', 'label' => 'CGST', 'format' => 'number', 'total' => 'cgst', 'align' => 'text-right', 'w' => 5];
        $cols[] = ['key' => 'sgst_combined', 'label' => 'SGST', 'format' => 'number', 'total' => 'sgst_combined', 'align' => 'text-right', 'w' => 5];
        $cols[] = ['key' => 'igst', 'label' => 'IGST', 'format' => 'number', 'total' => 'igst', 'align' => 'text-right', 'w' => 5];

        if ($hasTcs) {
            $cols[] = ['key' => 'tcs', 'label' => 'TCS', 'format' => 'number', 'total' => 'tcs', 'align' => 'text-right', 'w' => 4.5];
        }

        $cols[] = ['key' => 'tax_amount', 'label' => 'Total Tax', 'format' => 'number', 'total' => 'gst', 'align' => 'text-right', 'w' => 5.5];
        $cols[] = ['key' => 'net_amount', 'label' => 'Net Amount', 'format' => 'number', 'total' => 'grand_total', 'align' => 'text-right', 'w' => 7.5];

        // Compute normalized percentage widths summing to 100%
        $wSum = array_sum(array_column($cols, 'w'));
        foreach ($cols as &$c) {
            $c['width_pct'] = round(($c['w'] / $wSum) * 100, 2);
        }
        unset($c);

        $totals = $report['totals'] ?? [];
        $totals['sgst_combined'] = (float)($totals['sgst'] ?? 0) + (float)($totals['utgst'] ?? 0);
    @endphp

    <div class="header-container">
        <div class="header-title">{{ $title }} - {{ ucfirst($report['register_view'] ?? 'Summary') }}</div>
        <div class="meta-line">
            Period: <span>{{ $filters['from_date'] ?? ($filters['start_date'] ?? '') }} to {{ $filters['to_date'] ?? ($filters['end_date'] ?? '') }}</span>
            &nbsp; | &nbsp; Generated: <span>{{ $generated_at ?? now()->format('d-m-Y H:i:s') }}</span>
            &nbsp; | &nbsp; Currency: <span>INR</span>
        </div>
        @if(!empty($report['note']))
            <div class="note-line">{{ $report['note'] }}</div>
        @endif
    </div>

    <table class="report-table">
        <thead>
            <tr>
                @foreach($cols as $col)
                    <th class="{{ $col['align'] }}" style="width: {{ $col['width_pct'] }}%;">{{ $col['label'] }}</th>
                @endforeach
            </tr>
        </thead>
        <tbody>
            @forelse($report['data'] ?? [] as $index => $row)
                @php
                    $sgstCombined = (float)($row['sgst'] ?? 0) + (float)($row['utgst'] ?? 0);
                @endphp
                <tr>
                    @foreach($cols as $col)
                        @php
                            $k = $col['key'];
                            if ($k === 'index') {
                                $val = $index + 1;
                            } elseif ($k === 'sgst_combined') {
                                $val = $sgstCombined;
                            } else {
                                $val = $row[$k] ?? '';
                            }
                            $isNum = ($col['format'] ?? '') === 'number';
                            $isDate = ($col['format'] ?? '') === 'date';
                        @endphp
                        <td class="{{ $col['align'] }}">
                            @if($isNum)
                                {{ number_format((float)$val, 2) }}
                            @elseif($isDate && $val)
                                {{ \Carbon\Carbon::parse($val)->format('d/m/Y') }}
                            @else
                                {{ $val !== '' ? $val : '-' }}
                            @endif
                        </td>
                    @endforeach
                </tr>
            @empty
                <tr>
                    <td colspan="{{ count($cols) }}" class="empty-msg">No records found for the selected filters.</td>
                </tr>
            @endforelse

            @if(!empty($report['data']) && count($report['data']) > 0)
                <tr class="total-row">
                    @foreach($cols as $index => $col)
                        @php
                            $totKey = $col['total'] ?? null;
                        @endphp
                        <td class="{{ $col['align'] }}">
                            @if($index === 0)
                                TOTAL
                            @elseif($totKey && isset($totals[$totKey]))
                                {{ number_format((float)$totals[$totKey], 2) }}
                            @endif
                        </td>
                    @endforeach
                </tr>
            @endif
        </tbody>
    </table>
</body>
</html>
