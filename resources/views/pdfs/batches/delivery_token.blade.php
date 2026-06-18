@if (isset($data))
    @php
        $batch = $data['batch'] ?? $batch;
        $settings = $data['settings'] ?? $settings;
        $isPreview = $isPreview ?? true;
    @endphp
@endif
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <title>Delivery Token</title>
    <style>
        @page {
            size: A4 portrait;
            margin: 15mm;
        }

        body {
            font-family: 'Helvetica Neue', Helvetica, Arial, sans-serif;
            font-size: 11px;
            color: #1e293b;
            line-height: 1.5;
            background: #ffffff;
            margin: 0;
            padding: 0;
        }

        .container {
            width: 100%;
        }

        /* Header block */
        .header-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 20px;
        }

        .logo-cell {
            width: 50%;
            vertical-align: top;
        }

        .title-cell {
            width: 50%;
            text-align: right;
            vertical-align: top;
        }

        .plant-name {
            font-size: 18px;
            font-weight: 800;
            color: #0f172a;
            margin-bottom: 4px;
            text-transform: uppercase;
        }

        .plant-address {
            font-size: 10px;
            color: #64748b;
            max-width: 320px;
            line-height: 1.4;
        }

        .document-title {
            font-size: 22px;
            font-weight: 900;
            color: #4f46e5;
            text-transform: uppercase;
            letter-spacing: 1px;
            margin-bottom: 8px;
        }

        .doc-meta {
            font-size: 11px;
            color: #334155;
        }

        .doc-meta td {
            padding: 2px 0;
        }

        .doc-meta-label {
            font-weight: 700;
            color: #64748b;
            text-align: right;
            padding-right: 8px;
            text-transform: uppercase;
            font-size: 9px;
            letter-spacing: 0.5px;
        }

        .doc-meta-value {
            font-weight: 700;
            color: #0f172a;
            text-align: left;
        }

        /* Section block layouts */
        .section-title {
            font-size: 11px;
            font-weight: 800;
            color: #4f46e5;
            text-transform: uppercase;
            border-bottom: 2px solid #e2e8f0;
            padding-bottom: 4px;
            margin-top: 15px;
            margin-bottom: 10px;
            letter-spacing: 0.5px;
        }

        .info-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 15px;
        }

        .info-table td {
            width: 50%;
            vertical-align: top;
            padding: 0 10px 0 0;
        }

        .details-grid {
            width: 100%;
            border-collapse: collapse;
            background: #f8fafc;
            border: 1px solid #e2e8f0;
            border-radius: 6px;
        }

        .details-grid td {
            padding: 8px 12px;
            border-bottom: 1px solid #e2e8f0;
            font-size: 11px;
        }

        .details-grid tr:last-child td {
            border-bottom: none;
        }

        .grid-label {
            font-weight: 700;
            color: #64748b;
            width: 35%;
            text-transform: uppercase;
            font-size: 9px;
        }

        .grid-value {
            font-weight: 600;
            color: #1e293b;
            width: 65%;
        }

        /* Weight highlights */
        .weight-card-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 15px;
        }

        .weight-card {
            background: #f1f5f9;
            border: 1px solid #cbd5e1;
            border-radius: 6px;
            padding: 10px 15px;
            text-align: center;
        }

        .weight-card-label {
            font-size: 9px;
            font-weight: 800;
            color: #64748b;
            text-transform: uppercase;
            margin-bottom: 4px;
        }

        .weight-card-value {
            font-size: 14px;
            font-weight: 800;
            color: #0f172a;
        }

        .weight-card.highlight {
            background: #e0e7ff;
            border-color: #c7d2fe;
        }

        .weight-card.highlight .weight-card-label {
            color: #4f46e5;
        }

        .weight-card.highlight .weight-card-value {
            color: #3730a3;
            font-size: 16px;
        }

        /* Materials table styling */
        .materials-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 10px;
            margin-bottom: 25px;
            border: 1px solid #e2e8f0;
            border-radius: 6px;
            overflow: hidden;
        }

        .materials-table th {
            background: #f1f5f9;
            color: #475569;
            font-weight: 800;
            font-size: 9.5px;
            text-transform: uppercase;
            text-align: left;
            padding: 8px 12px;
            border-bottom: 1px solid #cbd5e1;
            letter-spacing: 0.5px;
        }

        .materials-table td {
            padding: 8px 12px;
            border-bottom: 1px solid #e2e8f0;
            font-size: 11px;
            color: #334155;
        }

        .materials-table tr:last-child td {
            border-bottom: none;
        }

        .text-right {
            text-align: right !important;
        }

        /* Footer & Signatures block */
        .signatures-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 45px;
        }

        .signatures-table td {
            width: 33.3%;
            text-align: center;
            vertical-align: bottom;
            padding-top: 50px;
        }

        .signature-line {
            width: 80%;
            border-top: 1px solid #cbd5e1;
            margin: 0 auto 5px auto;
        }

        .signature-title {
            font-size: 9.5px;
            font-weight: 700;
            color: #64748b;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        /* Preview Toolbar styles (only for browser view) */
        .preview-toolbar {
            background: #f1f5f9;
            padding: 10px 15px;
            margin: -15mm -15mm 15mm -15mm;
            border-bottom: 1px solid #e2e8f0;
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
            background: #ffffff;
            border: 1px solid #cbd5e1;
            border-radius: 6px;
            padding: 6px 12px;
            cursor: pointer;
            text-decoration: none;
            color: #334155;
            font-size: 12px;
            font-weight: 600;
            display: inline-block;
        }

        .preview-toolbar button.primary {
            background: #4f46e5;
            color: #ffffff;
            border-color: #4f46e5;
        }

        @media print {
            .preview-toolbar {
                display: none !important;
            }
        }
    </style>
</head>

<body>
    {{-- @if (!empty($isPreview))
        <div class="preview-toolbar">
            <table>
                <tr>
                    <td style="text-align: left; padding: 4px 8px;">
                        <span style="font-weight: 800; font-size: 14px; color: #1e293b;">Delivery Token Preview (A4 Size)</span>
                    </td>
                    <td style="text-align: right; padding: 4px 8px;">
                        <button onclick="window.print()" class="primary">Print Token</button>
                        <a href="{{ route('batches.delivery-token.download', $batch->id) }}" style="margin-left: 4px;">Download PDF</a>
                    </td>
                </tr>
            </table>
        </div>
    @endif --}}

    <div class="container">
        <!-- Header Section -->
        <table class="header-table">
            <tr>
                <td class="logo-cell">
                    @if ($batch->workOrder?->plant?->logo_path)
                        @php
                            $cleanLogoPath = ltrim(
                                str_replace(['public/', 'storage/', '/storage/'], '', $batch->workOrder->plant->logo_path),
                                '/',
                            );
                            $logoUrl = !empty($isPreview)
                                ? asset('storage/' . $cleanLogoPath)
                                : public_path('storage/' . $cleanLogoPath);
                        @endphp
                        <img src="{{ $logoUrl }}" style="max-height: 55px; max-width: 180px; object-fit: contain; margin-bottom: 8px;" />
                    @endif
                    <div class="plant-name">{{ $batch->workOrder?->plant?->name }}</div>
                    @if ($batch->workOrder?->plant && $batch->workOrder->plant->addresses->isNotEmpty())
                        @php $plAddr = $batch->workOrder->plant->addresses->first(); @endphp
                        <div class="plant-address">
                            {{ $plAddr->line_1 ?? '' }}, {{ $plAddr->city ?? '' }}, {{ $plAddr->state ?? '' }} -
                            {{ $plAddr->pincode ?? '' }}
                        </div>
                    @endif
                </td>
                <td class="title-cell">
                    <div class="document-title">Delivery Token</div>
                    <table class="doc-meta" align="right">
                        <tr>
                            <td class="doc-meta-label">Token No:</td>
                            <td class="doc-meta-value" style="font-family: monospace; font-size: 13px; color: #4f46e5;">B{{ $batch->batch_no }}</td>
                        </tr>
                        <tr>
                            <td class="doc-meta-label">Date:</td>
                            <td class="doc-meta-value">{{ optional($batch->load_time ?? $batch->created_at)->format('d-m-Y H:i') }}</td>
                        </tr>
                        <tr>
                            <td class="doc-meta-label">Shift:</td>
                            <td class="doc-meta-value">{{ $batch->shift ?? '-' }}</td>
                        </tr>
                        <tr>
                            <td class="doc-meta-label">Operator:</td>
                            <td class="doc-meta-value">{{ $batch->operator?->label ?? 'System' }}</td>
                        </tr>
                    </table>
                </td>
            </tr>
        </table>

        <!-- Details Sections -->
        <table class="info-table">
            <tr>
                <td>
                    <div class="section-title">Delivery & Customer Details</div>
                    <table class="details-grid">
                        <tr>
                            <td class="grid-label">Customer</td>
                            <td class="grid-value">{{ $batch->workOrder?->customer?->legal_name ?? '-' }}</td>
                        </tr>
                        <tr>
                            <td class="grid-label">Delivery Site</td>
                            <td class="grid-value">{{ $batch->workOrder?->site?->name ?? '-' }}</td>
                        </tr>
                        <tr>
                            <td class="grid-label">Order Number</td>
                            <td class="grid-value" style="font-family: monospace;">{{ $batch->workOrder?->order_no ?? '-' }}</td>
                        </tr>
                    </table>
                </td>
                <td>
                    <div class="section-title">Logistics & Concrete Mix</div>
                    <table class="details-grid">
                        <tr>
                            <td class="grid-label">Concrete Grade</td>
                            <td class="grid-value">{{ $batch->workOrder?->mixDesign?->concrete_grade?->name ?? ($batch->workOrder?->mixDesign?->design_name ?? '-') }}</td>
                        </tr>
                        <tr>
                            <td class="grid-label">Recipe Code</td>
                            <td class="grid-value" style="font-family: monospace;">{{ $batch->workOrder?->mixDesign?->design_code ?? '-' }}</td>
                        </tr>
                        <tr>
                            <td class="grid-label">Batch size</td>
                            <td class="grid-value">{{ number_format((float) $batch->batch_size, 2) }} m³</td>
                        </tr>
                    </table>
                </td>
            </tr>
        </table>

        <!-- Vehicle Details & Weights -->
        <div class="section-title">Vehicle & Weight Information</div>
        <table class="info-table" style="margin-bottom: 10px;">
            <tr>
                <td style="width: 45%;">
                    <table class="details-grid" style="height: 100%;">
                        <tr>
                            <td class="grid-label">Truck Registration</td>
                            <td class="grid-value" style="font-family: monospace; font-weight: bold;">{{ $batch->dispatches->first()?->truck?->registration ?? '-' }}</td>
                        </tr>
                        <tr>
                            <td class="grid-label">Driver Name</td>
                            <td class="grid-value">{{ trim(($batch->dispatches->first()?->driver?->first_name ?? '') . ' ' . ($batch->dispatches->first()?->driver?->last_name ?? '')) ?: '-' }}</td>
                        </tr>
                        @if ($batch->dispatches->first()?->transport?->legal_name)
                            <tr>
                                <td class="grid-label">Transporter</td>
                                <td class="grid-value">{{ $batch->dispatches->first()->transport->legal_name }}</td>
                            </tr>
                        @endif
                    </table>
                </td>
                <td style="width: 55%; padding-left: 10px; padding-right: 0;">
                    @php
                        $isMetricTon = !empty($settings['InvoiceInMetricTon']) && $settings['InvoiceInMetricTon'] == 1;
                        $dispatch = $batch->dispatches->first();
                        
                        $emptyWeight = (float) ($dispatch?->empty_weight_truck ?? 0);
                        $loadedWeight = (float) ($dispatch?->loaded_weight_truck ?? 0);
                        $netWeight = (float) ($dispatch?->net_weight ?? ($loadedWeight - $emptyWeight));
                        
                        $unitLabel = $isMetricTon ? ' MT' : ' kg';
                        $decimals = $isMetricTon ? 3 : 0;
                        
                        $emptyWeightStr = number_format($emptyWeight, $decimals) . $unitLabel;
                        $loadedWeightStr = number_format($loadedWeight, $decimals) . $unitLabel;
                        $netWeightStr = number_format($netWeight, $decimals) . $unitLabel;
                    @endphp
                    <table style="width: 100%; border-collapse: collapse;">
                        <tr>
                            <td style="width: 33.3%; padding: 0 4px 0 0;">
                                <div class="weight-card">
                                    <div class="weight-card-label">Empty Wt</div>
                                    <div class="weight-card-value">{{ $emptyWeightStr }}</div>
                                    <div style="font-size: 8px; color: #64748b; margin-top: 4px;">{{ $dispatch?->empty_time ? \Carbon\Carbon::parse($dispatch->empty_time)->format('H:i') : '-' }}</div>
                                </div>
                            </td>
                            <td style="width: 33.3%; padding: 0 4px;">
                                <div class="weight-card">
                                    <div class="weight-card-label">Loaded Wt</div>
                                    <div class="weight-card-value">{{ $loadedWeightStr }}</div>
                                    <div style="font-size: 8px; color: #64748b; margin-top: 4px;">{{ $dispatch?->load_time ? \Carbon\Carbon::parse($dispatch->load_time)->format('H:i') : '-' }}</div>
                                </div>
                            </td>
                            <td style="width: 33.3%; padding: 0 0 0 4px;">
                                <div class="weight-card highlight">
                                    <div class="weight-card-label">Net Weight</div>
                                    <div class="weight-card-value">{{ $netWeightStr }}</div>
                                    <div style="font-size: 8px; color: #3730a3; margin-top: 4px; font-weight: bold;">DISPATCHED</div>
                                </div>
                            </td>
                        </tr>
                    </table>
                </td>
            </tr>
        </table>

        <!-- Materials Section -->
        @if ($batch->materials->count() > 0)
            <div class="section-title">Dispatched Ingredients Details</div>
            <table class="materials-table">
                <thead>
                    <tr>
                        <th style="width: 5%;">S.No</th>
                        <th style="width: 45%;">Material Name</th>
                        <th style="width: 10%;">UOM</th>
                        <th class="text-right" style="width: 13%;">Target Qty</th>
                        <th class="text-right" style="width: 13%;">Actual Qty</th>
                        <th class="text-right" style="width: 14%;">Deviation (%)</th>
                    </tr>
                </thead>
                <tbody>
                    @php $sno = 1; @endphp
                    @foreach ($batch->materials as $mat)
                        @php
                            $target = (float) $mat->target_qty;
                            $actual = (float) $mat->actual_qty;
                            $deviationVal = (float) $mat->deviation_quantity;
                            $devPercent = 0;
                            if ($target > 0) {
                                $devPercent = ($deviationVal / $target) * 100;
                            }
                        @endphp
                        <tr>
                            <td>{{ $sno++ }}</td>
                            <td style="font-weight: 700;">{{ $mat->material_name ?: $mat->product->title ?? 'Material' }}</td>
                            <td>{{ $mat->uom?->unit_code ?? 'KGS' }}</td>
                            <td class="text-right font-mono">{{ number_format($target, 2) }}</td>
                            <td class="text-right font-mono" style="font-weight: bold; color: #0f172a;">{{ number_format($actual, 2) }}</td>
                            <td class="text-right font-mono" style="font-weight: bold; color: {{ $devPercent > 5 || $devPercent < -5 ? '#dc2626' : '#15803d' }};">
                                {{ ($devPercent > 0 ? '+' : '') . number_format($devPercent, 2) }}%
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @endif

        <!-- Signatures Section -->
        <table class="signatures-table">
            <tr>
                <td>
                    <div class="signature-line"></div>
                    <div class="signature-title">Prepared By (Operator)</div>
                </td>
                <td>
                    <div class="signature-line"></div>
                    <div class="signature-title">Driver's Signature</div>
                </td>
                <td>
                    <div class="signature-line"></div>
                    <div class="signature-title">Received By (Customer)</div>
                </td>
            </tr>
        </table>
    </div>
</body>

</html>
