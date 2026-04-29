<?php

namespace App\Services;

use App\Models\Billing;
use App\Models\UsageSummary;
use App\Models\User;

class BillingService
{
    public function __construct(private readonly PricingService $pricingService)
    {
    }

    public function generateMonthlyBilling(User $user, int $entityId, ?int $plantId, string $month): Billing
    {
        $query = UsageSummary::query()
            ->where('user_id', $user->id)
            ->where('entity_id', $entityId)
            ->where('month', $month);

        if ($plantId === null) {
            $query->whereNull('plant_id');
        } else {
            $query->where('plant_id', $plantId);
        }

        $rows = $query->get()
            ->groupBy('module');

        $breakdown = [];
        $total = 0.0;

        foreach ($rows as $module => $items) {
            $tokens = (int) $items->sum('tokens');
            $requests = (int) $items->sum('requests');
            $price = $this->pricingService->calculate((string) $module, $tokens);
            $moduleCost = round($price['token_cost'] + ($price['request_cost'] * $requests), 4);

            $breakdown[$module] = [
                'tokens' => $tokens,
                'requests' => $requests,
                'cost' => $moduleCost,
            ];

            $total += $moduleCost;
        }

        return Billing::query()->updateOrCreate(
            [
                'user_id' => $user->id,
                'entity_id' => $entityId,
                'plant_id' => $plantId,
                'month' => $month,
            ],
            [
                'total_amount' => round($total, 4),
                'breakdown_json' => $breakdown,
                'status' => 'pending',
            ]
        );
    }
}
