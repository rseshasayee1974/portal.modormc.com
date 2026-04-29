<?php

namespace Tests\Feature;

use App\Models\SubscriptionStatus;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SubscriptionStatusControllerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->actingAs(User::factory()->create());
    }

    public function test_can_view_index()
    {
        SubscriptionStatus::factory()->count(3)->create();

        $response = $this->get(route('subscription-statuses.index'));

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->component('SubscriptionStatuses/Index')
            ->has('subscriptionStatuses', 3)
        );
    }

    public function test_can_store_subscription_status()
    {
        $data = [
            'status_name' => 'Active'
        ];

        $response = $this->postJson(route('subscription-statuses.store'), $data);

        $response->assertStatus(200);
        $this->assertDatabaseHas('mm_subscription_statuses', $data);
        $response->assertJsonStructure(['subscriptionStatus', 'message']);
    }

    public function test_can_update_subscription_status()
    {
        $subscriptionStatus = SubscriptionStatus::factory()->create([
            'status_name' => 'Pending'
        ]);
        
        $newData = [
            'status_name' => 'Cancelled'
        ];

        $response = $this->putJson(route('subscription-statuses.update', $subscriptionStatus->id), $newData);

        $response->assertStatus(200);
        $this->assertDatabaseHas('mm_subscription_statuses', array_merge(['id' => $subscriptionStatus->id], $newData));
        $response->assertJsonStructure(['subscriptionStatus', 'message']);
    }

    public function test_can_destroy_subscription_status()
    {
        $subscriptionStatus = SubscriptionStatus::factory()->create();

        $response = $this->deleteJson(route('subscription-statuses.destroy', $subscriptionStatus->id));

        $response->assertStatus(200);
        
        if (in_array('Illuminate\Database\Eloquent\SoftDeletes', class_uses(SubscriptionStatus::class))) {
            $this->assertSoftDeleted($subscriptionStatus);
        } else {
            $this->assertDatabaseMissing('mm_subscription_statuses', ['id' => $subscriptionStatus->id]);
        }
    }
}
