<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Quality Control Summary Report</title>
    <style>
        body { font-family: sans-serif; font-size: 11px; color: #1e293b; }
        .header { text-align: center; margin-bottom: 20px; border-bottom: 2px solid #0284c7; padding-bottom: 10px; }
        .header h1 { margin: 0; font-size: 18px; color: #0f172a; text-transform: uppercase; }
        .header p { margin: 4px 0 0; color: #64748b; font-size: 10px; }
        table { width: 100%; border-collapse: collapse; margin-top: 10px; }
        th { background-color: #f1f5f9; color: #334155; font-size: 10px; text-transform: uppercase; padding: 6px 8px; border: 1px solid #cbd5e1; text-align: left; }
        td { padding: 6px 8px; border: 1px solid #e2e8f0; }
        .status-pass { color: #16a34a; font-weight: bold; }
        .status-fail { color: #dc2626; font-weight: bold; }
        .footer { margin-top: 30px; text-align: right; font-size: 9px; color: #94a3b8; }
    </style>
</head>
<body>
    <div class="header">
        <h1>Quality Control Technical Report</h1>
        <p>Ready Mix Concrete Quality Control Summary &bull; Generated: {{ $generated_at }}</p>
    </div>

    <table>
        <thead>
            <tr>
                <th>Date</th>
                <th>Test No</th>
                <th>Sample No</th>
                <th>Material</th>
                <th>Test Name</th>
                <th>Status</th>
            </tr>
        </thead>
        <tbody>
            @foreach($tests as $test)
            <tr>
                <td>{{ substr($test->test_date, 0, 10) }}</td>
                <td><strong>{{ $test->test_no }}</strong></td>
                <td>{{ $test->sample->sample_no ?? '' }}</td>
                <td>{{ $test->sample->material->title ?? '' }}</td>
                <td>{{ $test->testType->name ?? '' }}</td>
                <td class="{{ strtolower($test->overall_status) === 'pass' ? 'status-pass' : 'status-fail' }}">
                    {{ strtoupper($test->overall_status) }}
                </td>
            </tr>
            @endforeach
        </tbody>
    </table>

    <div class="footer">
        Quality Assurance / Quality Control Department &bull; Confidential
    </div>
</body>
</html>
