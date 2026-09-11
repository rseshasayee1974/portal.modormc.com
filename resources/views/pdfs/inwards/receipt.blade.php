<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Goods Receipt Note - {{ $inward->inward_no }}</title>
    <style>
        @page {
            margin: 10mm 12mm;
            size: A4 portrait;
        }

        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }

        body {
            font-family: 'DejaVu Sans', Arial, Helvetica, sans-serif;
            font-size: 11px;
            color: #1e293b;

            background: @if (empty($is_pdf))
                #475569
            @else
                #ffffff
            @endif
            ;
            line-height: 1.4;
            padding: 0;
            margin: 0;
            -webkit-print-color-adjust: exact;
            print-color-adjust: exact;
        }

        /* ── Screen Top PDF Viewer Toolbar ── */
        .pdf-viewer-bar {
            background: #0f172a;
            color: #ffffff;
            padding: 12px 24px;
            position: sticky;
            top: 0;
            z-index: 10000;
            display: flex;
            align-items: center;
            justify-content: space-between;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.35);
            border-bottom: 1px solid #1e293b;
        }

        .pdf-title-block {
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .pdf-icon-badge {
            background: #ef4444;
            color: white;
            padding: 4px 8px;
            border-radius: 4px;
            font-weight: 900;
            font-size: 10px;
            letter-spacing: 0.5px;
        }

        .pdf-title-text {
            font-size: 13px;
            font-weight: 800;
            color: #f8fafc;
            letter-spacing: 0.5px;
        }

        .pdf-title-sub {
            font-size: 11px;
            color: #94a3b8;
            font-weight: 500;
        }

        .pdf-actions {
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .btn-print-main {
            background: #4f46e5;
            color: #ffffff;
            border: none;
            padding: 8px 18px;
            border-radius: 6px;
            font-weight: 800;
            font-size: 12px;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            box-shadow: 0 4px 12px rgba(79, 70, 229, 0.4);
            transition: all 0.2s ease;
        }

        .btn-print-main:hover {
            background: #4338ca;
            transform: translateY(-1px);
            box-shadow: 0 6px 16px rgba(79, 70, 229, 0.5);
        }

        .btn-download-pdf {
            background: #059669;
            color: #ffffff;
            border: none;
            padding: 8px 16px;
            border-radius: 6px;
            font-weight: 800;
            font-size: 12px;
            text-decoration: none;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            box-shadow: 0 4px 12px rgba(5, 150, 105, 0.35);
            transition: all 0.2s ease;
        }

        .btn-download-pdf:hover {
            background: #047857;
            transform: translateY(-1px);
            box-shadow: 0 6px 16px rgba(5, 150, 105, 0.45);
        }

        .btn-close-viewer {
            background: #334155;
            color: #cbd5e1;
            border: 1px solid #475569;
            padding: 8px 14px;
            border-radius: 6px;
            font-weight: 700;
            font-size: 12px;
            cursor: pointer;
            transition: all 0.2s ease;
        }

        .btn-close-viewer:hover {
            background: #1e293b;
            color: #ffffff;
        }

        /* ── Floating Print Button (Bottom-Right) ── */
        .floating-print-btn {
            position: fixed;
            bottom: 28px;
            right: 28px;
            z-index: 9999;
            display: flex;
            align-items: center;
            gap: 10px;
            background: #4f46e5;
            color: #ffffff;
            border: none;
            padding: 12px 22px;
            border-radius: 9999px;
            font-size: 13px;
            font-weight: 800;
            letter-spacing: 0.5px;
            cursor: pointer;
            box-shadow: 0 10px 25px rgba(79, 70, 229, 0.5);
            transition: all 0.25s ease;
        }

        .floating-print-btn:hover {
            background: #4338ca;
            transform: translateY(-3px) scale(1.02);
            box-shadow: 0 15px 30px rgba(79, 70, 229, 0.6);
        }

        /* ── Print Media ── */
        @media print {

            .no-print,
            .pdf-viewer-bar,
            .floating-print-btn {
                display: none !important;
            }

            body {
                background: #ffffff !important;
                padding: 0 !important;
                margin: 0 !important;
            }

            .grn-container {
                width: 100% !important;
                max-width: 100% !important;
                margin: 0 !important;
                border: 1.5px solid #000000 !important;
                box-shadow: none !important;
                border-radius: 0 !important;
            }
        }

        /* ── A4 Document Sheet Container ── */
        .grn-container {
            width: 210mm;
            max-width: 95vw;
            min-height: 297mm;

            margin: @if (empty($is_pdf))
                28px auto 60px auto
            @else
                0 auto
            @endif
            ;
            background: #ffffff;

            border: @if (empty($is_pdf))
                1px solid #cbd5e1
            @else
                1.5px solid #94a3b8
            @endif
            ;

            border-radius: @if (empty($is_pdf))
                4px
            @else
                0
            @endif
            ;

            box-shadow: @if (empty($is_pdf))
                0 20px 40px rgba(0, 0, 0, 0.35),
                0 5px 15px rgba(0, 0, 0, 0.2)
            @else
                none
            @endif
            ;
            overflow: hidden;
            box-sizing: border-box;
        }

        /* ── Header ── */
        .header-table {
            width: 100%;
            border-collapse: collapse;
            border-bottom: 2px solid #0f172a;
            background: #f8fafc;
        }

        .header-table td {
            padding: 12px 16px;
            vertical-align: middle;
        }

        .company-logo {
            max-height: 55px;
            max-width: 140px;
            object-fit: contain;
        }

        .company-title {
            font-size: 16px;
            font-weight: 900;
            color: #0f172a;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .company-sub {
            font-size: 9.5px;
            color: #64748b;
            line-height: 1.35;
            margin-top: 2px;
        }

        .doc-badge {
            text-align: right;
        }

        .doc-badge-title {
            font-size: 15px;
            font-weight: 900;
            color: #1e3a8a;
            text-transform: uppercase;
            letter-spacing: 1px;
        }

        .doc-badge-ref {
            font-size: 13px;
            font-weight: 900;
            color: #0f172a;
            font-family: monospace;
            margin-top: 2px;
        }

        .doc-badge-date {
            font-size: 9.5px;
            color: #64748b;
            font-weight: 700;
            margin-top: 1px;
        }

        /* ── Section Dividers ── */
        .section-bar {
            background: #e2e8f0;
            padding: 5px 12px;
            font-size: 9px;
            font-weight: 900;
            text-transform: uppercase;
            letter-spacing: 1px;
            color: #334155;
            border-bottom: 1px solid #cbd5e1;
        }

        /* ── 2-Column Info Grid ── */
        .info-table {
            width: 100%;
            border-collapse: collapse;
            border-bottom: 1px solid #cbd5e1;
        }

        .info-table td {
            width: 50%;
            vertical-align: top;
            padding: 10px 14px;
        }

        .info-table td:first-child {
            border-right: 1px solid #e2e8f0;
        }

        .info-box-title {
            font-size: 10px;
            font-weight: 900;
            color: #1e40af;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-bottom: 6px;
            border-bottom: 1px dashed #cbd5e1;
            padding-bottom: 3px;
        }

        .detail-row {
            display: table;
            width: 100%;
            margin-bottom: 3px;
            font-size: 10px;
        }

        .detail-label {
            display: table-cell;
            width: 38%;
            color: #64748b;
            font-weight: 700;
            text-transform: uppercase;
            font-size: 9px;
        }

        .detail-val {
            display: table-cell;
            width: 62%;
            color: #0f172a;
            font-weight: 800;
        }

        /* ── Weighbridge Weight Ticket Box ── */
        .weight-summary-table {
            width: 100%;
            border-collapse: collapse;
            border-bottom: 1.5px solid #cbd5e1;
            background: #fafafa;
        }

        .weight-summary-table th {
            background: #1e293b;
            color: #ffffff;
            font-size: 9px;
            font-weight: 900;
            text-transform: uppercase;
            letter-spacing: 0.8px;
            padding: 7px 10px;
            text-align: center;
            border-right: 1px solid #334155;
        }

        .weight-summary-table th:last-child {
            border-right: none;
        }

        .weight-summary-table td {
            padding: 10px;
            text-align: center;
            border-right: 1px solid #e2e8f0;
            vertical-align: middle;
        }

        .weight-summary-table td:last-child {
            border-right: none;
        }

        .weight-num {
            font-size: 15px;
            font-weight: 900;
            font-family: monospace;
            color: #0f172a;
        }

        .weight-num.net {
            color: #059669;
            font-size: 17px;
        }

        .weight-num.gross {
            color: #3b82f6;
        }

        .weight-num.tare {
            color: #d97706;
        }

        .weight-unit {
            font-size: 9px;
            color: #64748b;
            font-weight: 700;
            text-transform: uppercase;
        }

        /* ── Photographic Proof Section ── */
        .photos-container {
            width: 100%;
            border-collapse: collapse;
            border-bottom: 1.5px solid #cbd5e1;
            background: #ffffff;
        }

        .photos-container td {
            width: 50%;
            padding: 10px;
            vertical-align: top;
        }

        .photos-container td:first-child {
            border-right: 1px solid #e2e8f0;
        }

        .snap-card {
            border: 1px solid #cbd5e1;
            border-radius: 4px;
            overflow: hidden;
            background: #0f172a;
            text-align: center;
        }

        .snap-card-title {
            background: #f1f5f9;
            color: #0f172a;
            font-size: 9.5px;
            font-weight: 900;
            text-transform: uppercase;
            padding: 5px 8px;
            border-bottom: 1px solid #cbd5e1;
            text-align: left;
            display: flex;
            justify-content: space-between;
        }

        .snap-img {
            max-width: 100%;
            max-height: 160px;
            height: 150px;
            width: auto;
            object-fit: contain;
            display: block;
            margin: 0 auto;
            padding: 4px;
        }

        .snap-empty {
            height: 120px;
            display: table-cell;
            vertical-align: middle;
            text-align: center;
            width: 320px;
            color: #94a3b8;
            font-size: 10px;
            font-weight: 700;
            text-transform: uppercase;
            background: #f8fafc;
        }

        .snap-caption {
            background: #1e293b;
            color: #ffffff;
            font-size: 8.5px;
            font-family: monospace;
            padding: 3px 6px;
            text-align: center;
        }

        /* ── Items Table ── */
        .items-table {
            width: 100%;
            border-collapse: collapse;
            border-bottom: 1.5px solid #cbd5e1;
        }

        .items-table th {
            background: #f1f5f9;
            color: #334155;
            font-size: 9px;
            font-weight: 900;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            padding: 7px 10px;
            border-bottom: 1.5px solid #cbd5e1;
            text-align: left;
        }

        .items-table th.text-center {
            text-align: center;
        }

        .items-table th.text-right {
            text-align: right;
        }

        .items-table td {
            padding: 8px 10px;
            border-bottom: 1px solid #f1f5f9;
            font-size: 10px;
            vertical-align: middle;
        }

        .items-table tr:nth-child(even) {
            background: #fafafa;
        }

        .item-title {
            font-weight: 800;
            color: #0f172a;
        }

        .item-code {
            font-size: 8.5px;
            color: #64748b;
            font-family: monospace;
        }

        /* ── Signatures ── */
        .sig-table {
            width: 100%;
            border-collapse: collapse;
            background: #ffffff;
        }

        .sig-table td {
            width: 33.33%;
            padding: 24px 14px 10px 14px;
            text-align: center;
            vertical-align: bottom;
            border-right: 1px solid #f1f5f9;
        }

        .sig-table td:last-child {
            border-right: none;
        }

        .sig-line {
            border-top: 1px dashed #94a3b8;
            margin-top: 30px;
            padding-top: 4px;
            font-size: 9.5px;
            font-weight: 800;
            color: #334155;
            text-transform: uppercase;
        }

        .sig-sub {
            font-size: 8px;
            color: #94a3b8;
            text-transform: uppercase;
            margin-top: 1px;
        }

        /* ── Footer / Watermark ── */
        .footer-note {
            padding: 8px 12px;
            background: #f8fafc;
            border-top: 1px solid #e2e8f0;
            font-size: 8.5px;
            color: #64748b;
            text-align: center;
        }
    </style>
</head>

<body>

    <!-- Top Sticky PDF Viewer Toolbar -->
    @if (empty($is_pdf))
        <div class="pdf-viewer-bar no-print">
            <div class="pdf-title-block">
                <span class="pdf-icon-badge">PDF</span>
                <div>
                    <div class="pdf-title-text">GOODS RECEIPT NOTE (GRN)</div>
                    <div class="pdf-title-sub">Ref: {{ $inward->inward_no }} &bull; Vehicle:
                        {{ $inward->truck?->registration ?? 'External Vehicle' }}</div>
                </div>
            </div>
            <div class="pdf-actions">
                <button type="button" onclick="window.print()" class="btn-print-main"
                    title="Print this receipt document (Ctrl+P)">
                    <svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2"
                        viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round"
                            d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z" />
                    </svg>
                    <span>Print Receipt</span>
                </button>
                <a href="{{ route('inwards.download-receipt', $inward->id) }}" class="btn-download-pdf"
                    title="Download as A4 PDF document">
                    <svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2"
                        viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round"
                            d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4" />
                    </svg>
                    <span>Download PDF</span>
                </a>
                <button type="button" onclick="window.close()" class="btn-close-viewer" title="Close document preview">
                    Close
                </button>
            </div>
        </div>
    @endif

    <div class="grn-container">

        <!-- 1. Header with Company Logo & Info -->
        <table class="header-table">
            <tr>
                <td style="width: 60%;">
                    @if (!empty($company['logo_base64']))
                        <img src="{{ $company['logo_base64'] }}" class="company-logo" alt="Logo"
                            style="margin-bottom: 4px;" />
                    @endif
                    <div class="company-title">{{ $company['name'] ?? 'MODO RMC' }}</div>
                    <div class="company-sub">
                        {{ $company['address'] ?? '' }}
                        @if (!empty($company['city']))
                            , {{ $company['city'] }}
                        @endif
                        @if (!empty($company['state']))
                            , {{ $company['state'] }}
                        @endif
                        @if (!empty($company['pin']))
                            - {{ $company['pin'] }}
                        @endif
                    </div>
                    <div class="company-sub">
                        @if (!empty($company['gstin']))
                            <strong>GSTIN:</strong> {{ $company['gstin'] }} &bull;
                        @endif
                        @if (!empty($company['phone']))
                            <strong>Phone:</strong> {{ $company['phone'] }}
                        @endif
                    </div>
                </td>
                <td class="doc-badge" style="width: 40%;">
                    <div class="doc-badge-title">GOODS RECEIPT NOTE</div>
                    <div class="doc-badge-ref">{{ $inward->inward_no }}</div>
                    <div class="doc-badge-date">Date:
                        {{ \Carbon\Carbon::parse($inward->received_date ?? $inward->created_at)->format('d-M-Y') }}
                    </div>
                    {{-- <div class="doc-badge-date" style="font-family: monospace; color: #1e40af;">PO Ref:
                        {{ $inward->order?->po_number ?? 'N/A' }}</div> --}}
                </td>
            </tr>
        </table>

        <!-- 2. Two-Column Details Bar: Vendor & Vehicle / Transport Details -->
        {{-- <div class="section-bar">Transaction & Transport Identification</div> --}}
        <table class="info-table">
            <tr>

                <td>
                    <div class="info-box-title">Weighbridge & Truck Info</div>
                    <div class="detail-row">
                        <span class="detail-label">Vendor Name:</span>
                        <span
                            class="detail-val">{{ $inward->order?->vendor?->legal_name ?? ($inward->order?->vendor?->trade_name ?? 'N/A') }}</span>
                    </div>
                    <div class="detail-row">
                        <span class="detail-label">Truck Reg No:</span>
                        <span class="detail-val" style="font-size: 11px; font-weight: 900; color: #1e3a8a;">
                            {{ $inward->truck?->registration ?? 'External Vehicle' }}
                        </span>
                    </div>
                    <div class="detail-row">
                        <span class="detail-label">Material:</span>
                        <span class="detail-val">{{ $inward->product?->title ?? 'N/A' }}
                            ({{ $inward->product?->code ?? '' }})</span>
                    </div>
                    <div class="detail-row">
                        <span class="detail-label">Receipt Status:</span>
                        <span class="detail-val" style="color: #059669; font-weight: 900;">Confirmed Receipt to
                            Stock</span>
                    </div>
                    <div class="detail-row">
                        <span class="detail-label">Weighed At:</span>
                        <span
                            class="detail-val">{{ $inward->updated_at ? \Carbon\Carbon::parse($inward->updated_at)->format('d-M-Y H:i:s') : 'N/A' }}</span>
                    </div>
                </td>
                <td>
                    <div class="info-box-title">Vendor / Supplier Details</div>
                    <div class="detail-row">
                        <span class="detail-label">Loaded Weight:</span>
                        <span class="detail-val">{{ number_format((float) ($inward->truck_loaded ?? 0), 2) }}
                            {{ $inward->uom?->unit_code ?? ' MT' }}</span>
                    </div>
                    <div class="detail-row">
                        <span class="detail-label">Empty Weight :</span>
                        <span class="detail-val">{{ number_format((float) ($inward->truck_empty ?? 0), 2) }}
                            {{ $inward->uom?->unit_code ?? ' MT' }}</span>
                    </div>
                    <div class="detail-row">
                        <span class="detail-label">Net Weight:</span>
                        <span
                            class="detail-val">{{ number_format(max(0, (float) ($inward->truck_loaded ?? 0) - (float) ($inward->truck_empty ?? 0)), 2) }}
                            {{ $inward->uom?->unit_code ?? ' MT' }}</span>
                    </div>
                    {{-- <div class="detail-row">
                        <span class="detail-label">Purchase Order:</span>
                        <span class="detail-val">{{ $inward->order?->po_number ?? 'N/A' }} ({{ \Carbon\Carbon::parse($inward->order?->order_date ?? $inward->order?->created_at)->format('d-M-Y') }})</span>
                    </div> --}}
                </td>
            </tr>
        </table>

        <!-- 3. Weighbridge Weight Scale Summary Table -->


        <!-- 4. Visual Proof: Weighbridge Camera Snapshots (Side-by-Side) -->
        {{-- <div class="section-bar">Weighbridge Camera Snapshot Evidence</div> --}}
        <table class="photos-container">
            <tr>
                <td>
                    {{-- <div class="snap-card"> --}}
                    <div class="snap-card-title">
                        <span>Loaded Image</span>
                        {{-- <span style="font-family: monospace; color: #2563eb;">{{ number_format((float)($inward->truck_loaded ?? 0), 2) }} {{ $inward->uom?->unit_code ?? 'KGS' }}</span> --}}
                    </div>
                    @if (!empty($grossSnapBase64))
                        <img src="{{ $grossSnapBase64 }}" class="snap-img" alt="Gross Camera Snap" />
                        {{-- <div class="snap-caption">GROSS PHOTO EVIDENCE &bull; VEHICLE ON SCALE</div> --}}
                    @else
                        <div class="snap-empty">No Gross Camera Snapshot Recorded</div>
                    @endif
                    {{-- </div> --}}
                </td>
                <td>
                    {{-- <div class="snap-card"> --}}
                    <div class="snap-card-title">
                        <span>Empty Image</span>
                        {{-- <span style="font-family: monospace; color: #d97706;">{{ number_format((float)($inward->truck_empty ?? 0), 2) }} {{ $inward->uom?->unit_code ?? 'KGS' }}</span> --}}
                    </div>
                    @if (!empty($tareSnapBase64))
                        <img src="{{ $tareSnapBase64 }}" class="snap-img" alt="Tare Camera Snap" />
                        {{-- <div class="snap-caption">TARE PHOTO EVIDENCE &bull; VEHICLE ON SCALE</div> --}}
                    @else
                        <div class="snap-empty">No Tare Camera Snapshot Recorded</div>
                    @endif
                    {{-- </div> --}}
                </td>
            </tr>
        </table>



        <!-- 6. Signatures & Verification Section -->
        <table class="sig-table">
            <tr>
                <td>
                    <div class="sig-line">Weighbridge Operator</div>
                    <div class="sig-sub">Verified & Weighed By</div>
                </td>
                <td>
                    <div class="sig-line">Driver / Transporter</div>
                    <div class="sig-sub">Delivered By</div>
                </td>
                <td>
                    <div class="sig-line">Store / Plant In-Charge</div>
                    <div class="sig-sub">Authorized Acceptance</div>
                </td>
            </tr>
        </table>

        <!-- 7. Footer Note -->
        <div class="footer-note">
            This is a system-generated Goods Receipt Note (GRN) with visual weighbridge camera evidence. Printed on
            {{ now()->format('d-M-Y H:i:s') }}
        </div>

    </div>

    @if (empty($is_pdf))
        <!-- Floating Quick Print Button -->
        <button type="button" onclick="window.print()" class="floating-print-btn no-print"
            title="Print this receipt document (Ctrl+P)">
            <svg width="20" height="20" fill="none" stroke="currentColor" stroke-width="2"
                viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round"
                    d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z" />
            </svg>
            <span>Print Receipt</span>
        </button>

        <script>
            // Auto-print if query string has ?print=1 or ?auto_print=1
            window.addEventListener('DOMContentLoaded', () => {
                const urlParams = new URLSearchParams(window.location.search);
                if (urlParams.has('print') || urlParams.has('auto_print')) {
                    setTimeout(() => {
                        window.print();
                    }, 400);
                }
            });

            // Key shortcut Ctrl+P / Cmd+P listener
            window.addEventListener('keydown', (e) => {
                if ((e.ctrlKey || e.metaKey) && e.key === 'p') {
                    e.preventDefault();
                    window.print();
                }
            });
        </script>
    @endif

</body>

</html>
