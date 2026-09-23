@php
    // Compatibility entry point: use the same complete GST layout as queued exports.
    $filters = $filters ?? [];
    $filters['from_date'] = $filters['from_date'] ?? $filters['start_date'] ?? $filters['start'] ?? now()->toDateString();
    $filters['to_date'] = $filters['to_date'] ?? $filters['end_date'] ?? $filters['end'] ?? now()->toDateString();
    $service = app(\App\Services\Reports\PurchaseRegisterService::class);
    $registerReport = $report ?? $service->buildFromRows($items ?? [], $filters);
@endphp
@include('reports.register_pdf', [
    'title' => 'Purchase Register',
    'report' => $registerReport,
    'filters' => $filters,
    'generated_at' => $generated_at ?? now()->format('d-m-Y H:i:s'),
])
