{{--
Author: Cipher
Created: 2026-10-15 16:44:52 Asia/Calcutta (UTC+05:30)
--}}
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Certificate of Concrete Strength - {{ $test->test_no }}</title>
    <style>
        @page { size: A4 portrait; margin: 16mm 13mm; }
        body { margin: 0; font: 9pt DejaVu Sans, sans-serif; color: #111; }
        table { width: 100%; border-collapse: collapse; table-layout: fixed; }
        th, td { border: 1px solid #333; padding: 6px; overflow-wrap: break-word; }
        .page { page-break-after: always; }
        .page:last-child { page-break-after: auto; }
        .title { border: 1px solid #333; padding: 7px; text-align: center; font-size: 12pt; font-weight: bold; }
        .metadata td { vertical-align: top; line-height: 1.6; }
        .section { border: 1px solid #333; border-top: 0; text-align: center; font-weight: bold; padding: 5px; }
        .summary { font-size: 8pt; }
        .summary th { font-size: 7pt; padding: 5px 2px; text-align: center; font-weight: normal; }
        .summary td { padding: 7px 2px; text-align: center; font-size: 7pt; }
        .average { font-weight: bold; }
        .remarks { border: 1px solid #333; border-top: 0; padding: 9px; line-height: 1.6; }
        .signature { width: 100%; margin-top: 22px; }
        .signature td { border: 0; padding: 0; width: 50%; vertical-align: top; }
        .muted { color: #555; font-size: 7pt; }
        .reference { font-size: 7pt; margin-top: 7px; color: #555; }
    </style>
</head>
<body>
@php
    $number = fn ($value, $precision = 2) => $value !== null ? number_format((float) $value, $precision, '.', '') : '-';
    $date = fn ($value) => $value ? \Carbon\Carbon::parse($value)->format('d/m/Y') : '-';
    $pages = collect();
    foreach ($reportSets as $set) {
        foreach ($set->specimens->sortBy('specimen_index')->chunk(12) as $specimens) {
            $pages->push(['set' => $set, 'specimens' => $specimens->values()]);
        }
    }
@endphp
@foreach ($pages as $pageIndex => $page)
@php $set = $page['set']; $specimens = $page['specimens']; $dimensions = $set->dimensions; @endphp
<div class="page">
    @include('pdfs.partials._brand_header')
    <div class="title">CERTIFICATE OF CONCRETE STRENGTH</div>
    <table class="metadata">
        <tr><td colspan="2"><strong>Casting date:</strong> {{ $date($set->casting_date) }}</td></tr>
        <tr>
            <td><strong>Client name:</strong> {{ $clientName }}<br>{{ $siteAddress }}</td>
            <td><strong>Project name:</strong> {{ $projectName }}<br><span class="muted">Test: {{ $set->source_test_no }}</span></td>
        </tr>
    </table>
    <div class="section">Specimen Summary</div>
    <table class="metadata">
        <tr>
            <td><strong>Grade:</strong> {{ $gradeName ?: '-' }}<br><strong>Identification:</strong> {{ $test->sample?->sample_no }}</td>
            <td><strong>Place of casting:</strong> {{ $set->place_of_casting ?: $plant?->name }}<br><strong>Slump:</strong> {{ $number($test->sample?->slump_mm, 0) }} mm</td>
        </tr>
    </table>
    <div class="section">COMPRESSIVE STRENGTH SUMMARY</div>
    <table class="summary">
        <colgroup>
            <col style="width:6%"><col style="width:13%"><col style="width:5%">
            <col style="width:5%"><col style="width:5%"><col style="width:5%">
            <col style="width:8%"><col style="width:9%"><col style="width:9%">
            <col style="width:9%"><col style="width:10%"><col style="width:16%">
        </colgroup>
        <thead>
            <tr>
                <th rowspan="2">Specimen<br>ID</th><th rowspan="2">Testing<br>date</th><th rowspan="2">Age<br>(days)</th>
                <th colspan="3">Size (mm)</th><th rowspan="2">Weight<br>(kg)</th><th rowspan="2">Density<br>(kg/m3)</th>
                <th rowspan="2">Load<br>(kN)</th><th rowspan="2">Strength<br>(MPa)</th><th rowspan="2">Average<br>(MPa)</th><th rowspan="2">Remarks</th>
            </tr>
            <tr><th>L</th><th>W</th><th>H</th></tr>
        </thead>
        <tbody>
        @foreach ($specimens as $index => $specimen)
            <tr>
                <td>{{ $specimen->identification_mark ?: $specimen->specimen_index }}</td>
                <td>{{ $date($set->testing_date) }}</td><td>{{ $set->age_days }}</td>
                <td>{{ $number($dimensions['length_mm'], 0) }}</td><td>{{ $number($dimensions['width_mm'], 0) }}</td><td>{{ $number($dimensions['height_mm'], 0) }}</td>
                <td>{{ $number($specimen->weight_kg, 3) }}</td>
                <td>{{ $number($specimen->density_kg_m3 ?? ((float) $specimen->weight_kg / $dimensions['volume_m3']), 0) }}</td>
                <td>{{ $number($specimen->load_kn, 0) }}</td><td>{{ $number($specimen->strength_mpa) }}</td>
                @if ($index === 0)
                    <td rowspan="{{ $specimens->count() }}" class="average">{{ $number($set->average_strength) }}</td>
                @endif
                <td>{{ $index === 0 ? $set->report_remarks : '' }}</td>
            </tr>
        @endforeach
        </tbody>
    </table>
    <div class="remarks">
        <strong>Remarks</strong><br>
        {{ $test->remarks }}
        <p>Results refer only to the specimens identified in this report.</p>
        <p>Test method: {{ $test->configuration_snapshot['standard_code'] ?? $test->testType?->standard_reference ?: 'As configured for this test' }}</p>
        <table class="signature"><tr>
            <td>Prepared by:<br><strong>{{ $test->tester?->username ?: '-' }}</strong></td>
            <td>Verified by:<br><strong>{{ $test->reviewer?->username ?: 'Awaiting review' }}</strong></td>
        </tr></table>
    </div>
    <div class="reference">Sample: {{ $test->sample?->sample_no }} | Issued: {{ $reportDate }} | Page {{ $pageIndex + 1 }} of {{ $pages->count() }}</div>
</div>
@endforeach
</body>
</html>
