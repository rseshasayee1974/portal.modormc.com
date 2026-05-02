<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Batch Sheet Report</title>
    <style>
        @page { size: A4 landscape; margin: 12mm 10mm 14mm 10mm; }
        body { font-family: Arial, Helvetica, sans-serif; font-size: 10px; color: #111; margin: 0; }
        .preview-toolbar { max-width: 1000px; margin: 0 auto 12px auto; display: flex; justify-content: space-between; align-items: center; }
        .preview-toolbar a, .preview-toolbar button {
            border: 1px solid #d1d5db;
            background: #fff;
            color: #111827;
            padding: 7px 10px;
            border-radius: 6px;
            font-size: 11px;
            text-decoration: none;
            cursor: pointer;
        }
        .preview-toolbar a.primary { background: #1f2937; color: #fff; border-color: #1f2937; }
        .sheet { width: 100%; border: 1px solid #444; padding: 8px 10px 10px 10px; box-sizing: border-box; }
        .top-row { width: 100%; border-collapse: collapse; margin-bottom: 4px; }
        .top-row td { vertical-align: middle; }
        .logo-cell { width: 120px; font-size: 11px; font-weight: 700; }
        .logo-box {
            border: 1px solid #777;
            width: 100px;
            height: 26px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            color: #444;
            font-size: 9px;
            font-weight: 700;
            text-transform: uppercase;
        }
        .title-cell { text-align: center; }
        .company-title { font-weight: 700; font-size: 20px; letter-spacing: 0.5px; }
        .report-title { margin-top: 2px; font-weight: 700; font-size: 17px; letter-spacing: 0.8px; }
        .meta-strip { width: 100%; border-collapse: collapse; margin-top: 6px; margin-bottom: 6px; }
        .meta-strip td { border-top: 1px solid #444; border-bottom: 1px solid #444; padding: 3px 4px; font-size: 15px; font-weight: 700; }
        .meta-strip td:last-child { text-align: right; }
        .info-grid { width: 100%; border-collapse: collapse; margin-bottom: 6px; }
        .info-grid td { padding: 1px 4px; font-size: 14px; }
        .k { width: 145px; font-weight: 700; }
        .v { font-weight: 400; }
        .empty { width: 28px; }
        .materials { width: 100%; border-collapse: collapse; table-layout: fixed; margin-top: 4px; }
        .materials th, .materials td {
            border: 1px solid #444;
            text-align: center;
            padding: 2px 1px;
            font-size: 11px;
        }
        .materials .group-head th { font-weight: 700; font-size: 11px; background: #f7f7f7; }
        .materials .name-row th { font-size: 9px; font-weight: 700; height: 18px; }
        .materials .number-row td { font-size: 11px; font-weight: 700; }
        .materials .section-row td {
            text-align: left;
            padding-left: 6px;
            font-weight: 700;
            background: #fafafa;
        }
        .summary-wrap { margin-top: 6px; }
        .summary-table { width: 100%; border-collapse: collapse; }
        .summary-table td { padding: 3px 4px; font-size: 12px; border: 1px solid #444; }
        .summary-table .label { font-weight: 700; width: 36%; }
        .summary-table .value { text-align: right; font-weight: 700; width: 14%; }
        .diff-neg { color: #9b1c1c; }
        .diff-pos { color: #065f46; }
        footer {
            position: fixed;
            bottom: -10mm;
            left: 0;
            right: 0;
            height: 10mm;
            border-top: 1px solid #444;
            display: table;
            width: 100%;
            font-size: 10px;
        }
        .footer-cell { display: table-cell; vertical-align: middle; }
        .footer-center { text-align: center; font-weight: 700; }
        .footer-right { text-align: right; }

        @media print {
            .preview-toolbar { display: none; }
            .sheet { border: 1px solid #444; }
        }
    </style>
</head>
<body>
@if(!empty($isPreview))
    <div class="preview-toolbar">
        <a href="{{ route('batches.index') }}">Back to batches</a>
        <a class="primary" href="{{ route('batches.download', $batch->id) }}">Download PDF</a>
    </div>
@endif

<div class="sheet">
    <table class="top-row">
        <tr>
            <td class="logo-cell">
                <div class="logo-box">Schwing Stetter</div>
            </td>
            <td class="title-cell">
                <div class="company-title">{{ strtoupper($batch->workOrder?->plant?->entity?->legal_name ?? 'V J MIX CONCRETE INDIA PVT LTD') }}</div>
                <div class="report-title">BATCH SHEET REPORT</div>
            </td>
            <td class="logo-cell" style="text-align:right;">
                <div class="logo-box">Schwing Stetter</div>
            </td>
        </tr>
    </table>

    <table class="meta-strip">
        <tr>
            <td>Plant Type : {{ $batch->workOrder?->plant?->plant_type ?? 'M1.5' }}</td>
            <td>Plant Sl.No : {{ $batch->workOrder?->plant?->code ?? '121' }}</td>
        </tr>
    </table>

    <table class="info-grid">
        <tr>
            <td class="k">Batch Number</td><td class="v">: {{ $batch->batch_no }}</td>
            <td class="k">Recipe Name</td><td class="v">: {{ $batch->workOrder?->mixDesign?->concrete_grade?->name ?? $batch->workOrder?->mixDesign?->design_type ?? '-' }}</td>
            <td class="k">Mixer Capacity</td><td class="v">: {{ number_format((float)($batch->workOrder?->mixDesign?->rate_per_qty ?? 1.25), 2) }}</td>
        </tr>
        <tr>
            <td class="k">Batch Date</td><td class="v">: {{ optional($batch->load_time ?? $batch->created_at)->format('d-m-Y') }}</td>
            <td class="k">Recipe Code</td><td class="v">: {{ $batch->workOrder?->mixDesign?->design_code ?? '-' }}</td>
            <td class="k">Batch Size</td><td class="v">: {{ number_format((float)$batch->batch_size, 4) }}</td>
        </tr>
        <tr>
            <td class="k">Batch Start Time</td><td class="v">: {{ optional($batch->start_time)->format('H:i:s') ?? '-' }}</td>
            <td class="k">Truck No</td><td class="v">: {{ $batch->truck?->registration ?? '-' }}</td>
            <td class="k">Production Qty</td><td class="v">: {{ number_format((float)($batch->workOrder?->produced_qty ?? 0), 2) }}</td>
        </tr>
        <tr>
            <td class="k">Batch End Time</td><td class="v">: {{ optional($batch->end_time)->format('H:i:s') ?? '-' }}</td>
            <td class="k">Truck Driver</td><td class="v">: {{ trim(($batch->driver?->first_name ?? '') . ' ' . ($batch->driver?->last_name ?? '')) ?: '-' }}</td>
            <td class="k">Order No</td><td class="v">: {{ $batch->workOrder?->order_no ?? '-' }}</td>
        </tr>
        <tr>
            <td class="k">Customer</td><td class="v">: {{ $batch->workOrder?->customer?->legal_name ?? '-' }}</td>
            <td class="k">Adj / Manual Qty</td><td class="v">: 0.00</td>
            <td class="k">Ordered Qty</td><td class="v">: {{ number_format((float)($batch->workOrder?->total_qty ?? 0), 2) }}</td>
        </tr>
        <tr>
            <td class="k">Site</td><td class="v">: {{ $batch->workOrder?->site?->name ?? $batch->site?->name ?? '-' }}</td>
            <td class="k"></td><td class="v"></td>
            <td class="k">With This Load</td><td class="v">: {{ number_format((float)$batch->batch_size, 2) }}</td>
        </tr>
    </table>

    @php
        $materials = $sheet['table_materials'] ?? [];
        $grouped = $sheet['grouped'] ?? [];
        $groupOrder = $sheet['group_order'] ?? [];
        $colCount = count($materials) > 0 ? count($materials) : 1;
    @endphp

    <table class="materials">
        <thead>
            <tr class="group-head">
                @foreach($groupOrder as $groupName)
                    @php $span = max(count($grouped[$groupName] ?? []), 1); @endphp
                    <th colspan="{{ $span }}">{{ $groupName }}</th>
                @endforeach
            </tr>
            <tr class="name-row">
                @foreach($groupOrder as $groupName)
                    @foreach($grouped[$groupName] ?? [] as $entry)
                        <th>{{ $entry['short'] }}</th>
                    @endforeach
                @endforeach
            </tr>
        </thead>
        <tbody>
            <tr class="number-row">
                @foreach($materials as $entry)
                    <td>{{ number_format((float)$entry['target'], 2) }}</td>
                @endforeach
            </tr>
            <tr class="section-row">
                <td colspan="{{ $colCount }}">Actual Values in kg</td>
            </tr>
            <tr class="number-row">
                @foreach($materials as $entry)
                    <td>{{ number_format((float)$entry['actual'], 2) }}</td>
                @endforeach
            </tr>
            <tr class="number-row">
                @foreach($materials as $entry)
                    <td class="{{ (float)$entry['diff_percent'] < 0 ? 'diff-neg' : 'diff-pos' }}">
                        {{ number_format((float)$entry['diff_percent'], 2) }}
                    </td>
                @endforeach
            </tr>
        </tbody>
    </table>

    <div class="summary-wrap">
        <table class="summary-table">
            <tr>
                <td class="label">Total Set Weight in kg</td>
                <td class="value">{{ number_format((float)$sheet['total_set_weight'], 2) }}</td>
                <td class="label">Mass of Recipe Targets in kg</td>
                <td class="value">{{ number_format((float)$sheet['total_set_weight'], 2) }}</td>
            </tr>
            <tr>
                <td class="label">Total Actual Weight in kg</td>
                <td class="value">{{ number_format((float)$sheet['total_actual_weight'], 2) }}</td>
                <td class="label">Mass of Total Set Weight in kg</td>
                <td class="value">{{ number_format((float)$sheet['total_set_weight'], 2) }}</td>
            </tr>
            <tr>
                <td class="label">Difference in Percentage</td>
                <td class="value {{ (float)$sheet['total_difference_percent'] < 0 ? 'diff-neg' : 'diff-pos' }}">{{ number_format((float)$sheet['total_difference_percent'], 2) }}</td>
                <td class="label">Mass of Total Actual Weight in kg</td>
                <td class="value">{{ number_format((float)$sheet['total_actual_weight'], 2) }}</td>
            </tr>
            <tr>
                <td class="label" colspan="3">Total Mass Difference in Percentage</td>
                <td class="value {{ (float)$sheet['total_difference_percent'] < 0 ? 'diff-neg' : 'diff-pos' }}">{{ number_format((float)$sheet['total_difference_percent'], 2) }}</td>
            </tr>
        </table>
    </div>

    <footer>
        <div class="footer-cell"><strong>Schwing Stetter</strong></div>
        <div class="footer-cell footer-center">Page 1</div>
        <div class="footer-cell footer-right">Date: {{ now()->format('d-m-Y h:i:s A') }}</div>
    </footer>
</div>
</body>
</html>
