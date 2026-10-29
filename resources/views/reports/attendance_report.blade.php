<!--
Author: Cipher
Created: 2026-10-27 15:37:34 Asia/Calcutta (UTC+05:30)
-->
<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Attendance Report</title>
    <style>
        {!! $css ?? $report_css ?? file_get_contents(public_path('css/reports/report_pdf.css')) !!}
        thead { display: table-header-group; }
        tr { page-break-inside: avoid; }
        th, td { overflow-wrap: break-word; }
    </style>
</head>
<body>
@include('pdfs.partials._brand_header')
<div class="statement-title-container">
    <h2 class="statement-title">Attendance Report</h2>
    <p class="statement-period">Period: {{ $start ?? '' }} to {{ $end ?? '' }}</p>
</div>
{{-- <p>Employees: {{ $total_employees }} | Records: {{ $total_records }} | Recorded attendance only. Times: Asia/Kolkata.</p> --}}
<table class="data-table">
    <thead><tr><th>#</th>@foreach($columns as $label)<th>{{ $label }}</th>@endforeach</tr></thead>
    <tbody>
    @forelse($transactions as $row)
        <tr><td>{{ $loop->iteration }}</td>
        @foreach($columns as $field => $label)
            <td class="{{ str_ends_with($field, '_hours') ? 'text-right' : '' }}">{{ $row[$field] ?? '—' }}</td>
        @endforeach
        </tr>
    @empty
        <tr><td colspan="{{ count($columns) + 1 }}">No attendance records match the selected filters.</td></tr>
    @endforelse
    <tr class="total-row"><td>Total</td>@foreach($columns as $field => $label)<td class="text-right">{{ isset($totals[$field]) ? number_format($totals[$field], 2) : '' }}</td>@endforeach</tr>
    </tbody>
</table>
</body>
</html>
