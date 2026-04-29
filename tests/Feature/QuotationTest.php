<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Quotation;
use App\Models\Product;
use App\Models\Patron;
use App\Models\Plant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class QuotationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        
        $user = User::factory()->create();
        $this->actingAs($user);
        
        Plant::factory()->create(['name' => 'Main Plant']);
        Product::factory()->create(['name' => 'Sand', 'code' => 'SND01']);
        Patron::factory()->create(['name' => 'Test Client']);
    }

    public function test_can_list_quotations()
    {
        Quotation::factory(3)->create();

        $response = $this->get(route('quotations.index'));

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->component('Quotations/Index')
            ->has('quotations', 3)
        );
    }
}
