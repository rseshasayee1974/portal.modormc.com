<?php

namespace App\Services;

use App\Models\EntitySubscription;
use App\Models\Feature;
use App\Models\Plan;
use App\Models\PlanFeature;
use App\Models\SubscriptionStatus;
use App\Models\UsageLog;
use Carbon\Carbon;

class FeatureAccessService
{
    public function getActiveSubscription(int $entityId): ?EntitySubscription
    {
        return EntitySubscription::query()
            ->with(['plan.planFeatures.feature', 'status'])
            ->where('entity_id', $entityId)
            ->whereHas('status', fn ($query) => $query->whereIn('status_name', ['active', 'trial']))
            ->where('started_at', '<=', now())
            ->where(function ($query) {
                $query->whereNull('expires_at')
                    ->orWhere('expires_at', '>=', now());
            })
            ->latest('started_at')
            ->first();
    }

    public function getFeature(string $featureCode): ?Feature
    {
        return Feature::query()
            ->where('code', $featureCode)
            ->where('is_active', 1)
            ->first();
    }

    public function getPlanFeature(int $planId, int $featureId): ?PlanFeature
    {
        return PlanFeature::query()
            ->where('plan_id', $planId)
            ->where('feature_id', $featureId)
            ->first();
    }

    public function getFeatureValue(int $entityId, string $featureCode): string|int|bool|null
    {
        $subscription = $this->getActiveSubscription($entityId);
        $feature = $this->getFeature($featureCode);

        if (!$subscription || !$feature) {
            return null;
        }

        $planFeature = $this->getPlanFeature($subscription->plan_id, $feature->id);

        if (!$planFeature) {
            return null;
        }

        return match ($feature->type) {
            'boolean' => $this->toBool($planFeature->value),
            default => is_numeric($planFeature->value) ? (int) $planFeature->value : $planFeature->value,
        };
    }

    public function canAccessFeature(int $entityId, string $featureCode, int $requestedUsage = 1): bool
    {
        $subscription = $this->getActiveSubscription($entityId);
        $feature = $this->getFeature($featureCode);

        if (!$subscription || !$feature) {
            return false;
        }

        $planFeature = $this->getPlanFeature($subscription->plan_id, $feature->id);

        if (!$planFeature) {
            return false;
        }

        if ($feature->type === 'boolean') {
            return $this->toBool($planFeature->value);
        }

        $limit = (int) $planFeature->value;
        $usage = $this->getUsage($entityId, $featureCode);

        return ($usage + max($requestedUsage, 0)) <= $limit;
    }

    public function getUsage(int $entityId, string $featureCode, ?string $period = null): int
    {
        $period ??= now()->format('Y-m');

        return (int) UsageLog::query()
            ->where('entity_id', $entityId)
            ->where('feature_code', $featureCode)
            ->where('period', $period)
            ->value('used_count');
    }

    public function recordUsage(int $entityId, string $featureCode, int $usedCount = 1, ?string $period = null): UsageLog
    {
        $period ??= now()->format('Y-m');

        $usageLog = UsageLog::query()->firstOrNew([
            'entity_id' => $entityId,
            'feature_code' => $featureCode,
            'period' => $period,
        ]);

        $usageLog->used_count = ((int) $usageLog->used_count) + max($usedCount, 0);
        $usageLog->save();

        return $usageLog;
    }

    public function calculateProratedUpgradeAmount(EntitySubscription $subscription, Plan|int $targetPlan, ?Carbon $asOf = null): float
    {
        $asOf ??= now();
        $targetPlan = $targetPlan instanceof Plan ? $targetPlan : Plan::findOrFail($targetPlan);
        $subscription->loadMissing('plan');

        $currentPrice = $subscription->billing_cycle === 'yearly'
            ? (float) $subscription->plan?->price_yearly
            : (float) $subscription->plan?->price_monthly;

        $targetPrice = $subscription->billing_cycle === 'yearly'
            ? (float) $targetPlan->price_yearly
            : (float) $targetPlan->price_monthly;

        $fullCycleEnd = $this->resolveNextBillingDate($subscription, $asOf);
        $cycleStart = Carbon::parse($subscription->started_at);
        $totalSeconds = max($cycleStart->diffInSeconds($fullCycleEnd), 1);
        $remainingSeconds = max($asOf->diffInSeconds($fullCycleEnd, false), 0);
        $remainingRatio = $remainingSeconds / $totalSeconds;

        return round(max($targetPrice - $currentPrice, 0) * $remainingRatio, 2);
    }

    public function upgradeSubscription(EntitySubscription $subscription, Plan|int $targetPlan, ?string $billingCycle = null): array
    {
        $targetPlan = $targetPlan instanceof Plan ? $targetPlan : Plan::findOrFail($targetPlan);
        $billingCycle ??= $subscription->billing_cycle ?: 'monthly';
        $proratedAmount = $this->calculateProratedUpgradeAmount($subscription, $targetPlan);
        $activeStatusId = $this->resolveStatusId('active');

        $subscription->update([
            'plan_id' => $targetPlan->id,
            'billing_cycle' => $billingCycle,
            'subscription_status_id' => $activeStatusId ?? $subscription->subscription_status_id,
            'scheduled_plan_id' => null,
            'scheduled_change_at' => null,
        ]);

        return [
            'subscription' => $subscription->fresh(['plan', 'status']),
            'prorated_amount' => $proratedAmount,
        ];
    }

    public function scheduleDowngrade(EntitySubscription $subscription, Plan|int $targetPlan, ?Carbon $effectiveAt = null): EntitySubscription
    {
        $targetPlan = $targetPlan instanceof Plan ? $targetPlan : Plan::findOrFail($targetPlan);
        $effectiveAt ??= $this->resolveNextBillingDate($subscription);

        $subscription->update([
            'scheduled_plan_id' => $targetPlan->id,
            'scheduled_change_at' => $effectiveAt,
        ]);

        return $subscription->fresh(['plan', 'scheduledPlan']);
    }

    public function applyScheduledChanges(?Carbon $asOf = null): int
    {
        $asOf ??= now();
        $subscriptions = EntitySubscription::query()
            ->whereNotNull('scheduled_plan_id')
            ->whereNotNull('scheduled_change_at')
            ->where('scheduled_change_at', '<=', $asOf)
            ->get();

        foreach ($subscriptions as $subscription) {
            $subscription->update([
                'plan_id' => $subscription->scheduled_plan_id,
                'scheduled_plan_id' => null,
                'scheduled_change_at' => null,
            ]);
        }

        return $subscriptions->count();
    }

    public function resolveNextBillingDate(EntitySubscription $subscription, ?Carbon $from = null): Carbon
    {
        $from ??= now();
        $anchor = Carbon::parse($subscription->started_at);

        while ($anchor->lte($from)) {
            $anchor = $subscription->billing_cycle === 'yearly'
                ? $anchor->copy()->addYear()
                : $anchor->copy()->addMonth();
        }

        return $anchor;
    }

    protected function resolveStatusId(string $statusName): ?int
    {
        return SubscriptionStatus::query()
            ->where('status_name', $statusName)
            ->value('id');
    }

    protected function toBool(string|int|bool|null $value): bool
    {
        if (is_bool($value)) {
            return $value;
        }

        return in_array(strtolower((string) $value), ['1', 'true', 'yes', 'enabled'], true);
    }
}
