<!--
Author: ragul-onemodo
Created: 2026-10-13 12:33:34 Asia/Calcutta (UTC+05:30)
-->
@php
    $printBrand = \App\Services\PrintBrandHeader::resolve(get_defined_vars());
    $invoiceBrandHeader = !empty($brandInvoiceTitle);
    $logoSize = 'max-width:100%; max-height:' . (!empty($compactBrandHeader) ? '32' : '45') . 'px; width:auto; height:auto;';
    if ($invoiceBrandHeader && $printBrand['logo']) {
        // Fit the logo inside a larger box without stretching wide or tall images.
        $logoSize = 'max-width:176px; max-height:96px; width:auto; height:auto;';
        $dimensions = @getimagesize($printBrand['logo']);
        if ($dimensions && $dimensions[0] > 0 && $dimensions[1] > 0) {
            $scale = min(176 / $dimensions[0], 96 / $dimensions[1]);
            $logoSize = 'width:' . round($dimensions[0] * $scale, 2) . 'px; height:auto; max-width:100%;';
        }
    }
    $brandPadding = !empty($compactBrandHeader) ? '5px 6px' : '10px 12px';
@endphp
@if ($invoiceBrandHeader || $printBrand['logo'] || $printBrand['name'] || $printBrand['address'])
<table class="print-brand-header" style="width:100%; border-collapse:collapse; table-layout:fixed; margin:{{ $invoiceBrandHeader ? '0' : '0 0 8px' }}; page-break-inside:avoid; border:0; {{ $invoiceBrandHeader ? 'border-bottom:1px solid #000;' : '' }} background:#fff; color:#172033; font-family:DejaVu Sans, sans-serif;">
    <tr>
        @if (!$invoiceBrandHeader || $printBrand['logo'])
        <td style="width:35%; border:0; padding:{{ $brandPadding }}; text-align:left; vertical-align:middle; background:#fff;">
            @if ($printBrand['logo'])
                <img src="{{ $printBrand['logo'] }}" alt="Plant logo" style="{{ $logoSize }}" />
            @endif
        </td>
        @endif
        <td style="width:{{ $invoiceBrandHeader && !$printBrand['logo'] ? '100%' : '65%' }}; border:0; padding:{{ $brandPadding }}; text-align:right; vertical-align:middle; background:#fff; line-height:1.3;">
            <div style="font-size:{{ !empty($compactBrandHeader) ? '9' : '12' }}px; font-weight:bold;">{{ $printBrand['name'] }}</div>
            @if ($printBrand['address'])<div style="font-size:{{ !empty($compactBrandHeader) ? '7' : '9' }}px;">{{ $printBrand['address'] }}</div>@endif
            @if ($printBrand['gstin'])<div style="font-size:{{ !empty($compactBrandHeader) ? '7' : '8' }}px;">GSTIN: {{ $printBrand['gstin'] }}</div>@endif
            @if ($invoiceBrandHeader)
                <div style="margin-top:6px; color:#000; text-transform:uppercase;">
                    @if (!empty($brandInvoiceCopy))<div style="font-size:9pt;">{{ $brandInvoiceCopy }}</div>@endif
                    <div style="font-size:15pt; font-weight:bold;">{{ $brandInvoiceTitle }}</div>
                </div>
            @endif
        </td>
    </tr>
</table>
@endif
