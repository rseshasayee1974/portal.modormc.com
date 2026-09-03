<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>{{ $target_name ?? 'Report' }}</title>
    <style>
        @page {
            margin: 35px;
            @if(isset($landscape) && $landscape)
                size: A4 landscape;
            @endif
        }
        body {
            font-family: 'DejaVu Sans', sans-serif;
            font-size: {{ (isset($landscape) && $landscape) ? '7.5pt' : '9.5pt' }};
            color: #1e293b;
            margin: 0;
            padding: 0;
            line-height: 1.4;
        }
        
        /* Watermark */
        .watermark {
            position: fixed;
            top: 55%;
            left: 50%;
            transform: translate(-50%, -50%) rotate(-35deg);
            font-size: 45pt;
            color: rgba(226, 232, 240, 0.22);
            z-index: -1000;
            font-weight: bold;
            text-transform: uppercase;
            white-space: nowrap;
            pointer-events: none;
        }

        /* Layout header table */
        .header-table {
            width: 100%;
            border-collapse: collapse;
            border: 0;
            margin-bottom: 25px;
        }
        .header-table td {
            border: 0;
            padding: 0;
            vertical-align: top;
        }
        
        /* Address styling */
        .address-box {
            font-size: {{ (isset($landscape) && $landscape) ? '7.5pt' : '8.5pt' }};
            color: #334155;
        }
        .address-title {
            font-weight: bold;
            color: #64748b;
            font-size: {{ (isset($landscape) && $landscape) ? '7.5pt' : '8.5pt' }};
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-bottom: 3px;
            display: block;
        }
        .address-name {
            font-weight: bold;
            font-size: {{ (isset($landscape) && $landscape) ? '9.5pt' : '11pt' }};
            color: #0f172a;
            margin-bottom: 2px;
            display: block;
        }

        /* Statement Title */
        .statement-title-container {
            border-bottom: 2px solid #6366f1;
            padding-bottom: 5px;
            margin-bottom: 20px;
        }
        .statement-title {
            font-size: {{ (isset($landscape) && $landscape) ? '11pt' : '13pt' }};
            font-weight: bold;
            color: #1e1b4b;
            margin: 0;
        }

        /* Data Table */
        table.data-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 15px;
            border: 1px solid #94a3b8;
        }
        table.data-table th {
            background-color: #e2e8f0;
            color: #0f172a;
            border: 1px solid #94a3b8;
            padding: {{ (isset($landscape) && $landscape) ? '5px 4px' : '9px 6px' }};
            font-size: {{ (isset($landscape) && $landscape) ? '7pt' : '8.5pt' }};
            font-weight: bold;
            text-align: center;
            text-transform: uppercase;
        }
        table.data-table td {
            padding: {{ (isset($landscape) && $landscape) ? '5px 6px' : '8px 10px' }};
            border: 1px solid #cbd5e1;
            font-size: {{ (isset($landscape) && $landscape) ? '7pt' : '9pt' }};
            vertical-align: middle;
        }
        
        /* Rows styling */
        .total-row {
            background-color: #f1f5f9;
            font-weight: bold;
        }
        
        /* Helpers */
        .text-left { text-align: left; }
        .text-right { text-align: right; }
        .text-center { text-align: center; }
        .font-bold { font-weight: bold; }
    </style>
</head>
<body>

    <!-- Subtle Diagonal Watermark -->
    <div class="watermark">{{ $plant->name ?? 'DEMO LOGIN' }}</div>

    <!-- Header section with logo and addresses -->
    <table class="header-table">
        <tr>
            <td style="width: 52%;">
                <div class="address-box" style="margin-top: 15px;">
                    <span class="address-title">Report Scope:</span>
                    <span class="address-name">{{ $target_name }}</span>
                    @if($patron)
                        <strong>Partner:</strong> {{ $patron->legal_name }}<br>
                        <strong>GSTIN:</strong> {{ $patron->gstin ?? 'N/A' }}<br>
                    @endif
                </div>
            </td>
            
            <td style="width: 48%; text-align: right;">
                <div class="address-box" style="margin-top: 5px; padding-left: 20px;">
                    <span class="address-title">Address:</span>
                    <span class="address-name">{{ $plant->name ?? 'DEMO LOGIN' }}</span>
                    @if($plant && $plant->addresses->isNotEmpty())
                        @php $plAddr = $plant->addresses->first(); @endphp
                        {{ $plAddr->line_1 ?? '' }}<br>
                        @if($plAddr->line_2){{ $plAddr->line_2 }}<br>@endif
                        {{ $plAddr->city ?? '' }}, {{ $plAddr->state->state_name ?? $plAddr->state_code ?? '' }} - {{ $plAddr->zipcode ?? '' }}<br>
                    @else
                        3/150, Akkiyampatti (Po),<br>
                        Sendamangalam (Tk),<br>
                        Namakkal (Dt), Tamil Nadu - 637409<br>
                    @endif
                    <strong>GSTIN/UIN :</strong> {{ $plant->gstin ?? '' }}<br>
                </div>
            </td>
        </tr>
    </table>

    <!-- Statement Title Bar -->
    <div class="statement-title-container">
        <h2 class="statement-title">{{ $target_name }} (Period: {{ $start }} - {{ $end }})</h2>
    </div>

    <!-- Statement Table -->
    <table class="data-table">
        <thead>
            <tr>
                <th width="5%">#</th>
                @foreach($headers as $header)
                    <th>{{ $header }}</th>
                @endforeach
            </tr>
        </thead>
        <tbody>
            @if(count($transactions) > 0)
                @foreach($transactions as $index => $row)
                    <tr>
                        <td class="text-center">{{ $index + 1 }}</td>
                        @foreach($fields as $fIdx => $field)
                            @php
                                $val = $row[$field] ?? '';
                                $align = isset($alignments[$fIdx]) ? $alignments[$fIdx] : 'left';
                            @endphp
                            <td class="text-{{ $align }}">
                                @if(is_numeric($val) && str_contains(strtolower($field), 'amt'))
                                    ₹ {{ number_format((float)$val, 2) }}
                                @else
                                    {{ $val }}
                                @endif
                            </td>
                        @endforeach
                    </tr>
                @endforeach
            @else
                <tr>
                    <td colspan="{{ count($headers) + 1 }}" class="text-center text-slate-400 italic">No records found for this period.</td>
                </tr>
            @endif

            @if(isset($totals) && count($totals) > 0)
                <tr class="total-row">
                    <td class="text-center font-bold">Total</td>
                    @foreach($fields as $fIdx => $field)
                        @php
                            $align = isset($alignments[$fIdx]) ? $alignments[$fIdx] : 'left';
                            $totalVal = $totals[$field] ?? '';
                        @endphp
                        <td class="text-{{ $align }} font-bold">
                            @if($totalVal !== '')
                                @if(is_numeric($totalVal) && str_contains(strtolower($field), 'amt'))
                                    ₹ {{ number_format((float)$totalVal, 2) }}
                                @else
                                    {{ $totalVal }}
                                @endif
                            @endif
                        </td>
                    @endforeach
                </tr>
            @endif
        </tbody>
    </table>

</body>
</html>
