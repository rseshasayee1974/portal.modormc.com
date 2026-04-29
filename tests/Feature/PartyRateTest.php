<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Plant;
use App\Models\PartyRate;
use App\Models\Patron;
use App\Models\Site;
use App\Models\ProductUnit;
use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PartyRateTest extends TestCase
{
    use RefreshDatabase;

    private $user;
    private $plant;

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->user = User::factory()->create();
        $this->actingAs($this->user);
        
        $this->plant = Plant::factory()->create();
        session(['active_plant_id' => $this->plant->id]);
    }

    public function test_can_list_party_rates()
    {
        PartyRate::factory(3)->create(['plant_id' => $this->plant->id]);

        $response = $this->get(route('party-rates.index'));

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->component('PartyRates/Index')
            ->has('rates', 3)
        );
    }

    public function test_can_create_party_rate()
    {
        $patron = Patron::factory()->create();
        $site = Site::factory()->create(['plant_id' => $this->plant->id]);
        $uom = ProductUnit::factory()->create();
        $product = Product::factory()->create();

        $response = $this->post(route('party-rates.store'), [
            'patron_id' => $patron->id,
            'loading_site' => $site->id,
            'unloading_site' => $site->id,
            'uom_id' => $uom->id,
            'payment_type' => 'Credit',
            'product_id' => $product->id,
            'product_rate' => 1000,
            'transport_rate' => 200,
            'rate' => 1200,
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('party_rates', [
            'patron_id' => $patron->id,
            'rate' => 1200,
        ]);
    }

    public function test_can_delete_party_rate()
    {
        $rate = PartyRate::factory()->create(['plant_id' => $this->plant->id]);

        $response = $this->delete(route('party-rates.destroy', $rate->id));

        $response->assertRedirect();
        $this->assertSoftDeleted($rate);
    }
}
