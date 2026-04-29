<?php

namespace Database\Seeders;

use App\Models\Feature;
use App\Models\Plan;
use Illuminate\Database\Seeder;

class PlanSeeder extends Seeder
{
    public function run(): void
    {
        $plans = [
            [
                'plan_type' => 'Basic',
                'price_monthly' => 999,
                'monthly_plan_description' => 'Starter access for small teams.',
                'price_yearly' => 9990,
                'yearly_plan_description' => 'Starter access with annual savings.',
                'max_users' => 3,
                'is_active' => 1,
                'feature_values' => [
                    'INVENTORY_ACCESS' => true,
                    'HR_ACCESS' => false,
                    'ACCOUNTING_ACCESS' => false,
                    'API_ACCESS' => false,
                    'MONTHLY_INVOICES' => 100,
                    'MAX_USERS' => 3,
                ],
            ],
            [
                'plan_type' => 'Pro',
                'price_monthly' => 2499,
                'monthly_plan_description' => 'Balanced plan for growing organizations.',
                'price_yearly' => 24990,
                'yearly_plan_description' => 'Balanced annual plan for growth.',
                'max_users' => 25,
                'is_active' => 1,
                'feature_values' => [
                    'INVENTORY_ACCESS' => true,
                    'HR_ACCESS' => true,
                    'ACCOUNTING_ACCESS' => true,
                    'API_ACCESS' => false,
                    'MONTHLY_INVOICES' => 1000,
                    'MAX_USERS' => 25,
                ],
            ],
            [
                'plan_type' => 'Enterprise',
                'price_monthly' => 9999,
                'monthly_plan_description' => 'Full access for advanced operations.',
                'price_yearly' => 99990,
                'yearly_plan_description' => 'Full annual access for large teams.',
                'max_users' => 999,
                'is_active' => 1,
                'feature_values' => [
                    'INVENTORY_ACCESS' => true,
                    'HR_ACCESS' => true,
                    'ACCOUNTING_ACCESS' => true,
                    'API_ACCESS' => true,
                    'MONTHLY_INVOICES' => 10000,
                    'MAX_USERS' => 999,
                ],
            ],
        ];

        foreach ($plans as $planData) {
            $featureValues = $planData['feature_values'];
            unset($planData['feature_values']);

            $planData['features_json'] = collect($featureValues)
                ->map(fn ($value, $code) => is_bool($value)
                    ? sprintf('%s:%s', $code, $value ? 'true' : 'false')
                    : sprintf('%s:%s', $code, $value))
                ->values()
                ->all();

            $plan = Plan::query()->updateOrCreate(
                ['plan_type' => $planData['plan_type']],
                $planData
            );

            $featureIds = Feature::query()
                ->whereIn('code', array_keys($featureValues))
                ->pluck('id', 'code');

            foreach ($featureValues as $featureCode => $value) {
                $featureId = $featureIds[$featureCode] ?? null;

                if (!$featureId) {
                    continue;
                }

                $plan->planFeatures()->updateOrCreate(
                    ['feature_id' => $featureId],
                    ['value' => is_bool($value) ? ($value ? 'true' : 'false') : (string) $value]
                );
            }
        }
    }
}
