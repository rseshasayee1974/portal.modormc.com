<?php

namespace Tests\Feature;

use App\Models\Entity;
use App\Models\EntitySubscription;
use App\Models\Feature;
use App\Models\Plan;
use App\Models\SubscriptionStatus;
use App\Services\FeatureAccessService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FeatureAccessServiceTest extends TestCase
{
    use RefreshDatabase;

    protected FeatureAccessService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(FeatureAccessService::class);
    }

    public function test_can_resolve_boolean_and_metered_feature_access(): void
    {
        $entity = Entity::factory()->create();
        $plan = Plan::factory()->create([
            'plan_type' => 'Basic',
            'max_users' => 3,
        ]);
        $activeStatus = SubscriptionStatus::factory()->create([
            'status_name' => 'active',
        ]);

        $inventoryFeature = Feature::create([
            'name' => 'Inventory',
            'code' => 'INVENTORY_ACCESS',
            'type' => 'boolean',
            'is_active' => 1,
        ]);

        $invoiceFeature = Feature::create([
            'name' => 'Monthly Invoices',
            'code' => 'MONTHLY_INVOICES',
            'type' => 'metered',
            'is_active' => 1,
        ]);

        $plan->planFeatures()->create([
            'feature_id' => $inventoryFeature->id,
            'value' => 'true',
        ]);

        $plan->planFeatures()->create([
            'feature_id' => $invoiceFeature->id,
            'value' => '100',
        ]);

        EntitySubscription::factory()->create([
            'entity_id' => $entity->id,
            'plan_id' => $plan->id,
            'subscription_status_id' => $activeStatus->id,
            'billing_cycle' => 'monthly',
            'started_at' => now()->subDays(5),
            'expires_at' => now()->addDays(25),
        ]);

        $this->assertTrue($this->service->canAccessFeature($entity->id, 'INVENTORY_ACCESS'));
        $this->assertSame(100, $this->service->getFeatureValue($entity->id, 'MONTHLY_INVOICES'));
        $this->assertTrue($this->service->canAccessFeature($entity->id, 'MONTHLY_INVOICES', 10));

        $this->service->recordUsage($entity->id, 'MONTHLY_INVOICES', 95);

        $this->assertFalse($this->service->canAccessFeature($entity->id, 'MONTHLY_INVOICES', 10));
        $this->assertTrue($this->service->canAccessFeature($entity->id, 'MONTHLY_INVOICES', 5));
    }

    public function test_records_usage_in_the_same_period_bucket(): void
    {
        $entity = Entity::factory()->create();

        $this->service->recordUsage($entity->id, 'API_ACCESS', 2, '2026-04');
        $this->service->recordUsage($entity->id, 'API_ACCESS', 3, '2026-04');

        $this->assertSame(5, $this->service->getUsage($entity->id, 'API_ACCESS', '2026-04'));
        $this->assertDatabaseHas('mm_usage_logs', [
            'entity_id' => $entity->id,
            'feature_code' => 'API_ACCESS',
            'period' => '2026-04',
            'used_count' => 5,
        ]);
    }

    public function test_can_upgrade_now_and_schedule_downgrade_for_next_cycle(): void
    {
        $entity = Entity::factory()->create();
        $activeStatus = SubscriptionStatus::factory()->create([
            'status_name' => 'active',
        ]);

        $basic = Plan::factory()->create([
            'plan_type' => 'Basic',
            'price_monthly' => 1000,
            'price_yearly' => 10000,
        ]);

        $pro = Plan::factory()->create([
            'plan_type' => 'Pro',
            'price_monthly' => 2000,
            'price_yearly' => 20000,
        ]);

        $enterprise = Plan::factory()->create([
            'plan_type' => 'Enterprise',
            'price_monthly' => 5000,
            'price_yearly' => 50000,
        ]);

        $subscription = EntitySubscription::factory()->create([
            'entity_id' => $entity->id,
            'plan_id' => $basic->id,
            'subscription_status_id' => $activeStatus->id,
            'billing_cycle' => 'monthly',
            'started_at' => now()->subDays(10),
            'expires_at' => now()->addDays(20),
        ]);

        $upgrade = $this->service->upgradeSubscription($subscription, $pro);

        $this->assertSame($pro->id, $upgrade['subscription']->plan_id);
        $this->assertGreaterThan(0, $upgrade['prorated_amount']);

        $scheduled = $this->service->scheduleDowngrade($upgrade['subscription'], $enterprise, now()->addDays(20));

        $this->assertSame($enterprise->id, $scheduled->scheduled_plan_id);
        $this->assertNotNull($scheduled->scheduled_change_at);

        $applied = $this->service->applyScheduledChanges(now()->addDays(21));

        $this->assertSame(1, $applied);
        $this->assertSame($enterprise->id, $scheduled->fresh()->plan_id);
        $this->assertNull($scheduled->fresh()->scheduled_plan_id);
    }
}
