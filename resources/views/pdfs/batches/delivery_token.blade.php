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
        @page { size: A4 portrait; margin: 10mm; }
        * { box-sizing: border-box; }
        body { margin: 0; padding: 0; font-family: DejaVu Sans, sans-serif; font-size: 10px; line-height: 1.25; color: #1e293b; }
        .container { width: 100%; }
        .top-bar { height: 3px; background: #243b53; margin-bottom: 10px; }
        table { width: 100%; border-collapse: collapse; }
        .header-table { margin-bottom: 10px; }
        .logo-cell { width: 52%; vertical-align: top; }
        .title-cell { width: 48%; text-align: right; vertical-align: top; }
        .plant-name { font-size: 16px; font-weight: bold; color: #102a43; margin-bottom: 3px; }
        .plant-address { font-size: 9px; color: #526577; line-height: 1.3; }
        .document-title { font-size: 20px; font-weight: bold; color: #243b53; margin-bottom: 5px; }
        .doc-meta { width: auto; font-size: 10px; }
        .doc-meta td { padding: 2px 0; }
        .doc-meta-label { font-size: 8px; text-align: right; padding-right: 8px !important; color: #627d98; text-transform: uppercase; }
        .doc-meta-value { text-align: left; font-weight: bold; }
        .section-title { font-size: 9px; font-weight: bold; color: #243b53; text-transform: uppercase; letter-spacing: .4px; background: #edf2f7; padding: 5px 7px; margin: 7px 0 5px; }
        .info-table { margin-bottom: 6px; table-layout: fixed; }
        .info-table > tbody > tr > td { width: 50%; vertical-align: top; padding-right: 8px; }
        .info-table > tbody > tr > td:last-child { padding-right: 0; }
        .details-grid { border: 1px solid #d9e2ec; table-layout: fixed; }
        .details-grid td { padding: 5px 7px; border-bottom: 1px solid #e8edf2; vertical-align: top; overflow-wrap: break-word; }
        .grid-label { width: 38%; font-size: 8px; color: #627d98; text-transform: uppercase; }
        .grid-value { width: 62%; font-size: 10px; font-weight: bold; }
        .weight-card { background: #f5f7fa; border: 1px solid #d9e2ec; padding: 8px 3px; text-align: center; }
        .weight-card-label { font-size: 8px; color: #627d98; text-transform: uppercase; margin-bottom: 4px; }
        .weight-card-value { font-size: 12px; font-weight: bold; white-space: nowrap; }
        .weight-card.highlight { background: #e6eff7; border-color: #b7c9dc; }
        .weight-card.highlight .weight-card-value { color: #102a43; }
        .materials-table { margin: 5px 0 8px; border: 1px solid #d9e2ec; table-layout: fixed; }
        .materials-table th { padding: 6px 4px; background: #243b53; color: #fff; font-size: 8px; text-align: left; }
        .materials-table td { padding: 7px 4px; font-size: 9px; border-bottom: 1px solid #d9e2ec; }
        .primary-row td { background: #f5f7fa; }
        .text-right { text-align: right !important; }
        .totals-card { background: #f5f7fa; border: 1px solid #d9e2ec; padding: 6px 10px; }
        .totals-card td { font-size: 10px; padding: 3px 0 !important; }
        .totals-card tr:last-child td { font-size: 12px; color: #102a43 !important; padding-top: 5px !important; }
        .camera-section { margin-top: 8px; page-break-inside: avoid; }
        .camera-cell { width: 50%; padding: 5px; border: 1px solid #d9e2ec; text-align: center; vertical-align: top; }
        .camera-caption { font-size: 8px; font-weight: bold; margin-bottom: 4px; color: #526577; }
        .camera-image { max-width: 100%; max-height: 95px; width: auto; height: auto; }
        .signatures-table { margin-top: 12px; page-break-inside: avoid; table-layout: fixed; }
        .signatures-table td { width: 25%; text-align: center; vertical-align: bottom; padding: 0 5px; }
        .signature-line { border-top: 1px solid #9fb3c8; margin: 0 0 5px; }
        .signature-title { font-size: 8px; font-weight: bold; color: #526577; }
        @if (!empty($isPreview))
        @media screen { body { background: #e8edf2; padding: 16px; } .container { max-width: 190mm; margin: auto; background: #fff; padding: 10mm; } .top-bar { display: none; } }
        @endif
        @media print { body { background: #fff; } .container { padding: 0; } .preview-toolbar { display: none; } }
    </style>
</head>

<body>
    <div class="top-bar"></div>
    {{-- @if (!empty($isPreview))
        <div class="preview-toolbar">
            <table>
                <tr>
                    <td style="text-align: left; padding: 4px 8px;">
                        <span style="font-weight: 800; font-size: 14px; color: #1e293b;">Delivery Token Preview (A4 Size)</span>
                    </td>
                    <td style="text-align: right; padding: 4px 8px;">
                        <button onclick="window.print()" class="primary">Print Token</button>
                        <a href="{{ route('batches.delivery-token.download', $batch->encrypted_id ?? $batch->id) }}" style="margin-left: 4px;">Download PDF</a>
                    </td>
                </tr>
            </table>
        </div>
    @endif --}}

    <div class="container">
@include('pdfs.partials._brand_header')

        <!-- Header Section -->
        <table class="header-table">
            <tr>
                <td class="logo-cell"><div class="document-title">Delivery Token</div></td>
                <td class="title-cell">
                    
                    <table class="doc-meta" align="right">
                        <tr>
                            <td class="doc-meta-label">Token No:</td>
                            <td class="doc-meta-value" style="font-family: monospace; font-size: 13px; color: #4f46e5;">
                                B{{ $batch->batch_no }}</td>
                        </tr>
                        <tr>
                            <td class="doc-meta-label">Date:</td>
                            <td class="doc-meta-value">
                                {{ optional($batch->load_time ?? $batch->created_at)->format('d-m-Y H:i') }}</td>
                        </tr>
                        @if (!empty($batch->shift))
                        <tr>
                            <td class="doc-meta-label">Shift:</td>
                            <td class="doc-meta-value">{{ $batch->shift }}</td>
                        </tr>
                        @endif
                        @if (!empty($batch->operator?->label))
                        <tr>
                            <td class="doc-meta-label">Operator:</td>
                            <td class="doc-meta-value">{{ $batch->operator->label }}</td>
                        </tr>
                        @endif
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
                        @if (!empty($batch->salesOrder?->customer?->legal_name))
                        <tr>
                            <td class="grid-label">Customer</td>
                            <td class="grid-value">{{ $batch->salesOrder->customer->legal_name }}</td>
                        </tr>
                        @endif
                        @if (!empty($batch->salesOrder?->site?->name))
                        <tr>
                            <td class="grid-label">Delivery Site</td>
                            <td class="grid-value">{{ $batch->salesOrder->site->name }}</td>
                        </tr>
                        @endif
                        @if (!empty($batch->salesOrder?->order_no))
                        <tr>
                            <td class="grid-label">Order Number</td>
                            <td class="grid-value" style="font-family: monospace;">
                                {{$batch->salesOrder->prefix . $batch->salesOrder->order_no }}</td>
                        </tr>
                        @endif
                    </table>
                </td>
                <td>
                    <div class="section-title">Logistics & Concrete Mix</div>
                    <table class="details-grid">
                        @php
                            $cgName = $batch->salesOrder?->mixDesign?->concrete_grade?->name 
                                ?? $batch->salesOrder?->mixDesign?->concreteGrade?->name 
                                ?? (!empty($batch->salesOrder?->mixDesign?->concrete_grade_id) ? \App\Models\ConcreteGrade::find($batch->salesOrder->mixDesign->concrete_grade_id)?->name : null)
                                ?? $batch->salesOrder?->mixDesign?->grade 
                                ?? $batch->salesOrder?->mixDesign?->design_type;
                        @endphp
                        @if (!empty($cgName))
                        <tr>
                            <td class="grid-label">Concrete Grade</td>
                            <td class="grid-value">
                                {{ $cgName }}
                            </td>
                        </tr>
                        @endif
                        @if (!empty($batch->salesOrder?->mixDesign?->design_code))
                        <tr>
                            <td class="grid-label">Recipe Code</td>
                            <td class="grid-value" style="font-family: monospace;">
                                {{ $batch->salesOrder->mixDesign->design_code }}</td>
                        </tr>
                        @endif
                        @if (!empty($batch->batch_size) && (float) $batch->batch_size > 0)
                        <tr>
                            <td class="grid-label">Batch size</td>
                            <td class="grid-value">{{ number_format((float) $batch->batch_size, 2) }} m³</td>
                        </tr>
                        @endif
                        @if ($batch->dispatches->first()?->salesExecutive)
                        <tr>
                            <td class="grid-label">Sales Executive</td>
                            <td class="grid-value">
                                {{ trim(($batch->dispatches->first()->salesExecutive->first_name ?? '') . ' ' . ($batch->dispatches->first()->salesExecutive->last_name ?? '')) }}
                            </td>
                        </tr>
                        @endif
                    </table>
                </td>
            </tr>
        </table>

        <!-- Vehicle Details & Weights -->
        <div class="section-title">Vehicle & Weight Information</div>
        <table class="info-table" style="margin-bottom: 10px;">
            <tr>
                <td style="width: 45%;">
                    <table class="details-grid">
                        @if (!empty($batch->dispatches->first()?->truck?->registration))
                        <tr>
                            <td class="grid-label">Truck Registration</td>
                            <td class="grid-value" style="font-family: monospace; font-weight: bold;">
                                {{ $batch->dispatches->first()->truck->registration }}</td>
                        </tr>
                        @endif
                        @php
                            $driverName = trim(($batch->dispatches->first()?->driver?->first_name ?? '') . ' ' . ($batch->dispatches->first()?->driver?->last_name ?? ''));
                        @endphp
                        @if (!empty($driverName))
                        <tr>
                            <td class="grid-label">Driver Name</td>
                            <td class="grid-value">{{ $driverName }}</td>
                        </tr>
                        @endif
                        @if ($batch->dispatches->first()?->transport?->legal_name)
                            <tr>
                                <td class="grid-label">Transporter</td>
                                <td class="grid-value">{{ $batch->dispatches->first()->transport->legal_name }}</td>
                            </tr>
                        @endif
                        @php
                            $dispatchInstance = $batch->dispatches->first();
                            $tripsDoneCount = 0;
                            if ($dispatchInstance && $dispatchInstance->truck_id) {
                                $unloadSiteId = $dispatchInstance->unload_site_id ?? $batch->salesOrder?->site_id;
                                if ($unloadSiteId) {
                                    $dispatchDate = $dispatchInstance->dispatch_time ?? $dispatchInstance->created_at;
                                    $dateString = $dispatchDate ? $dispatchDate->toDateString() : date('Y-m-d');
                                    $tripsDoneCount = \App\Models\Dispatch::where('truck_id', $dispatchInstance->truck_id)
                                        ->where(function ($q) use ($unloadSiteId) {
                                            $q->where('unload_site_id', $unloadSiteId)->orWhere(
                                                fn($sq) => $sq
                                                    ->whereNull('unload_site_id')
                                                    ->whereHas('salesOrder', fn($ssq) => $ssq->where('site_id', $unloadSiteId)),
                                            );
                                        })
                                        ->where(function ($q) use ($dateString) {
                                            $q->whereDate('dispatch_time', $dateString)->orWhereDate('created_at', $dateString);
                                        })
                                        ->where('id', '<', $dispatchInstance->id)
                                        ->count();
                                }
                            }
                        @endphp
                        {{-- <tr>
                            <td class="grid-label">Trips Done / Trip No</td>
                            <td class="grid-value">{{ $tripsDoneCount }} / {{ $tripsDoneCount + 1 }}</td>
                        </tr> --}}
                    </table>
                </td>
                <td style="width: 55%; padding-left: 10px; padding-right: 0;">
                    @php
                        $dispatch = $batch->dispatches->first();

                        $emptyWeight = (float) ($dispatch?->empty_weight_truck ?? 0);
                        $loadedWeight = (float) ($dispatch?->loaded_weight_truck ?? 0);
                        $netWeight = (float) ($dispatch?->net_weight ?? ($loadedWeight > 0 ? $loadedWeight - $emptyWeight : 0));

                        // $unitLabel = ' ' . ($dispatch?->uom?->unit_code ?? $batch->uom?->unit_code ?? 'MTR');
                        $unitLabel = ' MTR';

                        $formatWeight = function($val) {
                            $num = (float) $val;
                            return fmod($num, 1) != 0 ? number_format($num, 2) : number_format($num, 0);
                        };

                        $emptyWeightStr = $formatWeight($emptyWeight) . $unitLabel;
                        $loadedWeightStr = $formatWeight($loadedWeight) . $unitLabel;
                        $netWeightStr = $formatWeight($netWeight) . $unitLabel;
                    @endphp
                    <table style="width: 100%; border-collapse: collapse;">
                        <tr>
                            <td style="width: 33.3%; padding: 0 4px 0 0;">
                                <div class="weight-card">
                                    <div class="weight-card-label">Empty Wt</div>
                                    <div class="weight-card-value">{{ $emptyWeightStr }}</div>
                                    <div style="font-size: 8px; color: #64748b; margin-top: 4px;">
                                        {{-- {{ $dispatch?->empty_time ? \Carbon\Carbon::parse($dispatch->empty_time)->format('H:i') : '-' }} --}}
                                    </div>
                                </div>
                            </td>
                            <td style="width: 33.3%; padding: 0 4px;">
                                <div class="weight-card">
                                    <div class="weight-card-label">Loaded Wt</div>
                                    <div class="weight-card-value">{{ $loadedWeightStr }}</div>
                                    <div style="font-size: 8px; color: #64748b; margin-top: 4px;">
                                        {{-- {{ $dispatch?->load_time ? \Carbon\Carbon::parse($dispatch->load_time)->format('H:i') : '-' }} --}}
                                    </div>
                                </div>
                            </td>
                            <td style="width: 33.3%; padding: 0 0 0 4px;">
                                <div class="weight-card highlight">
                                    <div class="weight-card-label">Net Weight</div>
                                    <div class="weight-card-value">{{ $netWeightStr }}</div>
                                    <div style="font-size: 8px; color: #3730a3; margin-top: 4px; font-weight: bold;">
                                        DISPATCHED</div>
                                </div>
                            </td>
                        </tr>
                    </table>
                </td>
            </tr>
        </table>

        <!-- Materials Section -->
        @if ($batch->materials->count() > 0)
            @php
                $dispatch = $batch->dispatches->first();
                $groupedMaterials = $batch->materials->groupBy(function($mat) {
                    return $mat->product_id ?? $mat->material_name;
                })->map(function($group) {
                    $first = $group->first();
                    $target = $group->sum('target_qty');
                    $actual = $group->sum('actual_qty');
                    $deviation = $group->sum('deviation_quantity');
                    return (object)[
                        'material_name' => $first->material_name ?: ($first->product->title ?? 'Material'),
                        'uom_code' => $first->uom->unit_code ?? 'MTR',
                        'target_qty' => $target,
                        'actual_qty' => $actual,
                        'deviation_quantity' => $deviation,
                    ];
                });
                $sno = 1;
            @endphp
            
            <div class="section-title">Delivery Challan Items</div>
            <table class="materials-table">
                <thead>
                    <tr>
                        <th style="width: 4%;">S.No</th>
                        <th style="width: 32%;">Item / Material Name</th>
                        <th class="text-right" style="width: 14%;">Qty</th>
                        <th style="width: 8%;">UOM</th>
                        <th class="text-right" style="width: 10%;">Rate</th>
                        <th class="text-right" style="width: 8%;">Tax %</th>
                        <th class="text-right" style="width: 10%;">Tax Amt</th>
                        <th class="text-right" style="width: 14%;">Amount</th>
                    </tr>
                </thead>
                <tbody>
                    <!-- 1. Mix Design primary item -->
                    @if ($dispatch)
                        @php
                            $mixDesign = $batch->salesOrder?->mixDesign;
                            $mixDesignName = $mixDesign?->concrete_grade?->name 
                                ?? ($mixDesign?->concreteGrade?->name 
                                ?? (!empty($mixDesign?->concrete_grade_id) ? \App\Models\ConcreteGrade::find($mixDesign->concrete_grade_id)?->name : null)
                                ?? ($mixDesign?->grade 
                                ?? ($mixDesign?->design_type ?? 'Ready-Mix Concrete')));
                            $qty = (float)($dispatch->delivered_qty ?: $batch->batch_size);
                            $rate = (float)($dispatch->load_rate ?? 0);
                            $subTotal = (float)($dispatch->load_untax_amount ?? ($qty * $rate));
                            $taxAmount = (float)($dispatch->load_tax_amount ?? 0);
                            $totalAmount = (float)($dispatch->load_total_amount ?? ($subTotal + $taxAmount));
                            $taxRate = $dispatch->loadTax?->rate ?? 0;
                            $taxName = $dispatch->loadTax?->name ?? '-';
                        @endphp
                        <tr class="primary-row">
                            <td>{{ $sno++ }}</td>
                            <td style="font-weight: 700;">
                                {{ $mixDesignName }}
                            </td>
                            <td class="text-right font-mono" style="font-weight: bold;">{{ number_format($qty, 2) }}</td>
                            <td>{{ $dispatch->uom?->unit_code ?? 'CBM' }}</td>
                            <td class="text-right font-mono">₹ {{ number_format($rate, 2) }}</td>
                            <td class="text-right font-mono">{{ $taxRate > 0 ? $taxRate . '%' : '-' }}</td>
                            <td class="text-right font-mono">₹ {{ number_format($taxAmount, 2) }}</td>
                            <td class="text-right font-mono" style="font-weight: bold;">₹ {{ number_format($totalAmount, 2) }}</td>
                        </tr>
                    @endif

                   
                </tbody>
            </table>
        @endif

        <!-- Totals Block -->
        @if(isset($dispatch) && $dispatch)
        <table style="width: 100%; margin-top: 5px; margin-bottom: 8px; border-collapse: collapse;">
            <tr>
                {{-- <td style="width: 55%; vertical-align: top; font-size: 10px; color: #64748b; padding-right: 15px;">
                    <strong>Notes:</strong> All material quantities shown are compiled from PLC batching logs.
                </td> --}}
                <td style="width: 45%; vertical-align: top;">
                    <div class="totals-card">
                        <table style="width: 100%; border-collapse: collapse; font-size: 11px;">
                            <tr>
                                <td style="padding: 4px 0; color: #64748b; font-weight: bold; width: 50%;">Sub Total:</td>
                                <td class="text-right" style="padding: 4px 0; font-weight: bold; color: #0f172a;">₹ {{ number_format($dispatch->load_untax_amount, 2) }}</td>
                            </tr>
                            @if($dispatch->discount_amount > 0)
                            <tr>
                                <td style="padding: 4px 0; color: #64748b; font-weight: bold;">Discount:</td>
                                <td class="text-right" style="padding: 4px 0; font-weight: bold; color: #dc2626;">- ₹ {{ number_format($dispatch->discount_amount, 2) }}</td>
                            </tr>
                            @endif
                            @if($dispatch->load_tax_amount > 0)
                            <tr>
                                <td style="padding: 4px 0; color: #64748b; font-weight: bold;">Tax ({{ $dispatch->loadTax?->name ?? 'GST' }}):</td>
                                <td class="text-right" style="padding: 4px 0; font-weight: bold; color: #0f172a;">₹ {{ number_format($dispatch->load_tax_amount, 2) }}</td>
                            </tr>
                            @endif
                            @if($dispatch->transport_expenses > 0)
                            <tr>
                                <td style="padding: 4px 0; color: #64748b; font-weight: bold;">Transport Charges:</td>
                                <td class="text-right" style="padding: 4px 0; font-weight: bold; color: #0f172a;">₹ {{ number_format($dispatch->transport_expenses, 2) }}</td>
                            </tr>
                            @endif
                            @if($dispatch->adjustment_amount != 0)
                            <tr>
                                <td style="padding: 4px 0; color: #64748b; font-weight: bold;">Adjustment:</td>
                                <td class="text-right" style="padding: 4px 0; font-weight: bold; color: #0f172a;">₹ {{ number_format($dispatch->adjustment_amount, 2) }}</td>
                            </tr>
                            @endif
                            @if($dispatch->round_off != 0)
                            <tr>
                                <td style="padding: 4px 0; color: #64748b; font-weight: bold;">Round Off:</td>
                                <td class="text-right" style="padding: 4px 0; font-weight: bold; color: #0f172a;">₹ {{ number_format($dispatch->round_off, 2) }}</td>
                            </tr>
                            @endif
                            <tr style="border-top: 1.5px solid #cbd5e1; font-size: 13px;">
                                <td style="padding: 8px 0 0 0; color: #4f46e5; font-weight: 800; text-transform: uppercase;">Total Amount:</td>
                                <td class="text-right" style="padding: 8px 0 0 0; font-weight: 900; color: #312e81;">₹ {{ number_format($dispatch->load_total_amount, 2) }}</td>
                            </tr>
                        </table>
                    </div>
                </td>
            </tr>
        </table>
        @endif

        <!-- Ingredients Section (Hidden on A4 Delivery Token) -->
        @php
            $showIngredients = false;
        @endphp
        @if ($showIngredients && $batch->salesOrder?->mixDesign?->items?->count() > 0)
            <div class="section-title" style="margin-top: 25px;">Batching &amp; Ingredients Details</div>
            <table class="materials-table">
                <thead>
                    <tr>
                        <th style="width: 5%;">S.No</th>
                        <th style="width: 45%;">Material Name</th>
                        <th style="width: 10%;">UOM</th>
                        <th class="text-right" style="width: 13%;">Recipe (kg/m³)</th>
                        <th class="text-right" style="width: 13%;">Actual Qty</th>
                        <th class="text-right" style="width: 14%;">Deviation (%)</th>
                    </tr>
                </thead>
                <tbody>
                    @php
                        // Build a lookup of actual_qty from batch materials keyed by product_id
                        $batchActuals = $batch->materials->groupBy('product_id')
                            ->map(fn($g) => $g->sum('actual_qty'));
                        $ingSno = 1;
                    @endphp
                    @foreach ($batch->salesOrder->mixDesign->items as $item)
                        @php
                            $recipe   = (float) $item->actual_quantity;                      // raw mix design qty per m³
                            $actual   = (float) ($batchActuals[$item->product_id] ?? 0);     // what was actually dispensed
                            $devVal   = $actual - $recipe;
                            $devPct   = $recipe > 0 ? ($devVal / $recipe) * 100 : 0;
                            $uomCode  = $item->uom?->unit_code ?? 'MTR';
                        @endphp
                        <tr class="secondary-row">
                            <td>{{ $ingSno++ }}</td>
                            <td style="font-weight: 700;">{{ $item->product?->title ?? 'Material' }}</td>
                            <td>{{ $uomCode }}</td>
                            <td class="text-right font-mono">{{ number_format($recipe, 2) }}</td>
                            <td class="text-right font-mono" style="font-weight: bold; color: #0f172a;">{{ number_format($actual, 2) }}</td>
                            <td class="text-right font-mono" style="font-weight: bold; color: {{ $devPct > 5 || $devPct < -5 ? '#dc2626' : '#15803d' }};">
                                {{ ($devPct > 0 ? '+' : '') . number_format($devPct, 2) }}%
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @endif

        @php
            $cameraPhotos = $batch->photos->filter(function ($photo) {
                return in_array($photo->image_name, ['empty_weight_snap', 'loaded_weight_snap'], true)
                    && $photo->image_path
                    && \Illuminate\Support\Facades\Storage::disk('public')->exists($photo->image_path);
            })->sortBy(fn ($photo) => $photo->image_name === 'empty_weight_snap' ? 0 : 1)->values();
        @endphp
        @if ($cameraPhotos->isNotEmpty())
            <div class="camera-section">
                <div class="section-title">Camera Images</div>
                <table style="width: 100%; table-layout: fixed; border-collapse: collapse;">
                    <tr>
                        @foreach ($cameraPhotos as $photo)
                            @php
                                $photoSrc = !empty($isPreview)
                                    ? '/storage/' . ltrim($photo->image_path, '/')
                                    : \Illuminate\Support\Facades\Storage::disk('public')->path($photo->image_path);
                            @endphp
                            <td class="camera-cell">
                                <div class="camera-caption">{{ $photo->image_name === 'empty_weight_snap' ? 'Empty Weight Camera' : 'Loaded Weight Camera' }}</div>
                                <img src="{{ $photoSrc }}" alt="{{ $photo->alt_txt ?: 'Batch camera image' }}"
                                    class="camera-image" />
                            </td>
                        @endforeach
                    </tr>
                </table>
            </div>
        @endif

        <!-- Signatures Section -->
        <table class="signatures-table">
            <tr>
                <td>
                    <div style="height: 30px;"></div>
                    <div class="signature-line"></div>
                    <div class="signature-title">Prepared By (Operator)</div>
                </td>
                <td>
                    <div style="height: 30px;"></div>
                    <div class="signature-line"></div>
                    <div class="signature-title">Driver's Signature</div>
                </td>
                <td>
                    <div style="height: 30px;"></div>
                    <div class="signature-line"></div>
                    <div class="signature-title">Received By (Customer)</div>
                </td>
                <td>
                    <div style="min-height: 30px; text-align: center; margin-bottom: 5px;">
                        @if ($batch->salesOrder?->plant?->seal_sign_path)
                            @php
                                $sealPath = ltrim(
                                    str_replace(
                                        ['public/', 'storage/', '/storage/'],
                                        '',
                                        $batch->salesOrder->plant->seal_sign_path,
                                    ),
                                    '/',
                                );
                                $sealUrl = !empty($isPreview)
                                    ? asset('storage/' . $sealPath)
                                    : public_path('storage/' . $sealPath);
                            @endphp
                            <img src="{{ $sealUrl }}" style="max-height: 30px; max-width: 130px; object-fit: contain;" />
                        @elseif (!empty($settings['seal_sign_path']))
                            @php
                                $sealPath = ltrim(
                                    str_replace(
                                        ['public/', 'storage/', '/storage/'],
                                        '',
                                        $settings['seal_sign_path'],
                                    ),
                                    '/',
                                );
                                $sealUrl = !empty($isPreview)
                                    ? asset('storage/' . $sealPath)
                                    : public_path('storage/' . $sealPath);
                            @endphp
                            <img src="{{ $sealUrl }}" style="max-height: 30px; max-width: 130px; object-fit: contain;" />
                        @elseif (file_exists(public_path('storage/plants/onemodo/onemodo-teynampet/seal_sign.png')))
                            @php
                                $sealUrl = !empty($isPreview)
                                    ? asset('storage/plants/onemodo/onemodo-teynampet/seal_sign.png')
                                    : public_path('storage/plants/onemodo/onemodo-teynampet/seal_sign.png');
                            @endphp
                            <img src="{{ $sealUrl }}" style="max-height: 30px; max-width: 130px; object-fit: contain;" />
                        @elseif (file_exists(public_path('storage/seal_sign.png')))
                            @php
                                $sealUrl = !empty($isPreview)
                                    ? asset('storage/seal_sign.png')
                                    : public_path('storage/seal_sign.png');
                            @endphp
                            <img src="{{ $sealUrl }}" style="max-height: 30px; max-width: 130px; object-fit: contain;" />
                        @else
                            <div style="height: 30px;"></div>
                        @endif
                    </div>
                    <div class="signature-line" style="margin-top: 0;"></div>
                    <div class="signature-title">Authorized Signatory</div>
                </td>
            </tr>
        </table>
    </div>
</body>

</html>
