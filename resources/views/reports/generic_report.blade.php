<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>{{ $target_name ?? 'Report' }}</title>
    <style>
        {!! $css ?? $report_css ?? (file_exists(public_path('css/reports/generic_report.css')) ? file_get_contents(public_path('css/reports/generic_report.css')) : (file_exists(public_path('css/reports/report_pdf.css')) ? file_get_contents(public_path('css/reports/report_pdf.css')) : '')) !!}
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
