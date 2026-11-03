<!--
Author: ragul-onemodo
Created: 2026-11-02 16:46:39 Asia/Calcutta (UTC+05:30)
-->
@php
    $report = \App\Services\JayadhityaPlant2787BatchSheet::data($batch);
    $order = $batch->salesOrder;
    $plant = $batch->plant ?? $order?->plant;
    $mix = $order?->mixDesign;
    $dispatch = $batch->dispatches->first();
    $driver = $dispatch?->driver;
    $date = $batch->start_time ?? $batch->created_at;
    $value = fn ($v) => $v !== null && $v !== '' ? $v : '-';
    $quantity = fn ($v) => $v === null ? '-' : number_format((float) $v, 2, '.', '');
    $left = [['Batch Number / Docket Number', $batch->batch_no], ['Customer', $order?->customer?->legal_name],
        ['Site', $order?->site?->name], ['Recipe Code', $mix?->design_code], ['Recipe Name', $mix?->design_name],
        ['Truck Number', $dispatch?->truck?->registration], ['Truck Driver', trim(($driver?->first_name ?? '').' '.($driver?->last_name ?? ''))],
        ['Order Number', $order?->full_number ?: $order?->order_no], ['Batcher Name', $batch->operator?->full_name]];
    $right = [['Ordered Quantity', $order?->total_qty, 'M3'], ['Production Quantity', $batch->batch_size, 'M3'],
        ['Adj/Manual Quantity', null, 'M3'], ['With This Load', $report['with_this_load'], 'M3'],
        ['Mixer Capacity', $plant?->mixer_capacity, 'M3'],
        ['Batch Size', $report['inferred_runs'] ? null : $report['run_size'], 'M3'],
        ['Net Wt From W.Bridge', $dispatch?->net_weight, 'Kg']];

