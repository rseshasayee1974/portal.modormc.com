<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Cube Test Report - {{ $test->test_no }}</title>
<style>
    @page { size: A4 landscape; margin: 12mm 13mm; }
    body { margin: 0; color: #111; font: 10pt Helvetica, Arial, sans-serif; }
    table { width: 100%; border-collapse: collapse; table-layout: fixed; }
    td, th { border: 1px solid #222; overflow-wrap: break-word; }
    .page { page-break-after: always; }
    .page:last-child { page-break-after: auto; }
    .heading td { padding: 0; }
    .brand { padding: 9px 12px !important; }
    .brand table td { border: 0; padding: 0; }
    .logo { max-width: 74px; max-height: 65px; width: auto; height: auto; display: block; }
    .company { color: #d0002a; font-size: 25pt; font-weight: bold; }
    .address { font-size: 9pt; margin-top: 5px; line-height: 1.3; }
    .title { text-align: center; font-size: 14pt; font-weight: bold; padding: 5px !important; }
    .date { padding: 7px 12px !important; font-size: 10pt; }
    .metadata td { padding: 6px; font-size: 10pt; }
    .grid th { font-size: 9pt; font-weight: normal; height: 48px; padding: 4px 2px; line-height: 1.12; }
    .grid td { font-size: 9pt; text-align: center; height: 18px; padding: 2px; }
    .grid .sign td { height: 32px; vertical-align: bottom; text-align: left; padding: 5px 35px; }
    .sign table td { border: 0; padding: 0 !important; height: auto !important; }
    .average { font-weight: bold; }
    .footer td { height: 48px; vertical-align: top; padding: 7px 9px; font-size: 10pt; }
    .incharge { text-align: right; margin-top: 18px; padding-right: 55px; }
    .reference { margin-top: 4px; font-size: 8pt; color: #555; }
</style>
</head>
<body>
@php
    // Resolve plant logo if not provided
    $plantLogo = $plantLogo ?? ($companyLogo ?? null);
    if (empty($plantLogo)) {
        $plant = $plant ?? ($test->plant ?? null);
        if (!$plant) {
            $activePlantId = app(\App\Services\PlantContextService::class)->plantId() ?? session('active_plant_id');
            $plant = $activePlantId ? \App\Models\Plant::find($activePlantId) : \App\Models\Plant::first();
        }
        if ($plant && !empty($plant->logo_path)) {
            $cleanLogo = ltrim(str_replace(['public/', 'storage/', '/storage/'], '', $plant->logo_path), '/\\');
            $possiblePaths = [
                storage_path('app/public/' . $cleanLogo),
                public_path('storage/' . $cleanLogo),
                base_path('storage/app/public/' . $cleanLogo),
                base_path('public/storage/' . $cleanLogo),
            ];
            foreach ($possiblePaths as $pPath) {
                if (file_exists($pPath)) {
                    $mime = mime_content_type($pPath) ?: 'image/png';
                    $plantLogo = 'data:' . $mime . ';base64,' . base64_encode(file_get_contents($pPath));
                    break;
                }
            }
        }
    }

    // Keep set averages independent. Paginate at three specimen rows per block,
    // repeating a set's recorded average when that set continues in another block.
    $blocks = collect();
    foreach (($reportSets ?? $test->sets)->sortBy('set_number') as $set) {
        $specimens = $set->specimens->sortBy('specimen_index')->values();
        foreach (($specimens->isEmpty() ? collect([collect()]) : $specimens->chunk(3)) as $chunk) {
            $blocks->push(['set' => $set, 'specimens' => $chunk->values()]);
        }
    }
    if ($blocks->isEmpty()) $blocks->push(['set' => null, 'specimens' => collect()]);
    $pages = $blocks->chunk(3);
    $serial = 0;
    $formatDate = fn ($value) => $value ? \Carbon\Carbon::parse($value)->format('d/m/Y') : '';
    $number = fn ($value, $precision = 2) => $value !== null ? number_format((float) $value, $precision) : '';
@endphp
@foreach ($pages as $pageIndex => $pageBlocks)
<div class="page">
    <table class="heading">
        <colgroup><col style="width:69%"><col style="width:31%"></colgroup>
        <tr>
            <td rowspan="2" class="brand" style="width:69%">
                <table><tr>
                    @if (!empty($plantLogo))
                        <td style="width:78px; vertical-align:middle; padding-right:10px;"><img class="logo" src="{{ $plantLogo }}" alt="Plant logo"></td>
                    @endif
                    <td style="vertical-align:middle;"><div class="company">{{ $companyName }}</div><div class="address">{{ $companyAddress }}</div></td>
                </tr></table>
            </td>
            <td class="title" style="width:31%">CUBE TEST REPORT<br>COMPRESSIVE STRENGTH</td>
        </tr>
        <tr><td class="date">DATE : {{ $reportDate }}</td></tr>
    </table>
    <table class="metadata">
        <colgroup><col style="width:14%"><col style="width:40%"><col style="width:46%"></colgroup>
        <tr><td style="width:14%">CLIENT NAME : M/S.</td><td style="width:40%">{{ $clientName }}</td><td style="width:46%">GRADE OF CONCRETE : {{ $gradeName }}</td></tr>
        <tr><td>SITE ADDRESS :</td><td>{{ $siteAddress }}</td><td>CUBE SIZE : {{ $cubeSize }}</td></tr>
    </table>
    <table class="grid">
        <colgroup>
            <col style="width:3.3%"><col style="width:10.8%"><col style="width:10.7%"><col style="width:19.4%"><col style="width:9.6%">
            <col style="width:5.3%"><col style="width:10.2%"><col style="width:10.3%"><col style="width:10.2%"><col style="width:10.2%">
        </colgroup>
        <thead><tr>
            <th style="width:3.3%">S.<br>NO.</th><th style="width:10.8%">IDENTIFICATION<br>MARK</th><th style="width:10.7%">DATE OF<br>CASTING</th><th style="width:19.4%">PLACE OF CASTING</th>
            <th style="width:9.6%">DATE OF<br>TESTING</th><th style="width:5.3%">AGE AT<br>TEST</th><th style="width:10.2%">WEIGHT<br>OF CUBE<br>(kg)</th><th style="width:10.3%">LOAD IN<br>kN</th>
            <th style="width:10.2%">INDIVIDUAL<br>COMPRESSIVE<br>STRENGTH<br>N/mm²</th><th style="width:10.2%">AVERAGE<br>COMPRESSIVE<br>STRENGTH<br>N/mm²</th>
        </tr></thead>
        <tbody>
        @for ($blockIndex = 0; $blockIndex < 3; $blockIndex++)
            @php
                $block = $pageBlocks->values()->get($blockIndex);
                $set = $block['set'] ?? null;
                $specs = $block['specimens'] ?? collect();
            @endphp
            @for ($i = 0; $i < 3; $i++)
                @php $spec = $specs->get($i); @endphp
                <tr>
                    <td>{{ ++$serial }}</td>
                    <td>{{ $spec?->identification_mark }}</td>
                    <td>{{ $spec ? $formatDate($set?->casting_date) : '' }}</td>
                    <td>{{ $spec ? $set?->place_of_casting : '' }}</td>
                    <td>{{ $spec ? $formatDate($set?->testing_date) : '' }}</td>
                    <td>{{ $spec && $set?->age_days !== null ? $set->age_days . ' Days' : '' }}</td>
                    <td>{{ $number($spec?->weight_kg, 3) }}</td>
                    <td>{{ $number($spec?->load_kn) }}</td>
                    <td>{{ $number($spec?->strength_mpa) }}</td>
                    @if ($i === 0)
                        <td rowspan="4" class="average">{{ $number($set?->average_strength) }}</td>
                    @endif
                </tr>
            @endfor
            <tr class="sign"><td colspan="9">
                <table><tr><td>CLIENT SIGN {{ $set?->client_sign_name }}</td><td style="text-align:right">Q.C. SIGN {{ $set?->qc_sign_name }}</td></tr></table>
            </td></tr>
        @endfor
        </tbody>
    </table>
    <table class="footer"><tr><td>
        REMARKS : {{ $test->remarks ?: 'CUBES TAKEN AT SITE / PLANT' }}
        <div class="incharge">Q.C. INCHARGE</div>
    </td></tr></table>
    <div class="reference">Sample: {{ $test->sample?->sample_no }} | Tests: {{ $reportTestNumbers ?? $test->test_no }} | Page {{ $pageIndex + 1 }} of {{ $pages->count() }}</div>
</div>
@endforeach
</body>
</html>
