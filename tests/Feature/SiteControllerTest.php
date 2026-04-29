<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\TestCase;

class SiteControllerTest extends TestCase
{
    /**
     * A basic feature test example.
     */
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        // Assuming Roles are already seeded or need to be for testing
        $this->artisan('db:seed', ['--class' => 'RoleSeeder']);
    }

    public function test_can_list_sites()
    {
        $user = \App\Models\User::factory()->create();
        $user->assignRole('Super Administrator');
        $this->actingAs($user);

        \App\Models\Site::factory()->count(3)->create();

        $response = $this->get(route('sites.index'));

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page->component('Sites/Index'));
    }

    public function test_can_store_site()
    {
        $user = \App\Models\User::factory()->create();
        $user->assignRole('Super Administrator');
        $this->actingAs($user);

        $plant = \App\Models\Plant::factory()->create();
        $siteData = [
            'plant_id' => $plant->id,
            'name' => 'Test Site',
            'code' => 'S-001',
            'type' => 'loading',
            'is_restricted' => false,
        ];

        $response = $this->post(route('sites.store'), $siteData);

        $response->assertStatus(302);
        $this->assertDatabaseHas('sites', ['name' => 'Test Site']);
    }

    public function test_can_update_site()
    {
        $user = \App\Models\User::factory()->create();
        $user->assignRole('Super Administrator');
        $this->actingAs($user);

        $site = \App\Models\Site::factory()->create();
        $updateData = [
            'plant_id' => $site->plant_id,
            'name' => 'Updated Name',
            'type' => 'unloading',
        ];

        $response = $this->put(route('sites.update', $site), $updateData);

        $response->assertStatus(302);
        $this->assertDatabaseHas('sites', ['id' => $site->id, 'name' => 'Updated Name']);
    }

    public function test_can_delete_site()
    {
        $user = \App\Models\User::factory()->create();
        $user->assignRole('Super Administrator');
        $this->actingAs($user);

        $site = \App\Models\Site::factory()->create();

        $response = $this->delete(route('sites.destroy', $site));

        $response->assertStatus(302);
        $this->assertSoftDeleted('sites', ['id' => $site->id]);
    }
}
