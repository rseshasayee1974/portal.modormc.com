<?php

namespace Tests\Feature;

use App\Models\Currency;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CurrencyControllerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->actingAs(User::factory()->create());
    }

    public function test_can_view_index()
    {
        Currency::factory()->count(3)->create();

        $response = $this->get(route('currencies.index'));

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->component('Currencies/Index')
            ->has('currencies', 3)
        );
    }

    public function test_can_store_currency()
    {
        $data = [
            'currency_name' => 'US Dollar',
            'currency_code' => 'USD'
        ];

        $response = $this->postJson(route('currencies.store'), $data);

        $response->assertStatus(200);
        $this->assertDatabaseHas('mm_currencies', $data);
        $response->assertJsonStructure(['currency', 'message']);
    }

    public function test_can_update_currency()
    {
        $currency = Currency::factory()->create([
            'currency_name' => 'Old Currency',
            'currency_code' => 'OC'
        ]);
        
        $newData = [
            'currency_name' => 'New Currency',
            'currency_code' => 'NC'
        ];

        $response = $this->putJson(route('currencies.update', $currency->id), $newData);

        $response->assertStatus(200);
        $this->assertDatabaseHas('mm_currencies', array_merge(['id' => $currency->id], $newData));
        $response->assertJsonStructure(['currency', 'message']);
    }

    public function test_can_destroy_currency()
    {
        $currency = Currency::factory()->create();

        $response = $this->deleteJson(route('currencies.destroy', $currency->id));

        $response->assertStatus(200);
        
        if (in_array('Illuminate\Database\Eloquent\SoftDeletes', class_uses(Currency::class))) {
            $this->assertSoftDeleted($currency);
        } else {
            $this->assertDatabaseMissing('mm_currencies', ['id' => $currency->id]);
        }
    }
}
