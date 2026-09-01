<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <title>Gate Pass</title>
    <style>
        @page {
            size: 80mm auto;
            margin: 0;
        }

        body {
            font-family: Consolas, "Courier New", Courier, monospace !important;
            font-size: 11px;
            font-weight: 800 !important;
            color: #000000 !important;
            margin: 2mm 3mm;
            width: 74mm;
            line-height: 1.35;
            -webkit-font-smoothing: none !important;
            -moz-osx-font-smoothing: none !important;
            text-rendering: optimizeSpeed !important;
            background: #ffffff !important;
        }

        /* ── Header ── */
        .header { text-align: center; margin-bottom: 4px; }

        .company-name {
            font-size: 13px; font-weight: bold; text-transform: uppercase;
            color: #000 !important; margin-bottom: 2px; letter-spacing: 0.5px;
        }

        .company-address { font-size: 9px; color: #000 !important; line-height: 1.25; }

        /* ── Document Title Band ── */
        .token-title {
            font-size: 13px; font-weight: bold; text-align: center;
            border-top: 2px solid #000000 !important;
            border-bottom: 2px solid #000000 !important;
            padding: 4px 0; margin: 5px 0;
            color: #000000 !important; letter-spacing: 2px;
        }

        /* ── Gate Pass Number ── */
        .pass-number-row { text-align: center; margin: 3px 0 5px 0; }
        .pass-number-label { font-size: 9px; text-transform: uppercase; color: #000 !important; letter-spacing: 0.5px; }
        .pass-number-value { font-size: 20px; font-weight: 900; letter-spacing: 2px; color: #000 !important; }

        /* ── Meta Table ── */
        .meta-table { width: 100%; border-collapse: collapse; }
        .meta-table td { padding: 2.5px 0; vertical-align: middle; color: #000 !important; }
        .meta-label { font-weight: bold; text-align: left; width: 38%; font-size: 9.5px; text-transform: uppercase; letter-spacing: 0.3px; }
        .meta-colon { width: 4%; text-align: center; font-weight: bold; }
        .meta-value { text-align: right; width: 58%; word-wrap: break-word; font-weight: 900; font-size: 10.5px; }
        .font-mono { font-family: Consolas, "Courier New", Courier, monospace !important; font-weight: 900; }

        /* ── Section Title ── */
        .section-title {
            font-size: 9px; font-weight: bold; text-transform: uppercase;
            letter-spacing: 0.8px; text-align: center; margin: 3px 0 2px 0; color: #000 !important;
        }

        /* ── Weight Box ── */
        .weight-box { border: 1.5px solid #000 !important; padding: 3px 4px; margin: 4px 0; }
        .weight-box table { width: 100%; border-collapse: collapse; }
        .weight-box td { padding: 2px 0; font-size: 10px; color: #000 !important; }
        .weight-box .net-row td { font-size: 12px; font-weight: 900; padding-top: 4px; border-top: 1px dashed #000 !important; }
        .weight-label { text-align: left; font-weight: bold; text-transform: uppercase; font-size: 9px; }
        .weight-value { text-align: right; font-family: Consolas, "Courier New", Courier, monospace !important; font-weight: 900; }

        /* ── QR Code ── */
        .qr-section { text-align: center; margin: 5px 0 3px 0; }
        .qr-label { font-size: 8px; text-transform: uppercase; letter-spacing: 0.5px; color: #000 !important; margin-top: 3px; font-weight: bold; }
        .qr-img { display: inline-block; width: 100px; height: 100px; image-rendering: pixelated; }

        /* ── Signature Lines ── */
        .sig-table { width: 100%; border-collapse: collapse; margin-top: 10px; }
        .sig-table td { padding: 0 3px; vertical-align: bottom; color: #000 !important; }
        .sig-line { border-top: 1px solid #000 !important; text-align: center; font-size: 8px; font-weight: bold; padding-top: 3px; text-transform: uppercase; letter-spacing: 0.3px; }

        /* ── Dividers ── */
        .divider { border-top: 1px dashed #000 !important; margin: 5px 0; }
        .divider-solid { border-top: 1px solid #000 !important; margin: 5px 0; }

        /* ── Footer ── */
        .footer {
            text-align: center; margin-top: 10px; font-size: 9px;
            border-top: 1.5px dashed #000 !important;
            padding-top: 6px; color: #000 !important; font-weight: bold;
        }

        /* ── Materials Table ── */
        .materials-table { width: 100%; border-collapse: collapse; margin-top: 4px; }
        .materials-table th { border-bottom: 1px dashed #000 !important; text-align: left; font-weight: 900; font-size: 9px; color: #000 !important; padding-bottom: 3px; text-transform: uppercase; letter-spacing: 0.3px; }
        .materials-table td { padding: 2.5px 0; font-size: 9.5px; font-weight: bold; color: #000 !important; border-bottom: 1px dashed #000 !important; }
        .materials-table tr:last-child td { border-bottom: none; }
        .text-right { text-align: right; }

        @media print {
            body { margin: 0 3mm !important; width: 74mm !important; }
        }
    </style>
</head>

<body>

    {{-- ── Prepare QR data URI (server-side, no external requests) ── --}}
    @php
        use App\Services\QrCodeGenerator;

        $dispatch = $batch->dispatches->first();

        $unitLabel   = ' MTR';
        $decimals    = 0;
        $emptyWeight  = (float) ($dispatch?->empty_weight_truck ?? 0);
        $loadedWeight = (float) ($dispatch?->loaded_weight_truck ?? 0);
        $netWeight    = (float) ($dispatch?->net_weight ?? max(0, $loadedWeight - $emptyWeight));


        $batchNo = 'B' . str_pad($batch->batch_no ?? $batch->id, 4, '0', STR_PAD_LEFT);
        $hash = md5($batch->id . 'gatepass-secret-salt-2026');
        $qrUrl = route('public.gatepass.verify', ['batch' => $batch->id, 'hash' => $hash]);
        $qrDataUri = QrCodeGenerator::svgDataUri($qrUrl, size: 4, quiet: 2);
    @endphp

    <div class="header">
        @if ($batch->workOrder?->plant?->logo_path)
            <div style="text-align: center; margin-bottom: 4px;">
                @php
                    $cleanLogoPath = ltrim(
                        str_replace(['public/', 'storage/', '/storage/'], '', $batch->workOrder->plant->logo_path),
                        '/',
                    );
                    $logoUrl = !empty($isPreview)
                        ? asset('storage/' . $cleanLogoPath)
                        : public_path('storage/' . $cleanLogoPath);
                @endphp
                <img src="{{ $logoUrl }}" style="max-height: 40px; max-width: 130px; object-fit: contain;" />
            </div>
        @endif
        <div class="company-name">{{ $batch->workOrder?->plant?->name }}</div>
        @if ($batch->workOrder?->plant && $batch->workOrder->plant->addresses->isNotEmpty())
            @php $plAddr = $batch->workOrder->plant->addresses->first(); @endphp
            <div class="company-address">
                {{ $plAddr->line_1 ?? '' }}, {{ $plAddr->city ?? '' }} - {{ $plAddr->zipcode ?? '' }}
            </div>
        @endif
        <div class="token-title">** GATE PASS **</div>
    </div>

    {{-- ── Batch / Pass Number ── --}}
    <div class="pass-number-row">
        <div class="pass-number-label">Batch / Gate Pass No.</div>
        <div class="pass-number-value font-mono">{{ $batchNo }}</div>
        <div style="font-size: 9px; color: #000; margin-top: 2px;">
            {{ optional($batch->load_time ?? $batch->created_at)->format('d-M-Y  H:i') }}
        </div>
    </div>

    <div class="divider-solid"></div>

    {{-- ── Customer & Site ── --}}
    <div class="section-title">Customer / Site Details</div>
    <table class="meta-table">
        <tr>
            <td class="meta-label">Customer</td>
            <td class="meta-colon">:</td>
            <td class="meta-value">{{ $batch->workOrder?->customer?->legal_name ?? '-' }}</td>
        </tr>
        <tr>
            <td class="meta-label">Site</td>
            <td class="meta-colon">:</td>
            <td class="meta-value">{{ $batch->workOrder?->site?->name ?? '-' }}</td>
        </tr>
        <tr>
            <td class="meta-label">Order No</td>
            <td class="meta-colon">:</td>
            <td class="meta-value font-mono">{{ $batch->workOrder?->order_no ?? '-' }}</td>
        </tr>
    </table>

    <div class="divider"></div>

    {{-- ── Mix Design ── --}}
    <div class="section-title">Mix Design</div>
    <table class="meta-table">
        <tr>
            <td class="meta-label">Grade</td>
            <td class="meta-colon">:</td>
            <td class="meta-value">
                {{ $batch->workOrder?->mixDesign?->concrete_grade?->name ?? ($batch->workOrder?->mixDesign?->design_name ?? '-') }}
            </td>
        </tr>
        <tr>
            <td class="meta-label">Code</td>
            <td class="meta-colon">:</td>
            <td class="meta-value font-mono">{{ $batch->workOrder?->mixDesign?->design_code ?? '-' }}</td>
        </tr>
        <tr>
            <td class="meta-label">Batch Qty</td>
            <td class="meta-colon">:</td>
            <td class="meta-value font-mono" style="font-size:13px; font-weight:900;">
                {{ number_format((float) $batch->batch_size, 2) }} m&sup3;
            </td>
        </tr>
    </table>

    <div class="divider"></div>

    {{-- ── Transport / Truck ── --}}
    <div class="section-title">Transport Details</div>
    <table class="meta-table">
        <tr>
            <td class="meta-label">Truck No</td>
            <td class="meta-colon">:</td>
            <td class="meta-value font-mono" style="font-size:13px; font-weight:900; letter-spacing:1px;">
                {{ $dispatch?->truck?->registration ?? '-' }}
            </td>
        </tr>
        <tr>
            <td class="meta-label">Driver</td>
            <td class="meta-colon">:</td>
            <td class="meta-value">
                {{ trim(($dispatch?->driver?->first_name ?? '') . ' ' . ($dispatch?->driver?->last_name ?? '')) ?: '-' }}
            </td>
        </tr>
        @if ($dispatch?->transport?->legal_name)
            <tr>
                <td class="meta-label">Transporter</td>
                <td class="meta-colon">:</td>
                <td class="meta-value">{{ $dispatch->transport->legal_name }}</td>
            </tr>
        @endif
        <tr>
            <td class="meta-label">Operator</td>
            <td class="meta-colon">:</td>
            <td class="meta-value">{{ $batch->operator?->label ?? 'System' }}</td>
        </tr>
    </table>

    <div class="divider"></div>

    {{-- ── Weight Record ── --}}
    <div class="section-title">Weight Record</div>
    <div class="weight-box">
        <table>
            <tr>
                <td class="weight-label">Empty Weight</td>
                <td class="weight-value">{{ number_format($emptyWeight, $decimals) }}{{ $unitLabel }}</td>
            </tr>
            <tr>
                <td class="weight-label">Empty Time</td>
                <td class="weight-value" style="font-size:9px;">
                    {{ $dispatch?->empty_time ? \Carbon\Carbon::parse($dispatch->empty_time)->format('d-m-Y H:i') : '-' }}
                </td>
            </tr>
            <tr>
                <td class="weight-label">Loaded Weight</td>
                <td class="weight-value">{{ number_format($loadedWeight, $decimals) }}{{ $unitLabel }}</td>
            </tr>
            <tr>
                <td class="weight-label">Load Time</td>
                <td class="weight-value" style="font-size:9px;">
                    {{ $dispatch?->load_time ? \Carbon\Carbon::parse($dispatch->load_time)->format('d-m-Y H:i') : '-' }}
                </td>
            </tr>
            <tr class="net-row">
                <td class="weight-label" style="font-size:11px;">NET WEIGHT</td>
                <td class="weight-value" style="font-size:14px;">{{ number_format($netWeight, $decimals) }}{{ $unitLabel }}</td>
            </tr>
        </table>
    </div>

    {{-- ── Batched Materials ── --}}
    {{-- @php
        $printMode = $settings['material_print_mode'] ?? 'run';
        $formattedMaterials = $batch->getFormattedMaterials($printMode);
    @endphp

    @if ($formattedMaterials->count() > 0)
        <div class="divider"></div>
        <div class="section-title">Batched Materials</div>
        <table class="materials-table">
            <thead>
                <tr>
                    <th style="width:40%;">Material</th>
                    <th class="text-right" style="width:20%;">Target</th>
                    <th class="text-right" style="width:20%;">Actual</th>
                    <th class="text-right" style="width:20%;">Dev%</th>
                </tr>
            </thead>
            <tbody>
                @php
                    $groupedMaterials = $formattedMaterials->groupBy(function($mat) {
                        return $mat->material_name;
                    })->map(function($group) {
                        $first = $group->first();
                        $target = $group->sum('target_qty');
                        $actual = $group->sum('actual_qty');
                        $deviation = $group->sum('deviation_quantity');
                        return (object)[
                            'material_name' => $first->material_name,
                            'target_qty' => $target,
                            'actual_qty' => $actual,
                            'deviation_quantity' => $deviation,
                        ];
                    });
                @endphp
                @foreach ($groupedMaterials as $mat)
                    @php
                        $target      = (float) $mat->target_qty;
                        $actual      = (float) $mat->actual_qty;
                        $deviationVal = $actual - $target;
                        $devPercent  = $target > 0 ? ($deviationVal / $target) * 100 : 0;
                    @endphp
                    <tr>
                        <td>{{ $mat->material_name }}</td>
                        <td class="text-right font-mono">{{ number_format($target, 0) }}</td>
                        <td class="text-right font-mono">{{ number_format($actual, 0) }}</td>
                        <td class="text-right font-mono">{{ ($devPercent > 0 ? '+' : '') . number_format($devPercent, 1) }}%</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif --}}

    {{-- ── QR Code (server-side SVG, works in browser + dompdf) ── --}}
    <div class="divider"></div>
    <div class="qr-section">
        <img class="qr-img" src="{{ $qrDataUri }}" alt="QR Code" />
        <div class="qr-label">Scan to Verify &mdash; {{ $batchNo }}</div>
    </div>

    {{-- ── Signature Lines ── --}}
    <table class="sig-table">
        <tr>
            <td style="width:33%; padding-bottom: 18px;"></td>
            <td style="width:34%; padding-bottom: 18px;"></td>
            <td style="width:33%; padding-bottom: 18px;"></td>
        </tr>
        <tr>
            <td><div class="sig-line">Plant Officer</div></td>
            <td><div class="sig-line">Security</div></td>
            <td><div class="sig-line">Driver</div></td>
        </tr>
    </table>

    {{-- ── Footer ── --}}
    <div class="footer">
        <div style="letter-spacing: 1px;">MODOR MC &mdash; GATE PASS</div>
        <div class="font-mono" style="margin-top: 2px; font-size:8px;">Printed: {{ now()->format('d-m-Y H:i:s') }}</div>
        <div style="margin-top: 5px;">** AUTHORISED PASS &mdash; VALID FOR ONE TRIP **</div>
    </div>

</body>
</html>
