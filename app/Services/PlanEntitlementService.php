<?php

namespace App\Services;

use App\Models\EntitySubscription;
use App\Models\PlanFeature;

class PlanEntitlementService
{
    private const DEFAULT_TOKEN_LIMIT = 10000;
    private const DEFAULT_ALERT_THRESHOLD = 80;

    public function resolveForEntity(int $entityId): array
    {
        $subscription = EntitySubscription::query()
            ->with(['plan', 'status'])
            ->where('entity_id', $entityId)
            ->first();

        if (!$subscription) {
            return $this->defaults();
        }

        $status = mb_strtolower((string) optional($subscription->status)->status_name);
        if (!in_array($status, ['active', 'trial'], true)) {
            return [
                ...$this->defaults(),
                'subscription_status' => $status ?: 'unknown',
            ];
        }

        $features = PlanFeature::query()
            ->select('plan_features.value', 'features.code')
            ->join('features', 'features.id', '=', 'plan_features.feature_id')
            ->where('plan_features.plan_id', $subscription->plan_id)
            ->pluck('value', 'code');

        $tokenLimit = (int) ($features->get('token_limit') ?? $features->get('monthly_token_limit') ?? 0);
        $alertThreshold = (int) ($features->get('usage_alert_threshold') ?? self::DEFAULT_ALERT_THRESHOLD);

        if ($tokenLimit <= 0) {
            $jsonFeatures = (array) optional($subscription->plan)->features_json;
            $tokenLimit = (int) ($jsonFeatures['token_limit'] ?? $jsonFeatures['monthly_token_limit'] ?? self::DEFAULT_TOKEN_LIMIT);
            $alertThreshold = (int) ($jsonFeatures['usage_alert_threshold'] ?? $alertThreshold);
        }

        return [
            'token_limit' => max(1, $tokenLimit ?: self::DEFAULT_TOKEN_LIMIT),
            'usage_alert_threshold' => max(1, min(100, $alertThreshold)),
            'plan_type' => optional($subscription->plan)->plan_type ?? 'default',
            'subscription_status' => $status,
        ];
    }

    private function defaults(): array
    {
        return [
            'token_limit' => self::DEFAULT_TOKEN_LIMIT,
            'usage_alert_threshold' => self::DEFAULT_ALERT_THRESHOLD,
            'plan_type' => 'default',
            'subscription_status' => 'inactive',
        ];
    }
}
