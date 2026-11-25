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
@unless ($is_pdf ?? false)
    @include('pdfs.partials._invoice_page_footer', ['previewRow' => true])
@endunless
