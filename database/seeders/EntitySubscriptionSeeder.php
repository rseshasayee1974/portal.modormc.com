<?php

namespace Database\Seeders;

use App\Models\Entity;
use App\Models\EntitySubscription;
use App\Models\Plan;
use App\Models\SubscriptionStatus;
use Illuminate\Database\Seeder;

class EntitySubscriptionSeeder extends Seeder
{
    public function run(): void
    {
        $activeStatusId = SubscriptionStatus::query()->where('status_name', 'active')->value('id');
        $trialStatusId = SubscriptionStatus::query()->where('status_name', 'trial')->value('id');

        if (!$activeStatusId || !$trialStatusId) {
            return;
        }

        $plans = Plan::query()->whereIn('plan_type', ['Basic', 'Pro', 'Enterprise'])->get()->keyBy('plan_type');
        $entities = Entity::query()->limit(3)->get();

        foreach ($entities as $index => $entity) {
            $planName = ['Basic', 'Pro', 'Enterprise'][$index] ?? 'Basic';
            $plan = $plans->get($planName) ?? $plans->first();

            if (!$plan) {
                continue;
            }

            EntitySubscription::query()->updateOrCreate(
                ['plant_id' => $entity->plants->first()?->id ?? 1],
                [
                    'plan_id' => $plan->id,
                    'scheduled_plan_id' => null,
                    'subscription_status_id' => $index === 0 ? $trialStatusId : $activeStatusId,
                    'billing_cycle' => 'monthly',
                    'started_at' => now()->subDays(15),
                    'expires_at' => now()->addDays(15),
                    'scheduled_change_at' => null,
                    'created_by' => 1,
                    'updated_by' => 1,
                    'deleted_by' => null,
                ]
            );
        }
    }
}
