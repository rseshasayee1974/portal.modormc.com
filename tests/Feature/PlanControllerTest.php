<?php

namespace Tests\Feature;

use App\Models\Plan;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PlanControllerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->actingAs(User::factory()->create());
    }

    public function test_can_view_index()
    {
        Plan::factory()->count(3)->create();

        $response = $this->get(route('plans.index'));

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->component('Plans/Index')
            ->has('plans', 3)
        );
    }

    public function test_can_store_plan()
    {
        $data = [
            'plan_type' => 'Pro Plan',
            'price_monthly' => 29.99,
            'monthly_plan_description' => 'Best value',
            'price_yearly' => 299.99,
            'yearly_plan_description' => 'Save 2 months',
            'max_users' => 10,
            'features_json' => ['feature 1', 'feature 2'],
            'is_active' => 1
        ];

        $response = $this->postJson(route('plans.store'), $data);

        $response->assertStatus(200);
        
        // JSON encode the features for db assertion since it's casted
        $dbData = $data;
        $dbData['features_json'] = json_encode($data['features_json']);
        
        $this->assertDatabaseHas('mm_plans', $dbData);
        $response->assertJsonStructure(['plan', 'message']);
    }

    public function test_can_update_plan()
    {
        $plan = Plan::factory()->create([
            'plan_type' => 'Basic Plan',
            'price_monthly' => 9.99,
            'price_yearly' => 99.99,
            'max_users' => 1,
            'is_active' => 0
        ]);
        
        $newData = [
            'plan_type' => 'Premium Plan',
            'price_monthly' => 50.00,
            'monthly_plan_description' => 'Desc',
            'price_yearly' => 500.00,
            'yearly_plan_description' => 'Desc year',
            'max_users' => 20,
            'features_json' => ['A', 'B'],
            'is_active' => 1
        ];

        $response = $this->putJson(route('plans.update', $plan->id), $newData);

        $response->assertStatus(200);
        
        $dbData = $newData;
        $dbData['features_json'] = json_encode($newData['features_json']);
        
        $this->assertDatabaseHas('mm_plans', array_merge(['id' => $plan->id], $dbData));
        $response->assertJsonStructure(['plan', 'message']);
    }

    public function test_can_destroy_plan()
    {
        $plan = Plan::factory()->create();

        $response = $this->deleteJson(route('plans.destroy', $plan->id));

        $response->assertStatus(200);
        
        if (in_array('Illuminate\Database\Eloquent\SoftDeletes', class_uses(Plan::class))) {
            $this->assertSoftDeleted($plan);
        } else {
            $this->assertDatabaseMissing('mm_plans', ['id' => $plan->id]);
        }
    }
}
