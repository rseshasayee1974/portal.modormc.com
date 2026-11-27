{{--
Author: ragul-onemodo
Created: 2026-11-25 12:42:18 Asia/Calcutta (UTC+05:30)
--}}
@php
    $remarks = collect([$document->period, $document->notes])->map(fn ($text) => trim((string) $text))
        ->filter(fn ($text) => $text !== '')->unique();
@endphp
@if ($remarks->isNotEmpty())
    <div style="padding: 6px 8px; font-size: 9pt; text-align: left;">
        <strong>Notes</strong>
        @foreach ($remarks as $remark)
            <div style="overflow-wrap: break-word;">{!! nl2br(e($remark)) !!}</div>
        @endforeach
    </div>
@endif
@if ($pdfSettings['signature'] ?? true)
    @php
        $seal = ($data['company']['seal_sign_path'] ?? null) ?: $document->plant?->seal_sign_path;
        $sealSrc = null;
        if (filled($seal) && ($pdfSettings['show_seal_signature'] ?? true)) {
            $path = preg_replace('~^(?:public/|storage/)+~', '', ltrim($seal, '/'));
            $sealSrc = ($is_pdf ?? false)
                ? \Illuminate\Support\Facades\Storage::disk('public')->path($path)
                : \Illuminate\Support\Facades\Storage::disk('public')->url($path);
        }
    @endphp
    <table style="width: 100%; border-collapse: collapse; page-break-inside: avoid; font: 9pt sans-serif;">
        <tr>
            <td style="width: 50%; height: 56pt; vertical-align: bottom; padding: 6pt; text-align: center;">
                <strong>Customer Signature</strong>
            </td>
            <td style="width: 50%; height: 56pt; vertical-align: bottom; padding: 6pt; text-align: center;">
                @if ($sealSrc)
                    <div><img src="{{ $sealSrc }}" alt="Company seal and signature" style="max-height: 42pt; max-width: 110pt;"></div>
                @endif
                <strong>Authorized Signatory</strong>
            </td>
        </tr>
    </table>
@endif
@unless ($is_pdf ?? false)
    @include('pdfs.partials._invoice_page_footer', ['previewRow' => true])
@endunless
