<x-mail::message>
# Scheduled {{ ucfirst(str_replace('_', ' ', $reportType)) }} Report

Hello,

The automated reporting scheduler has generated your scheduled report.

**Report Details:**
- **Report Type:** {{ ucfirst(str_replace('_', ' ', $reportType)) }}
- **Frequency:** {{ ucfirst($frequency) }}
- **Plant:** {{ $plantName }}
- **Date Range:** {{ $dateRange }}

Please find the generated report file attached to this email.

Thanks,<br>
{{ config('app.name') }} Automated Reporting
</x-mail::message>
