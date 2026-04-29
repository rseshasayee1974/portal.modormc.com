<?php

namespace App\Services;

use App\Models\ApiUsageLog;
use App\Models\EntityUser;
use App\Models\UsageSummary;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class UsageService
{
    public function __construct(private readonly PlanEntitlementService $planEntitlementService)
    {
    }

    public function estimateTokens(string $text): int
    {
        return (int) max(1, ceil(mb_strlen($text) / 4));
    }

    public function validateContextAccess(User $user, int $entityId, ?int $plantId): bool
    {
        $query = EntityUser::query()
            ->where('user_id', $user->id)
            ->where('entity_id', $entityId);

        if ($plantId !== null) {
            $query->where('plant_id', $plantId);
        }

        return $query->exists();
    }

    public function checkPlanThreshold(User $user, int $entityId, ?int $plantId, int $incomingTokens): array
    {
        $month = now()->format('Y-m');
        $query = UsageSummary::query()
            ->where('user_id', $user->id)
            ->where('entity_id', $entityId)
            ->where('month', $month);

        if ($plantId === null) {
            $query->whereNull('plant_id');
        } else {
            $query->where('plant_id', $plantId);
        }

        $currentTokens = (int) $query->sum('tokens');

        $entitlements = $this->planEntitlementService->resolveForEntity($entityId);
        $limit = (int) $entitlements['token_limit'];
        $alertThreshold = (int) $entitlements['usage_alert_threshold'];
        $next = $currentTokens + $incomingTokens;
        $usagePercent = $limit > 0 ? round(($next / $limit) * 100, 2) : 100;

        return [
            'plan' => (string) $entitlements['plan_type'],
            'subscription_status' => (string) $entitlements['subscription_status'],
            'limit' => $limit,
            'current_tokens' => $currentTokens,
            'next_tokens' => $next,
            'usage_percent' => $usagePercent,
            'is_blocked' => $next > $limit,
            'alert_threshold' => $alertThreshold,
            'alert_80' => $usagePercent >= $alertThreshold,
        ];
    }

    public function track(User $user, int $entityId, ?int $plantId, string $module, int $tokensUsed, string $endpoint): void
    {
        DB::transaction(function () use ($user, $entityId, $plantId, $module, $tokensUsed, $endpoint): void {
            $now = Carbon::now();
            ApiUsageLog::query()->create([
                'user_id' => $user->id,
                'entity_id' => $entityId,
                'plant_id' => $plantId,
                'module' => $module,
                'tokens_used' => $tokensUsed,
                'endpoint' => $endpoint,
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            $summary = UsageSummary::query()->firstOrCreate(
                [
                    'user_id' => $user->id,
                    'entity_id' => $entityId,
                    'plant_id' => $plantId,
                    'module' => $module,
                    'date' => $now->toDateString(),
                ],
                [
                    'month' => $now->format('Y-m'),
                    'tokens' => 0,
                    'requests' => 0,
                ]
            );

            $summary->increment('tokens', $tokensUsed);
            $summary->increment('requests', 1);
        });
    }
}
