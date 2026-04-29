<?php

namespace Tests\Feature;

use App\Models\StateCode;
use App\Models\Country;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StateCodeControllerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->actingAs(User::factory()->create());
    }

    public function test_can_view_index()
    {
        Country::factory()->count(2)->create();
        StateCode::factory()->count(3)->create();

        $response = $this->get(route('state-codes.index'));

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->component('StateCodes/Index')
            ->has('stateCodes', 3)
            ->has('countries')
        );
    }

    public function test_can_store_state_code()
    {
        $country = Country::factory()->create();
        
        $data = [
            'country_id' => $country->id,
            'state_code' => 'CA',
            'state_name' => 'California'
        ];

        $response = $this->postJson(route('state-codes.store'), $data);

        $response->assertStatus(200);
        $this->assertDatabaseHas('mm_state_codes', $data);
        $response->assertJsonStructure(['stateCode', 'message']);
    }

    public function test_can_update_state_code()
    {
        $country1 = Country::factory()->create();
        $country2 = Country::factory()->create();
        
        $stateCode = StateCode::factory()->create([
            'country_id' => $country1->id,
            'state_name' => 'Texas',
            'state_code' => 'TX'
        ]);
        
        $newData = [
            'country_id' => $country2->id,
            'state_name' => 'New York',
            'state_code' => 'NY'
        ];

        $response = $this->putJson(route('state-codes.update', $stateCode->id), $newData);

        $response->assertStatus(200);
        $this->assertDatabaseHas('mm_state_codes', array_merge(['id' => $stateCode->id], $newData));
        $response->assertJsonStructure(['stateCode', 'message']);
    }

    public function test_can_destroy_state_code()
    {
        $stateCode = StateCode::factory()->create();

        $response = $this->deleteJson(route('state-codes.destroy', $stateCode->id));

        $response->assertStatus(200);
        
        if (in_array('Illuminate\Database\Eloquent\SoftDeletes', class_uses(StateCode::class))) {
            $this->assertSoftDeleted($stateCode);
        } else {
            $this->assertDatabaseMissing('mm_state_codes', ['id' => $stateCode->id]);
        }
    }
}
