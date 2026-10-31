<!--
Author: ragul-onemodo
Created: 2026-10-31 09:25:14 Asia/Calcutta (UTC+05:30)
-->
@php
    $report = \App\Services\JayadhityaBatchSheet::layoutData($batch);
    $order = $batch->relationLoaded('workOrder') && !$batch->relationLoaded('salesOrder') ? $batch->workOrder : $batch->salesOrder;
    $plant = $batch->plant ?? $order?->plant;
    $mix = $order?->mixDesign;
    $dispatch = $batch->dispatches->first();
    $driver = $dispatch?->driver;
    $batchDate = $batch->start_time ?? $batch->created_at;
    $value = fn ($value) => $value !== null && $value !== '' ? $value : '-';
    $format = function ($value, $column) {
        if ($value === null) return '-';
        $value = (float) $value;
        $precision = abs($value - round($value, 2)) > 0.000001 ? 3
            : ($column === 'Sil' ? 1 : (in_array($column, ['ADMIX 1', 'ADMIX 2', 'Adm 3', 'Adm 4'], true) ? 2 : ($value == floor($value) ? 0 : 2)));
        return number_format($value, $precision, '.', '');
    };
    $left = [['Batch Number', $batch->batch_no], ['Batch Date', $batchDate?->format('d-m-Y')],
        ['Batch Start Time', $batch->start_time?->format('H:i:s')], ['Batch End Time', $batch->end_time?->format('H:i:s')],
        ['Customer', $order?->customer?->legal_name], ['Site', $order?->site?->name]];
    $middle = [['Recipe Name', $mix?->design_name ?? $mix?->grade], ['Recipe Code', $mix?->design_code],
        ['Truck No', $dispatch?->truck?->registration], ['Truck Driver', trim(($driver?->first_name ?? '').' '.($driver?->last_name ?? ''))],
        ['Adj / Manual Qty', null]];
    $right = [['Mixer Capacity', isset($plant->mixer_capacity) ? number_format($plant->mixer_capacity, 2) : null],
        ['Batch Size', number_format($report['run_size'], 4)], ['Production Qty', number_format((float) $batch->batch_size, 2)],
        ['Order No', $order?->order_no], ['Ordered Qty', isset($order->total_qty) ? number_format((float) $order->total_qty, 2) : null],
        ['With This Load', $report['with_this_load'] !== null ? number_format((float) $report['with_this_load'], 2) : null]];
    $brand = \App\Services\PrintBrandHeader::resolve(['plant' => $plant, 'batchSheetBrand' => true]);
    $columnCount = max(1, count($report['columns']));