@endphp
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<title>Docket / Batch Report / Autographic Record</title>
<style>
@page { size: A4 portrait; margin: 0; }
* { box-sizing: border-box; }
body { margin: 0; background: #e5e5e5; font-family: "Times New Roman", Times, serif; color: #000; }
.page { width: 210mm; min-height: 297mm; margin: 10px auto; background: white; padding: 10mm 6mm 8mm; font-size: 10pt; }
.company { text-align: center; font-weight: bold; font-size: 15pt; }
.system { margin: 9px 0 0 14mm; font-weight: bold; font-size: 12.5pt; }
.system table { border-collapse: collapse; }
.system td { vertical-align: middle; padding: 0 6px 0 0; }
.logo { width: 34px; height: 34px; }
.brand { margin: 5px 0 0 14mm; line-height: 1.15; }
.title { text-align: center; font-weight: bold; font-size: 12pt; margin: 6px 0 10px; }
.top { width: 100%; border-collapse: collapse; }
.top td { padding: 0; vertical-align: top; }
.times { border-collapse: collapse; line-height: 1.2; }
.times th { width: 31mm; text-align: left; white-space: nowrap; }
.times td { white-space: nowrap; }
.serial { text-align: right; padding-right: 4mm !important; }
.info { width: 100%; border-collapse: collapse; margin-top: 4px; table-layout: fixed; }
.info td { padding: 1px 0; vertical-align: top; }
.info .key { font-weight: bold; width: 50mm; }
.info .sep { width: 3mm; text-align: center; }
.info .val { width: 72mm; padding-right: 4mm; overflow-wrap: anywhere; }
.info .rkey { font-weight: bold; width: 39mm; }
.info .num { width: 14mm; text-align: right; }
.info .unit { width: 9mm; padding-left: 2mm; }
table.batch { width: 100%; border-collapse: collapse; table-layout: fixed; margin-top: 4px; font-size: 8.4pt; }
table.batch th, table.batch td { padding: 1px 2px; text-align: center; overflow-wrap: anywhere; }
table.batch thead .group th { font-size: 9pt; text-align: center; padding-bottom: 3px; }
table.batch thead .heads th { font-weight: normal; white-space: pre-wrap; }
table.batch td { font-variant-numeric: tabular-nums; }
table.batch .label td { text-align: left; font-size: 9.2pt; padding-top: 2px; }
table.batch .rule td { padding: 0; height: 2px; border-top: 1px solid black; }
table.batch .gap td { height: 6px; }
table.batch tr { break-inside: avoid; }
.note { font-size: 8pt; margin: 8px 0; }
.toolbar { text-align: center; padding: 10px; }
@media print { body { background: white; } .page { margin: 0; } .toolbar { display: none; } }
</style>
</head>
<body>
@if ($isPreview ?? false)<div class="toolbar"><button type="button" onclick="window.print()">Print batch sheet</button></div>@endif
<div class="page">
  <div class="company">{{ $plant?->name }}</div>
  <div class="system"><table><tr><td><svg class="logo" viewBox="0 0 46 46"><rect x="1" y="1" width="44" height="44" fill="#fff" stroke="#888"/><polygon points="20,3 33,3 20,43 7,43" fill="#00e000"/><polygon points="35,3 43,3 30,43 22,43" fill="#00a800"/></svg></td><td>MCI 370 Control System Ver 1.0</td></tr></table></div>
  <div class="brand">SCHWING<br>Stetter</div>
  <div class="title">Docket / Batch Report / Autographic Record</div>
  <table class="top"><tr><td><table class="times">
    <tr><th>Batch Date</th><td>: {{ $date?->format('d-M-Y') }}</td></tr>
    <tr><th>Batch Start Time</th><td>: {{ $batch->start_time?->format('h:i:s A') }}</td></tr>
    <tr><th>Batch End Time</th><td>: {{ $batch->end_time?->format('h:i:s A') }}</td></tr>
  </table></td><td class="serial"><b>Plant Serial Number:</b>&nbsp; 2787</td></tr></table>
  <table class="info">
    @foreach ($left as $index => [$label, $val])
    <tr><td class="key">{{ $label }}</td><td class="sep">:</td><td class="val">{{ $value($val) }}</td>
      @if (isset($right[$index]))
      <td class="rkey">{{ $right[$index][0] }}</td><td class="sep">:</td><td class="num">{{ $quantity($right[$index][1]) }}</td><td class="unit">{!! $right[$index][2] === 'M3' ? 'M<sup>3</sup>' : 'Kg' !!}</td>
      @else <td colspan="4"></td> @endif
    </tr>
    @endforeach
  </table>
  @if ($report['slots'])
  @php
      $slots = $report['slots'];
      $count = count($slots);
      $groups = $report['print_groups'];
  @endphp
  <table class="batch">
    <thead><tr class="group">@foreach ($groups as $group)<th colspan="{{ $group['span'] }}">{{ $group['name'] }}</th>@endforeach</tr>
    <tr class="heads">@foreach ($slots as [$column, $label])<th>{{ $label }}</th>@endforeach</tr></thead>
    <tbody>
      @if (!$report['inferred_runs'])
      <tr class="label"><td colspan="{{ $count }}">Targets based on batchsize in Kgs.</td></tr>
      <tr data-row="target">@foreach ($report['target_cells'] as $cell)<td>{{ $cell }}</td>@endforeach</tr>
      <tr class="rule"><td colspan="{{ $count }}"></td></tr>
      <tr class="label"><td colspan="{{ $count }}">Actual in Kgs.</td></tr>
      @foreach ($report['actual_cells'] as $cells)<tr data-row="actual">@foreach ($cells as $cell)<td>{{ $cell }}</td>@endforeach</tr>@endforeach
      <tr class="rule"><td colspan="{{ $count }}"></td></tr>
      @endif
      <tr class="label"><td colspan="{{ $count }}">Total Set Weight in Kgs.</td></tr>
      <tr data-row="total-set">@foreach ($report['set_cells'] as $cell)<td>{{ $cell }}</td>@endforeach</tr>
      <tr class="gap"><td colspan="{{ $count }}"></td></tr>
      <tr class="label"><td colspan="{{ $count }}">Total Actual in Kgs.</td></tr>
      <tr data-row="total-actual">@foreach ($report['total_cells'] as $cell)<td>{{ $cell }}</td>@endforeach</tr>
      <tr class="rule"><td colspan="{{ $count }}"></td></tr>
    </tbody>
  </table>
  @else
  <p class="note">No material readings are recorded for this batch.</p>
  @endif
  @if ($report['inferred_runs'])<p class="note">Stored load totals shown. Individual mixer-cycle readings are unavailable.</p>@endif
</div>
</body>
</html>
