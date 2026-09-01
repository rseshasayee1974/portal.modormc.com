<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <title>Batching Token</title>
    <style>
        @page {
            size: 80mm auto;
            margin: 0;
        }

        body {
            font-family: Consolas, "Courier New", Courier, monospace !important;
            font-size: 11.5px;
            font-weight: 800 !important;
            color: #000000 !important;
            margin: 2mm 3mm;
            width: 74mm;
            line-height: 1;
            -webkit-font-smoothing: none !important;
            -moz-osx-font-smoothing: none !important;
            font-smoothing: none !important;
            text-rendering: optimizeSpeed !important;
            background: #ffffff !important;
        }

        .header {
            text-align: center;
            margin-bottom: 4px;
        }

        .company-name {
            font-size: 12px;
            font-weight: bold;
            text-transform: uppercase;
            color: #000000 !important;
            margin-bottom: 2px;
            letter-spacing: 0.5px;
        }

        .company-address {
            font-size: 10px;
            font-weight: bold;
            color: #000000 !important;
            line-height: 1;
        }

        .token-title {
            font-size: 12px;
            font-weight: bold;
            border-top: 1px dashed #000;
            border-bottom: 1px dashed #000;
            padding: 3px 0;
            margin: 4px 0;
        }

        .meta-table {
            width: 100%;
            border-collapse: collapse;
        }

        .meta-table td {
            padding: 3px 0;
            vertical-align: middle;
            color: #000000 !important;
        }

        .meta-label {
            font-weight: bold;
            text-align: left;
            width: 38%;
            font-size: 10.5px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .meta-value {
            text-align: right;
            width: 62%;
            word-wrap: break-word;
            font-weight: 900;
            font-size: 10.5px;
        }

        .font-mono {
            font-family: Consolas, "Courier New", Courier, monospace !important;
            font-weight: 900;
        }

        .divider {
            border-top: 1px dashed #000;
            margin: 4px 0;
        }

        .materials-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 4px;
        }

        .materials-table th {
            border-bottom: 1px dashed #000000 !important;
            text-align: left;
            font-weight: bold;
            font-size: 10px;
            color: #000000 !important;
            padding-bottom: 4px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .materials-table td {
            padding: 2px 0;
            font-size: 10px;
            font-weight: bold;
            color: #000000 !important;
            border-bottom: 1px dashed #000000 !important;
        }

        .materials-table tr:last-child td {
            border-bottom: none;
        }

        .text-right {
            text-align: right;
        }

        .footer {
            text-align: center;
            margin-top: 12px;
            font-size: 10px;
            border-top: 2px dashed #000000 !important;
            padding-top: 8px;
            color: #000000 !important;
            font-weight: bold;
        }

        /* Preview Toolbar styles (only for browser view) */
        .preview-toolbar {
            background: #f3f4f6;
            padding: 6px 10px;
            margin: -2mm -3mm 6px -3mm;
            border-bottom: 1px solid #d1d5db;
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif;
        }

        .preview-toolbar table {
            width: 100%;
            border-collapse: collapse;
        }

        .preview-toolbar td {
            vertical-align: middle;
        }

        .preview-toolbar a,
        .preview-toolbar button {
            background: #fff;
            border: 1px solid #d1d5db;
            /* padding: 4px 8px; */
            border-radius: 4px;
            cursor: pointer;
            text-decoration: none;
            color: #374151;
            font-size: 11px;
            font-weight: 600;
            display: inline-block;
        }

        .preview-toolbar button.primary {
            background: #4f46e5;
            color: #fff;
            border-color: #4f46e5;
        }

        @media print {
            .preview-toolbar {
                display: none !important;
            }

            body {
                margin: 0 3mm !important;
                width: 74mm !important;
            }
        }
    </style>
</head>

<body>
    {{-- @if (!empty($isPreview))
        <div class="preview-toolbar">
            <table>
                <tr>
                    <td style="text-align: left;">
                        <a href="{{ route('batches.index') }}">← Back</a>
                    </td>
                    <td style="text-align: right;">
                        <button onclick="window.print()" class="primary">Print Token</button>
                        <a href="{{ route('batches.token.download', $batch->encrypted_id ?? $batch->id) }}">Download PDF</a>
                    </td>
                </tr>
            </table>
        </div>
    @endif --}}

    <div class="header">
        @if ($batch->salesOrder?->plant?->logo_path)
            <div style="text-align: center; margin-bottom: 6px;">
                @php
                    $cleanLogoPath = ltrim(
                        str_replace(['public/', 'storage/', '/storage/'], '', $batch->salesOrder->plant->logo_path),
                        '/',
                    );
                    $logoUrl = !empty($isPreview)
                        ? asset('storage/' . $cleanLogoPath)
                        : public_path('storage/' . $cleanLogoPath);
                @endphp
                <img src="{{ $logoUrl }}" style="max-height: 50px; max-width: 150px; object-fit: contain;" />
            </div>
        @endif
        <div class="company-name">{{ $batch->salesOrder?->plant?->name }}</div>
        @if ($batch->salesOrder?->plant && $batch->salesOrder->plant->addresses->isNotEmpty())
            @php $plAddr = $batch->salesOrder->plant->addresses->first(); @endphp
            <div class="company-address">
                {{ $plAddr->line_1 ?? '' }}, {{ $plAddr->city ?? '' }},
                {{ $plAddr->state->state_name ?? ($plAddr->state_code ?? '') }} - {{ $plAddr->zipcode ?? '' }}
            </div>
        @endif
        <div class="token-title">BATCHING TOKEN</div>
    </div>

    <table class="meta-table">
        <tr>
            <td class="meta-label">Token No:</td>
            <td class="meta-value font-mono">B{{ $batch->batch_no }}</td>
        </tr>
        <tr>
            <td class="meta-label">Date/Time:</td>
            <td class="meta-value font-mono">
                {{ optional($batch->load_time ?? $batch->created_at)->format('d-m-Y H:i') }}</td>
        </tr>
        {{-- <tr>
            <td class="meta-label">Shift:</td>
            <td class="meta-value">{{ $batch->shift ?? '-' }}</td>
        </tr> --}}
        <tr>
            <td class="meta-label">Operator:</td>
            <td class="meta-value">{{ $batch->operator?->label ?? '-' }}</td>
        </tr>
    </table>

    <div class="divider"></div>

    <table class="meta-table">
        <tr>
            <td class="meta-label">Customer:</td>
            <td class="meta-value">{{ $batch->salesOrder?->customer?->legal_name ?? '-' }}</td>
        </tr>
        <tr>
            <td class="meta-label">Site:</td>
            <td class="meta-value">{{ $batch->salesOrder?->site?->name ?? '-' }}</td>
        </tr>
        <tr>
            <td class="meta-label">Order No:</td>
            <td class="meta-value font-mono">{{ $batch->salesOrder?->order_no ?? '-' }}</td>
        </tr>
    </table>

    <div class="divider"></div>

    <table class="meta-table">
        <tr>
            <td class="meta-label">Recipe:</td>
            <td class="meta-value">
                {{ $batch->salesOrder?->mixDesign?->concrete_grade?->name ?? ($batch->salesOrder?->mixDesign?->design_name ?? '-') }}
            </td>
        </tr>
        <tr>
            <td class="meta-label">Code:</td>
            <td class="meta-value font-mono">{{ $batch->salesOrder?->mixDesign?->design_code ?? '-' }}</td>
        </tr>
        <tr>
            <td class="meta-label">Batch Size:</td>
            <td class="meta-value font-mono" style="font-weight: bold;">
                {{ number_format((float) $batch->batch_size, 2) }} m³</td>
        </tr>
    </table>

    <div class="divider"></div>

    <table class="meta-table">
        <tr>
            <td class="meta-label">Truck No:</td>
            <td class="meta-value font-mono" style="font-weight: bold;">
                {{ $batch->dispatches->first()?->truck?->registration ?? '-' }}</td>
        </tr>
        <tr>
            <td class="meta-label">Driver:</td>
            <td class="meta-value">
                {{ trim(($batch->dispatches->first()?->driver?->first_name ?? '') . ' ' . ($batch->dispatches->first()?->driver?->last_name ?? '')) ?: '-' }}
            </td>
        </tr>
        @if ($batch->dispatches->first()?->transport?->legal_name)
            <tr>
                <td class="meta-label">Transporter:</td>
                <td class="meta-value">{{ $batch->dispatches->first()->transport->legal_name }}</td>
            </tr>
        @endif
        @if ($batch->dispatches->first()?->loadSite?->name)
            <tr>
                <td class="meta-label">Load Site:</td>
                <td class="meta-value">{{ $batch->dispatches->first()->loadSite->name }}</td>
            </tr>
        @endif
        @if ($batch->dispatches->first()?->salesExecutive?->label)
            <tr>
                <td class="meta-label">Sales Exec:</td>
                <td class="meta-value">{{ $batch->dispatches->first()->salesExecutive->label }}</td>
            </tr>
        @endif
        @php
            $emptyWeight = (float) ($batch->dispatches->first()?->empty_weight_truck ?? 0);
            $emptyWeightStr = number_format($emptyWeight, 2) . ' MTR';
        @endphp
        <tr>
            <td class="meta-label">Empty Wt:</td>
            <td class="meta-value font-mono">{{ $emptyWeightStr }}</td>
        </tr>
        <tr>
            <td class="meta-label">Empty Time:</td>
            <td class="meta-value font-mono">
                {{ $batch->dispatches->first()?->empty_time ? \Carbon\Carbon::parse($batch->dispatches->first()?->empty_time)->format('d-m-Y H:i') : '-' }}
            </td>
        </tr>
    </table>

    @if ($batch->salesOrder?->mixDesign?->items?->count() > 0)
        <div class="divider"></div>
        <div
            style="font-weight: 800; text-align: center; margin-bottom: 4px; font-size: 11px; color: #000000 !important; letter-spacing: 0.05em;">
            TARGET MATERIALS (per m³)</div>
        <table class="materials-table">
            <thead>
                <tr>
                    <th>Material</th>
                    <th class="text-right">Qty (kg/m³)</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($batch->salesOrder->mixDesign->items as $item)
                    <tr>
                        <td>{{ $item->product?->title ?? 'Material' }}</td>
                        <td class="text-right font-mono">{{ number_format((float) $item->actual_quantity, 2) }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif

    <div class="footer">
        <div>Batching Token - v1</div>
        <div class="font-mono" style="margin-top: 2px;">Date Printed: {{ now()->format('d-m-Y H:i:s') }}</div>
        <div style="margin-top: 4px; font-weight: bold;">Thank you!</div>
    </div>
</body>

</html>
