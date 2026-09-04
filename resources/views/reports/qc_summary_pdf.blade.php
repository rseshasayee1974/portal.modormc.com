<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Quality Control Summary Report</title>
    <style>
        {!! $css ?? $report_css ?? (file_exists(public_path('css/reports/qc_summary_pdf.css')) ? file_get_contents(public_path('css/reports/qc_summary_pdf.css')) : (file_exists(public_path('css/reports/report_pdf.css')) ? file_get_contents(public_path('css/reports/report_pdf.css')) : '')) !!}
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
