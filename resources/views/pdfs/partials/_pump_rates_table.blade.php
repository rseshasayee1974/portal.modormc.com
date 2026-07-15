@if (!empty($pdfSettings['pump_rates']) && !empty($item['pump_rates']))
    <div style="margin-top: 6px; page-break-inside: avoid;">
        <span style="font-size: 9px; font-weight: bold; color: #475569; text-transform: uppercase; letter-spacing: 0.05em; display: block; margin-bottom: 3px;">Pump Rates(Additional Charges)</span>
        <table style="width: 100%; max-width: 250px; border-collapse: collapse; font-size: 9px; border: 1px solid #e2e8f0; border-radius: 4px; overflow: hidden;">
            <thead>
                <tr style="background: #f8fafc; border-bottom: 1px solid #e2e8f0; font-weight: bold; color: #475569;">
                    <th style="padding: 3px 6px; text-align: left; width: 60%;">Pump Type</th>
                    <th style="padding: 3px 6px; text-align: right; width: 40%;">Rate</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($item['pump_rates'] as $pr)
                    <tr style="border-bottom: 1px solid #f1f5f9; color: #334155;">
                        <td style="padding: 3px 6px; text-align: left;">{{ $pr['pump_type'] }}</td>
                        <td style="padding: 3px 6px; text-align: right; font-weight: bold; color: #1e293b;">₹ {{ number_format($pr['pump_rate'], 2) }} / m³</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
@endif
