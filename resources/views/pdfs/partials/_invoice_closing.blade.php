{{--
Author: ragul-onemodo
Created: 2026-11-25 12:42:18 Asia/Calcutta (UTC+05:30)
--}}
<div style="padding: 6px 8px; font-size: 9pt; text-align: left;">
    @php
        $remarks = collect([$document->period, $document->notes])->map(fn ($text) => trim((string) $text))
            ->filter(fn ($text) => $text !== '')->unique();
    @endphp
    @if ($remarks->isNotEmpty())
        <strong>Notes</strong>
        @foreach ($remarks as $remark)
            <div style="overflow-wrap: break-word;">{!! nl2br(e($remark)) !!}</div>
        @endforeach
    @endif
    <div style="margin-top: 4px;">This is a computer-generated invoice and does not require a signature.</div>
</div>
@unless ($is_pdf ?? false)
    <div class="invoice-preview-footer" style="background: #eee; color: #475569; padding: 3mm 5mm; font: 8pt sans-serif;">
        {{ $document->plant?->name ?? $data['company']['name'] ?? config('app.name') }} - Copyright {{ now()->year }}
    </div>
@endunless
