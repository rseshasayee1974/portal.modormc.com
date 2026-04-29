<?php

namespace Tests\Feature;

use App\Models\Country;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CountryControllerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->actingAs(User::factory()->create());
    }

    public function test_can_view_index()
    {
        Country::factory()->count(3)->create();

        $response = $this->get(route('countries.index'));

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->component('Countries/Index')
            ->has('countries', 3)
        );
    }

    public function test_can_store_country()
    {
        // Setup payload based on the columns
        $data = [
            'country_name' => 'Test Country',
            'country_code' => 'TC',
            'is_active' => 1
        ];

        $response = $this->postJson(route('countries.store'), $data);

        $response->assertStatus(200);
        $this->assertDatabaseHas('mm_countries', $data);
        $response->assertJsonStructure(['country', 'message']);
    }

    public function test_can_update_country()
    {
        $country = Country::factory()->create([
            'country_name' => 'Old Name',
            'country_code' => 'ON',
            'is_active' => 0
        ]);
        
        $newData = [
            'country_name' => 'New Name',
            'country_code' => 'NN',
            'is_active' => 1
        ];

        $response = $this->putJson(route('countries.update', $country->id), $newData);

        $response->assertStatus(200);
        $this->assertDatabaseHas('mm_countries', array_merge(['id' => $country->id], $newData));
        $response->assertJsonStructure(['country', 'message']);
    }

    public function test_can_destroy_country()
    {
        $country = Country::factory()->create();

        $response = $this->deleteJson(route('countries.destroy', $country->id));

        $response->assertStatus(200);
        
        if (in_array('Illuminate\Database\Eloquent\SoftDeletes', class_uses(Country::class))) {
            $this->assertSoftDeleted($country);
        } else {
            $this->assertDatabaseMissing('mm_countries', ['id' => $country->id]);
        }
    }
}
