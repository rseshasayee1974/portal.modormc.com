<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>{{ $title }}</title>
    <style>
        @page { margin: 24px 20px; }
        body { font-family: DejaVu Sans, sans-serif; font-size: 8px; color: #1d2d3e; }
        h1 { font-size: 17px; margin: 0 0 6px; }
        .period { margin-bottom: 6px; }
        .note { color: #526171; margin-bottom: 12px; }
        table { width: 100%; border-collapse: collapse; table-layout: fixed; }
        thead { display: table-header-group; }
        th { background: #dce6f1; text-align: left; font-size: 7px; }
        th, td { border: 1px solid #b7c5d3; padding: 5px 3px; overflow-wrap: break-word; word-wrap: break-word; }
        tr { page-break-inside: avoid; }
        .number { text-align: right; }
        .total { font-weight: bold; background: #e2e8f0; }
        .metadata { font-size: 7px; color: #526171; background: #f8fafc; }
        .empty { text-align: center; padding: 20px; }
        .rate-section { page-break-before: always; }
    </style>
</head>
<body>
    @php
        $metadataKeys = ['unloading', 'truck', 'description', 'irn', 'einvoice_status', 'ack_date', 'cancel_at', 'party_type', 'created_by'];
        $columns = array_values(array_filter($report['columns'], fn ($column) => !in_array($column['key'], $metadataKeys)));
        $metadata = array_values(array_filter($report['columns'], fn ($column) => in_array($column['key'], $metadataKeys)));
        $rateColumns = array_values(array_filter($columns, fn ($column) => str_starts_with($column['key'], 'taxes.')));
        // Wide registers print tax rates in continuation tables rather than clipping columns.
        $rateSections = count($rateColumns) > 6 ? array_chunk($rateColumns, 6) : [];
        if ($rateSections) {
            $columns = array_values(array_filter($columns, fn ($column) => !str_starts_with($column['key'], 'taxes.')));
        }
        $value = fn ($row, $key) => \App\Services\Reports\RegisterReportColumns::value($row, $key);
    @endphp
    <h1>{{ $title }} - {{ ucfirst($report['register_view']) }}</h1>
    <div class="period">Period: {{ $filters['from_date'] }} to {{ $filters['to_date'] }} &nbsp; | &nbsp; Generated: {{ $generated_at }} &nbsp; | &nbsp; Amounts in INR</div>
    <div class="note">{{ $report['note'] }}</div>
    <table>
        <thead><tr>
            <th style="width: 22px;">#</th>
            @foreach($columns as $column)
                <th class="{{ $column['format'] === 'number' ? 'number' : '' }}" @if(in_array($column['key'], ['customer_name', 'supplier_name', 'product_name', 'invoice_no', 'bill_no'])) style="width: 85px;" @endif>{{ $column['label'] }}</th>
            @endforeach
        </tr></thead>
        <tbody>
            @forelse($report['data'] as $index => $row)
                <tr>
                    <td>{{ $index + 1 }}</td>
                    @foreach($columns as $column)
                        @php $cell = $value($row, $column['key']); @endphp
                        <td class="{{ $column['format'] === 'number' ? 'number' : '' }}">{{ $column['format'] === 'number' ? number_format((float) $cell, 2) : ($column['format'] === 'date' && $cell ? \Carbon\Carbon::parse($cell)->format('d/m/Y') : ($cell ?: '-')) }}</td>
                    @endforeach
                </tr>
                @if(count($metadata))
                    <tr><td colspan="{{ count($columns) + 1 }}" class="metadata">
                        @foreach($metadata as $column)
                            @if($value($row, $column['key']) !== '')
                                <strong>{{ $column['label'] }}:</strong> {{ $value($row, $column['key']) }} &nbsp;
                            @endif
                        @endforeach
                    </td></tr>
                @endif
            @empty
                <tr><td colspan="{{ count($columns) + 1 }}" class="empty">No records found for the selected filters.</td></tr>
            @endforelse
            <tr class="total"><td></td>
                @foreach($columns as $index => $column)
                    <td class="{{ $column['format'] === 'number' ? 'number' : '' }}">{{ $index === 0 ? 'TOTAL' : ($column['total'] ? number_format((float) $value($report['totals'], $column['total']), 2) : '') }}</td>
                @endforeach
            </tr>
        </tbody>
    </table>
    @foreach($rateSections as $sectionIndex => $section)
        @php
            $identityColumns = array_values(array_filter($report['columns'], fn ($column) => in_array($column['key'], [
                'invoice_date', 'bill_date', 'invoice_no', 'bill_no', 'customer_name', 'supplier_name', 'product_name',
            ])));
            $splitColumns = array_merge($identityColumns, $section);
        @endphp
        <div class="rate-section">
            <h1>{{ $title }} - GST splits by rate ({{ $sectionIndex + 1 }}/{{ count($rateSections) }})</h1>
            <div class="period">Period: {{ $filters['from_date'] }} to {{ $filters['to_date'] }} &nbsp; | &nbsp; Amounts in INR</div>
            <div class="note">Row numbers match the {{ $report['register_view'] }} register. Each column shows the tax amount at the named rate.</div>
            <table>
                <thead><tr>
                    <th style="width: 22px;">#</th>
                    @foreach($splitColumns as $column)
                        <th class="{{ $column['format'] === 'number' ? 'number' : '' }}">{{ $column['label'] }}</th>
                    @endforeach
                </tr></thead>
                <tbody>
                    @foreach($report['data'] as $index => $row)
                        <tr><td>{{ $index + 1 }}</td>
                            @foreach($splitColumns as $column)
                                @php $cell = $value($row, $column['key']); @endphp
                                <td class="{{ $column['format'] === 'number' ? 'number' : '' }}">{{ $column['format'] === 'number' ? number_format((float) $cell, 2) : ($column['format'] === 'date' && $cell ? \Carbon\Carbon::parse($cell)->format('d/m/Y') : ($cell ?: '-')) }}</td>
                            @endforeach
                        </tr>
                    @endforeach
                    <tr class="total"><td></td>
                        @foreach($splitColumns as $index => $column)
                            <td class="{{ $column['format'] === 'number' ? 'number' : '' }}">{{ $index === 0 ? 'TOTAL' : ($column['total'] ? number_format((float) $value($report['totals'], $column['total']), 2) : '') }}</td>
                        @endforeach
                    </tr>
                </tbody>
            </table>
        </div>
    @endforeach
</body>
</html>
