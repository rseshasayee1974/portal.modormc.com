<!--
Author: ragul-onemodo
Created: 2026-10-13 12:33:34 Asia/Calcutta (UTC+05:30)
-->
@php $printBrand = \App\Services\PrintBrandHeader::resolve(get_defined_vars()); @endphp
@if ($printBrand['logo'] || $printBrand['name'] || $printBrand['address'])
<table class="print-brand-header" style="width:100%; border-collapse:collapse; table-layout:fixed; margin:0 0 8px; page-break-inside:avoid; border:0; background:#fff; color:#172033; font-family:DejaVu Sans, sans-serif;">
    <tr>
        <td style="width:35%; border:0; padding:{{ !empty($compactBrandHeader) ? '5px 6px' : '10px 12px' }}; text-align:left; vertical-align:middle; background:#fff;">
            @if ($printBrand['logo'])
                <img src="{{ $printBrand['logo'] }}" alt="Plant logo" style="max-width:100%; max-height:{{ !empty($compactBrandHeader) ? '32' : '45' }}px; width:auto; height:auto;" />
            @endif
        </td>
        <td style="width:65%; border:0; padding:{{ !empty($compactBrandHeader) ? '5px 6px' : '10px 12px' }}; text-align:right; vertical-align:middle; background:#fff; line-height:1.3;">
            <div style="font-size:{{ !empty($compactBrandHeader) ? '9' : '12' }}px; font-weight:bold;">{{ $printBrand['name'] }}</div>
            @if ($printBrand['address'])<div style="font-size:{{ !empty($compactBrandHeader) ? '7' : '9' }}px;">{{ $printBrand['address'] }}</div>@endif
            @if ($printBrand['gstin'])<div style="font-size:{{ !empty($compactBrandHeader) ? '7' : '8' }}px;">GSTIN: {{ $printBrand['gstin'] }}</div>@endif
        </td>
    </tr>
</table>
@endif
