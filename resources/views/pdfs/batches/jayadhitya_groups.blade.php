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
    $format = fn ($value, $column) => $value === null ? '-' : number_format((float) $value, $column === 'Sil' ? 1 : (in_array($column, ['ADMIX 1', 'ADMIX 2', 'Adm 3', 'Adm 4'], true) ? 2 : ((float) $value == floor((float) $value) ? 0 : 2)), '.', '');
    $left = [['Batch Number', $batch->batch_no], ['Batch Date', $batchDate?->format('d-m-Y')],
        ['Batch Start Time', $batch->start_time?->format('H:i:s')], ['Batch End Time', $batch->end_time?->format('H:i:s')],
        ['Customer', $order?->customer?->legal_name], ['Site', $order?->site?->name]];
    $middle = [['Recipe Name', $mix?->design_name ?? $mix?->grade], ['Recipe Code', $mix?->design_code],
        ['Truck No', $dispatch?->truck?->registration], ['Truck Driver', trim(($driver?->first_name ?? '').' '.($driver?->last_name ?? ''))],
        ['Adj / Manual Qty', null]];
    $right = [['Mixer Capacity', isset($plant->mixer_capacity) ? number_format($plant->mixer_capacity, 2) : null],
        ['Batch Size', number_format((float) $batch->batch_size, 4)], ['Production Qty', number_format((float) $batch->batch_size, 2)],
        ['Order No', $order?->order_no], ['Ordered Qty', isset($order->total_qty) ? number_format((float) $order->total_qty, 2) : null],
        ['With This Load', number_format((float) $batch->batch_size, 2)]];
    $columnLabels = ['MSAND 1' => "MSA\nND 1", 'MSAND 2' => "MSA\nND 2", '20MM' => "20M\nM", '12MM' => "12M\nM",
        'CEMENT 1' => "CEM\nENT 1", 'CEMENT 2' => "CEM\nENT 2", 'CEMENT 3' => "CEM\nENT 3", 'WATER' => "WAT\nER",
        'ADMIX 1' => "ADMI\nX 1", 'ADMIX 2' => "ADMI\nX 2"];
    $logo = '<svg viewBox="0 0 38 44" aria-hidden="true"><polygon points="14,0 38,0 22,44 0,44" fill="#6cb52d"/><polygon points="10,10 22,10 8,44 0,44" fill="#1a1a1a" opacity=".85"/></svg>';
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
  .page { width: 900px; min-height: 1273px; margin: 16px auto; background: #fff; padding: 22px 28px; position: relative; font-size: 16px; }
  .scroll { overflow-x: auto; }

  /* header */
  .head { display: flex; justify-content: space-between; align-items: flex-start; }
  .head .mid { text-align: center; padding-top: 2px; }
  .head .mid b { display: block; font-size: 15px; letter-spacing: .2px; }
  .head .mid b + b { margin-top: 18px; }
  .logo { display: flex; align-items: center; gap: 6px; }
  .logo svg { width: 38px; height: 44px; }
  .logo .t { font-family: Arial, Helvetica, sans-serif; font-weight: 800; line-height: .95; font-size: 17px; }
  .logo .t span { display: block; font-weight: 600; font-size: 17px; }
  .logo.sm svg { width: 30px; height: 34px; } .logo.sm .t { font-size: 13px; } .logo.sm .t span { font-size: 13px; }

  .rule { border-top: 1.5px solid #000; border-bottom: 1.5px solid #000; margin-top: 14px; }
  .plant { display: flex; justify-content: space-between; font-size: 20px; padding: 0 0 2px; }
  .plant span.v { font-weight: 400; } .plant b { font-weight: 700; }
  .plant .c { padding-left: 120px; } 

  /* info block */
  .info { display: grid; grid-template-columns: 300px 274px 1fr; font-size: 16px; line-height: 24px; border-bottom: 1.5px solid #000; padding-bottom: 6px; }
  .info .r { display: grid; grid-template-columns: 128px minmax(0, 1fr); }
  .info .r b { font-weight: 700; white-space: nowrap; } .info .r.l2 { grid-template-columns: 150px minmax(0, 1fr); }
  .info .col3 .r { grid-template-columns: 124px 1fr; }

  /* table */
  table.b { width: 100%; border-collapse: collapse; font-size: 15.5px; margin-top: 2px; }
  table.b th, table.b td { text-align: center; padding: 3px 2px; white-space: nowrap; }
  table.b th { font-size: 12.5px; line-height: 13px; font-weight: 700; vertical-align: bottom; }
  table.b tr.grp th { font-size: 16px; border-bottom: 1.5px solid #000; padding: 2px 0; }
  table.b tr.cols th { border-bottom: 1.5px solid #000; padding-bottom: 4px; }
  table.b td.num { text-align: center; }
  table.b td.sil, table.b th.sil { text-align: right; padding-right: 6px; }
  table.b tr.label td { text-align: left; font-weight: 700; font-size: 15px; padding: 5px 0 2px; }
  table.b tr.note td { text-align: right; font-weight: 700; font-size: 15px; padding: 2px 2px 4px; }
  table.b tr.note.top td { padding-top: 0; }
  table.b tr.data td { padding: 3px 2px; }
  table.b tr.data.tight td { padding: 2px; }
  table.b tr.set td, table.b tr.act td { border-top: 0; }

  /* footer */
  .foot { position: absolute; left: 28px; right: 28px; bottom: 22px; display: flex; justify-content: space-between; align-items: flex-end; font-weight: 700; font-size: 15.5px; }
  .foot .logo small { display: block; font-size: 8px; font-family: Arial, sans-serif; font-weight: 700; margin-top: 3px; }

  @media print {
    body { background: #fff; }
    .page { margin: 0; width: 210mm; min-height: 297mm; box-shadow: none; }
    .scroll { overflow: visible; }
  }
  @media (max-width: 940px) { .page { margin: 0; } }
</style>
<style>
  /* Uniform A4 scaling retains the 900px source canvas without clipping its 844px inner grid. */
  @media print {
    .page { width: 900px; min-height: 1273px; margin: 0; zoom: 0.8817; }
  }
</style>
</head>
<body>
<div class="page">
  <div class="scroll"><div style="min-width:844px">
  <div class="head">
    <div class="logo" id="logoL">{!! $logo !!}<div class="t">SCHWING<span>Stetter</span></div></div>
    <div class="mid"><b id="company">{{ $plant?->name }}</b><b>BATCH SHEET REPORT</b></div>
    <div class="logo" id="logoR">{!! $logo !!}<div class="t">SCHWING<span>Stetter</span></div></div>
  </div>
  <div class="rule"></div>
  <div class="plant">
    <div><b>Plant Type :</b> <span class="v" id="plantType">{{ $value($plant?->plant_type) }}</span></div>
    <div><b>Plant Sl.No :</b> <span class="v" id="plantSl">{{ $value($plant?->code) }}</span></div>
  </div>
  <div style="border-top:1.5px solid #000"></div>
  <div class="info" id="info">
    @foreach ([$left, $middle, $right] as $index => $list)
      <div class="{{ $index === 1 ? 'col2' : ($index === 2 ? 'col3' : '') }}">
        @foreach ($list as [$key, $entry])
          <div class="r {{ $index === 1 ? 'l2' : '' }}"><b>{{ $key }}</b><span>: {{ $value($entry) }}</span></div>
        @endforeach
      </div>
    @endforeach
  </div>
  <table class="b" id="tbl">
    <tr class="grp">@foreach ($report['groups'] as $group => $columns)<th colspan="{{ count($columns) }}" @if ($loop->last) class="sil" @endif>{{ $group }}</th>@endforeach</tr>
    <tr class="cols">@foreach ($report['columns'] as $column)<th class="{{ $loop->last ? 'sil' : '' }}">{!! nl2br(e($report['labels'][$column] ?? $columnLabels[$column] ?? $column)) !!}</th>@endforeach</tr>
    @foreach ($report['recipe_rows'] as $row)
      <tr class="data" data-row="recipe">@foreach ($report['columns'] as $column)<td class="{{ $loop->last ? 'sil' : 'num' }}">{{ $format($row === null ? null : ($row[$column] ?? 0), $column) }}</td>@endforeach</tr>
    @endforeach
    @foreach ($report['tolerance_rows'] as $row)
      <tr class="data tight" data-row="tolerance">
        @foreach ($row as $reading)<td class="num">{{ $reading === null ? '-' : number_format($reading, 2, '.', '') }}</td>@endforeach
        <td colspan="14" @if ($loop->last) style="text-align:right;font-weight:700;padding-right:2px" @endif>@if ($loop->last)Mass of Recipe Targets in kg : {{ $report['recipe'] ? number_format(array_sum($report['recipe']), 2, '.', '') : '-' }}@endif</td>
      </tr>
    @endforeach
    <tr class="label"><td colspan="20">Actual Values in kg</td></tr>
    @foreach ($report['runs'] as $row)
      <tr class="data" data-row="actual">@foreach ($report['columns'] as $column)<td class="{{ $loop->last ? 'sil' : 'num' }}">{{ $format($row === null ? null : ($row[$column] ?? 0), $column) }}</td>@endforeach</tr>
    @endforeach
    <tr class="label"><td colspan="20">Total Set Weight in kg</td></tr>
    <tr class="data">@foreach ($report['columns'] as $column)<td class="{{ $loop->last ? 'sil' : 'num' }}">{{ $format($report['targets'][$column] ?? 0, $column) }}</td>@endforeach</tr>
    <tr class="note top"><td colspan="20">Mass of Total Set Weight in kg : {{ number_format($report['total_target'], 2, '.', '') }}</td></tr>
    <tr class="label"><td colspan="20">Total Actual Weight in kg</td></tr>
    <tr class="data">@foreach ($report['columns'] as $column)<td class="{{ $loop->last ? 'sil' : 'num' }}">{{ $format($report['actuals'][$column] ?? 0, $column) }}</td>@endforeach</tr>
    <tr class="note top"><td colspan="20">Mass of Total Actual Weight in kg : {{ number_format($report['total_actual'], 2, '.', '') }}</td></tr>
    <tr class="label"><td colspan="20">Difference in Percentage</td></tr>
    <tr class="data">@foreach ($report['columns'] as $column)
      @php $target = $report['targets'][$column] ?? 0; $actual = $report['actuals'][$column] ?? 0; @endphp
      <td class="{{ $loop->last ? 'sil' : 'num' }}">{{ number_format($target != 0 ? ($actual - $target) / $target * 100 : 0, 2, '.', '') }}</td>
    @endforeach</tr>
    <tr class="note top"><td colspan="20">Total Mass Difference in Percentage : {{ number_format($report['difference'], 2, '.', '') }}</td></tr>
  </table>
  </div></div>
  <div class="foot">
    <div class="logo sm" id="logoF">{!! $logo !!}<div class="t">SCHWING<span>Stetter</span><small>SCHWING EWIG Control System</small></div></div>
    <div style="text-align:center">Page <span id="pg">1</span></div>
    <div>Date: <span id="printDate">{{ now()->format('d-m-Y g:i:s A') }}</span></div>
  </div>
</div>
</body>
</html>
