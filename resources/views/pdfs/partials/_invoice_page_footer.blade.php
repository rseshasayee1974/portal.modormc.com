{{--
Author: ragul-onemodo
Created: 2026-11-25 12:42:18 Asia/Calcutta (UTC+05:30)
--}}
@php
    $copyright = ($document->plant?->name ?? $data['company']['name'] ?? config('app.name')) . ' - Copyright ' . now()->year;
    $footerText = blank($data['company']['seal_sign_path'] ?? null) && blank($document->plant?->seal_sign_path)
        ? 'This is a computer-generated invoice and does not require a signature.   |   ' . $copyright
        : $copyright;
@endphp
@if ($previewRow ?? false)
    <div class="invoice-preview-footer" style="background: #eee; color: #475569; padding: 3mm 5mm; font: 8pt sans-serif; text-align: left;">{{ $footerText }}</div>
@else
<style>
    @page { margin-bottom: 13mm !important; }
    @if ($is_pdf ?? false)
        html { margin-bottom: 13mm !important; }
        .invoice-page-footer {
            position: fixed;
            bottom: -10mm;
            left: 0;
            right: 0;
            padding: 2mm 5mm;
            background: #eee;
            color: #475569;
            font: 8pt sans-serif;
            text-align: left;
        }
    @else
        @media print {
            @page {
                @bottom-center {
                    content: "{!! str_replace(['\\', '"', '<', "\r", "\n"], ['\\\\', '\\"', '\\3c ', ' ', ' '], $footerText) !!}";
                    width: 100%;
                    height: 9mm;
                    padding-left: 5mm;
                    background: #eee;
                    color: #475569;
                    font: 8pt sans-serif;
                    text-align: left;
                }
            }
            .invoice-preview-footer { display: none; }
        }
    @endif
</style>
@if ($is_pdf ?? false)
    <div class="invoice-page-footer">{{ $footerText }}</div>
@endif
@endif
