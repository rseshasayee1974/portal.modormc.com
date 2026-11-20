<?php

namespace App\Services\Reports;

use App\Models\Patron;
use App\Services\PlantContextService;

/**
 * Author: ragul-onemodo
 * Created: 2026-11-20 17:06:24 Asia/Calcutta (UTC+05:30)
 */
class PatronCreditLimitReportService implements ReportServiceInterface
{
    public function __construct(private readonly PlantContextService $ctx) {}

    public function generate(array $params): array
    {
        $plantId = $params['plant_id'] ?? $this->ctx->plantId() ?? session('active_plant_id');
        $balances = app(CustomerOutstandingReportService::class)
            ->creditBalancesForPlant($plantId ? (int) $plantId : null);

        $transactions = Patron::query()->where('plant_id', $plantId)
            ->whereNull('deleted_at')->orderBy('legal_name')
            ->get(['id', 'code', 'legal_name', 'credit_limit'])
            ->map(function (Patron $patron) use ($balances) {
                $limit = round((float) ($patron->credit_limit ?? 0), 2);
                $used = max(0, round((float) ($balances[$patron->id] ?? 0), 2));
                return [
                    'patron_id' => $patron->id,
                    'code' => $patron->code,
                    'patron_name' => $patron->legal_name,
                    'credit_limit' => $limit,
                    'used_credit' => $used,
                    'available_credit' => max(0, round($limit - $used, 2)),
                ];
            })->values();

        return [
            'transactions' => $transactions,
            'items' => $transactions,
            'total_credit_limit' => round($transactions->sum('credit_limit'), 2),
            'total_used_credit' => round($transactions->sum('used_credit'), 2),
            'total_available_credit' => round($transactions->sum('available_credit'), 2),
            'total_records' => $transactions->count(),
        ];
    }

    public function targetName(array $params): string
    {
        return 'Patron Credit Limit';
    }
}