@endphp
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Batch Sheet Report</title>
<style>
  @page { size: A4; margin: 0; }
  * { box-sizing: border-box; }
  body { margin: 0; background: #e5e5e5; font-family: "Times New Roman", Times, serif; color: #000; }
  .page { width: 900px; min-height: 1273px; margin: 16px auto; background: #fff; padding: 24px 30px; font-size: 16px; display: flex; flex-direction: column; }
  .scroll { overflow-x: auto; }
  .head { display: grid; grid-template-columns: 170px minmax(0, 1fr) 170px; align-items: start; gap: 12px; }
  .head .mid { text-align: center; padding-top: 2px; }
  .head .mid b { display: block; font-size: 19px; line-height: 24px; }
  .head .mid b + b { margin-top: 14px; font-size: 18px; }
  .logo { display: flex; align-items: flex-start; min-height: 44px; }
  #logoR { justify-content: flex-end; }
  .logo img { max-width: 150px; max-height: 60px; object-fit: contain; }
  .logo.sm img { max-width: 100px; max-height: 36px; }
  .plant { display: grid; grid-template-columns: 1fr 1fr; text-align: center; gap: 20px; font-size: 18px; line-height: 26px; margin-top: 22px; padding-bottom: 2px; border-bottom: 1px solid #000; }
  .plant span.v { font-weight: 400; }

  /* Shared table rows keep all three metadata blocks aligned when a value wraps. */
  .info { width: 100%; border-collapse: collapse; table-layout: fixed; margin: 2px 0 8px; font-size: 15.5px; line-height: 23px; }
  .info td { padding: 0; vertical-align: top; }
  .info td.key { width: 14.5%; font-weight: 700; white-space: nowrap; }
  .info td.separator { width: 1.5%; text-align: center; }
  .info td.value { width: 17.33%; padding-right: 4px; overflow-wrap: anywhere; }

  table.b { width: 100%; table-layout: fixed; border-collapse: collapse; font-size: 15px; }
  table.b th, table.b td { text-align: center; padding: 4px 3px; overflow-wrap: anywhere; }
  table.b thead { display: table-header-group; }
  table.b tr { break-inside: avoid; }
  table.b tr.grp th { font-size: 16px; line-height: 21px; padding: 0; white-space: nowrap; }
  table.b tr.grp th span { display: block; margin: 0 4px; padding-bottom: 3px; border-bottom: 1px solid #777; }
  table.b tr.grp th:first-child span { margin-left: 0; }
  table.b tr.grp th:last-child span { margin-right: 0; }
  table.b tr.cols th { font-size: 12.5px; line-height: 15px; padding: 5px 3px; vertical-align: middle; }
  table.b tr.label td { text-align: left; font-weight: 700; font-size: 14.5px; line-height: 19px; padding: 6px 2px 1px; border-bottom: .6px solid #555; }
  table.b tr.recipe-label td { color: #333; font-size: 12px; font-weight: 400; border: 0; padding: 2px 2px 0; line-height: 15px; }
  table.b tr.data td { padding: 4px 3px; line-height: 19px; }
  table.b tr.note td { text-align: right; font-weight: 700; font-size: 14.5px; line-height: 20px; padding: 4px 2px 1px; }
  .foot { margin-top: auto; padding-top: 28px; display: grid; grid-template-columns: 1fr auto 1fr; gap: 16px; align-items: end; font-weight: 700; font-size: 13px; }
  .foot > :last-child { text-align: right; }
  @media print {
    body { background: #fff; }
    .page { width: 900px; min-height: 1273px; margin: 0; zoom: 0.8817; }
    .scroll { overflow: visible; }
  }
  @media (max-width: 940px) { .page { margin: 0; } }
</style>
</head>
<body>
<div class="page">
  <div class="scroll"><div style="min-width:840px">
  <div class="head">
    <div class="logo" id="logoL">@if ($brand['logo'])<img src="{{ $brand['logo'] }}" alt="{{ $brand['name'] }} logo">@endif</div>
    <div class="mid"><b id="company">{{ $plant?->name }}</b><b>BATCH SHEET REPORT</b></div>
    <div class="logo" id="logoR">@if ($brand['logo'])<img src="{{ $brand['logo'] }}" alt="{{ $brand['name'] }} logo">@endif</div>
  </div>
  <div class="plant">
    <div><b>Plant Type :</b> <span class="v" id="plantType">{{ $value($plant?->plant_type) }}</span></div>
    <div><b>Plant Sl.No :</b> <span class="v" id="plantSl">{{ $value($plant?->code) }}</span></div>
  </div>
  <table class="info" id="info" aria-label="Batch details">
    <tbody>
    @for ($rowIndex = 0; $rowIndex < max(count($left), count($middle), count($right)); $rowIndex++)
      <tr>
        @foreach ([$left, $middle, $right] as $list)
          @php [$key, $entry] = $list[$rowIndex] ?? [null, null]; @endphp
          <td class="key">{{ $key }}</td><td class="separator">{{ $key !== null ? ':' : '' }}</td><td class="value">{{ $key !== null ? $value($entry) : '' }}</td>
        @endforeach
      </tr>
    @endfor
    </tbody>
  </table>
  <table class="b" id="tbl">
    @if (empty($report['columns']))
    <tr><td>No consumption records stored for this batch.</td></tr>
    @else
    <thead>
    <tr class="grp">@foreach ($report['group_spans'] as $group)<th colspan="{{ $group['span'] }}"><span>{{ $group['name'] }}</span></th>@endforeach</tr>
    <tr class="cols">@foreach ($report['columns'] as $column)<th class="{{ $loop->last ? 'sil' : '' }}">{{ $report['labels'][$column] ?? $column }}</th>@endforeach</tr>
    </thead>
    <tbody>
    @foreach ($report['recipe_rows'] as $row)
      {{-- <tr class="label recipe-label"><td colspan="{{ $columnCount }}">{{ $loop->first ? 'Mix Design in kg per m³' : 'Set Weight in kg per m³' }}</td></tr> --}}
      <tr class="data" data-row="recipe">@foreach ($report['columns'] as $column)<td class="{{ $loop->last ? 'sil' : 'num' }}">{{ $format($row === null ? null : ($row[$column] ?? 0), $column) }}</td>@endforeach</tr>
    @endforeach
    <tr class="note"><td colspan="{{ $columnCount }}">Mass of Recipe Targets in kg : {{ $report['recipe'] ? number_format(array_sum($report['recipe']), 2, '.', '') : '-' }}</td></tr>
    <tr class="label"><td colspan="{{ $columnCount }}">Actual Values in kg</td></tr>
    @foreach ($report['runs'] as $row)
      <tr class="data" data-row="actual">@foreach ($report['columns'] as $column)<td class="{{ $loop->last ? 'sil' : 'num' }}">{{ $format($row === null ? null : ($row[$column] ?? 0), $column) }}</td>@endforeach</tr>
    @endforeach
    <tr class="label"><td colspan="{{ $columnCount }}">Total Set Weight in kg</td></tr>
    <tr class="data">@foreach ($report['columns'] as $column)<td class="{{ $loop->last ? 'sil' : 'num' }}">{{ $format($report['targets'][$column] ?? 0, $column) }}</td>@endforeach</tr>
    <tr class="note top"><td colspan="{{ $columnCount }}">Mass of Total Set Weight in kg : {{ number_format($report['total_target'], 2, '.', '') }}</td></tr>
    <tr class="label"><td colspan="{{ $columnCount }}">Total Actual Weight in kg</td></tr>
    <tr class="data">@foreach ($report['columns'] as $column)<td class="{{ $loop->last ? 'sil' : 'num' }}">{{ $format($report['actuals'][$column] ?? 0, $column) }}</td>@endforeach</tr>
    <tr class="note top"><td colspan="{{ $columnCount }}">Mass of Total Actual Weight in kg : {{ number_format($report['total_actual'], 2, '.', '') }}</td></tr>
    <tr class="label"><td colspan="{{ $columnCount }}">Difference in Percentage</td></tr>
    <tr class="data">@foreach ($report['columns'] as $column)
      @php $target = $report['targets'][$column] ?? 0; $actual = $report['actuals'][$column] ?? 0; @endphp
      <td class="{{ $loop->last ? 'sil' : 'num' }}">{{ number_format($target != 0 ? ($actual - $target) / $target * 100 : 0, 2, '.', '') }}</td>
    @endforeach</tr>
    <tr class="note top"><td colspan="{{ $columnCount }}">Total Mass Difference in Percentage : {{ number_format($report['difference'], 2, '.', '') }}</td></tr>
    </tbody>
    @endif
  </table>
  </div></div>
  <div class="foot">
    <div class="logo sm" id="logoF">@if ($brand['logo'])<img src="{{ $brand['logo'] }}" alt="{{ $brand['name'] }} logo">@endif</div>
    <div style="text-align:center">Page <span id="pg">1</span></div>
    <div>Date: <span id="printDate">{{ now()->format('d-m-Y g:i:s A') }}</span></div>
  </div>
</div>
</body>
</html>
