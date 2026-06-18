<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Plant;
use App\Models\Patron;
use App\Models\Product;
use App\Models\ProductUnit;
use App\Models\MixDesign;
use App\Models\MixDesignItem;
use App\Models\ConcreteGrade;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\TestCase;

class MixDesignTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;
    protected Plant $plant;
    protected Patron $patron;
    protected Product $product;
    protected ProductUnit $unit;
    protected ConcreteGrade $concreteGrade;

    protected function setUp(): void
    {
        parent::setUp();
        config(['app.key' => 'base64:o/uhgklRUIi8R9GE5ftPdxE+yRmWNQOie8gIb4XV14g=']);

        $this->user = User::factory()->create();
        $this->plant = Plant::factory()->create();
        $this->patron = Patron::factory()->create(['plant_id' => $this->plant->id]);
        $this->unit = ProductUnit::factory()->create();
        
        $this->concreteGrade = ConcreteGrade::create([
            'plant_id' => $this->plant->id,
            'name' => 'M25',
            'concrete_code' => 'M25',
            'status' => 1,
        ]);

        // Product needs plant_id
        $this->product = Product::factory()->create([
            'plant_id' => $this->plant->id,
            'unit_id' => $this->unit->id,
        ]);

        $this->withSession([
            'active_plant_id' => $this->plant->id,
            'active_entity_id' => $this->plant->entity_id,
        ]);
        
        session([
            'active_plant_id' => $this->plant->id,
            'active_entity_id' => $this->plant->entity_id,
        ]);
    }

    /**
     * A basic feature test example.
     */
    public function test_mix_design_page_loads_with_authenticated_user(): void
    {
        $response = $this->actingAs($this->user)->get('/inventory/mixdesigns');
        $response->assertStatus(200);
    }

    public function test_mix_design_can_be_stored(): void
    {
        $response = $this->actingAs($this->user)->post('/inventory/mixdesigns', [
            'partner_id' => $this->patron->id,
            'design_name' => 'Custom Mix 1',
            'design_code' => 'M1-CODE',
            'design_type' => 'M25',
            'unit_id' => $this->unit->id,
            'rate_per_qty' => 1500,
            'items' => [
                [
                    'product_id' => $this->product->id,
                    'uom_id' => $this->unit->id,
                    'rate' => 10.5,
                    'actual_quantity' => 100,
                    'cross_quantity' => 100,
                    'variation_quantity' => 0,
                ]
            ]
        ]);

        $response->assertSessionHasNoErrors();
        $response->assertRedirect();
        
        $this->assertDatabaseHas('mm_mix_designs', [
            'plant_id' => $this->plant->id,
            'partner_id' => $this->patron->id,
            'concrete_grade_id' => $this->concreteGrade->id,
            'design_name' => 'Custom Mix 1',
            'design_code' => 'M1-CODE',
        ]);

        $this->assertDatabaseHas('mm_mix_design_items', [
            'plant_id' => $this->plant->id,
            'product_id' => $this->product->id,
            'actual_quantity' => 100,
        ]);
    }

    public function test_mix_design_can_be_updated(): void
    {
        $mixDesign = MixDesign::factory()->create([
            'plant_id' => $this->plant->id,
            'partner_id' => $this->patron->id,
            'concrete_grade_id' => $this->concreteGrade->id,
            'unit_id' => $this->unit->id,
        ]);

        $item = MixDesignItem::create([
            'plant_id' => $this->plant->id,
            'mix_design_id' => $mixDesign->id,
            'product_id' => $this->product->id,
            'uom_id' => $this->unit->id,
            'rate' => 12.0,
            'actual_quantity' => 50,
            'created_by' => $this->user->id,
        ]);

        $response = $this->actingAs($this->user)->put("/inventory/mixdesigns/{$mixDesign->id}", [
            'partner_id' => $this->patron->id,
            'design_name' => 'Updated Mix Name',
            'design_code' => 'M2-CODE',
            'design_type' => 'M25',
            'unit_id' => $this->unit->id,
            'rate_per_qty' => 2000,
            'items' => [
                [
                    'id' => $item->id,
                    'product_id' => $this->product->id,
                    'uom_id' => $this->unit->id,
                    'rate' => 15.0,
                    'actual_quantity' => 60,
                    'cross_quantity' => 60,
                    'variation_quantity' => 0,
                ]
            ]
        ]);

        $response->assertSessionHasNoErrors();
        $response->assertRedirect();

        $this->assertDatabaseHas('mm_mix_designs', [
            'id' => $mixDesign->id,
            'design_name' => 'Updated Mix Name',
            'design_code' => 'M2-CODE',
        ]);

        $this->assertDatabaseHas('mm_mix_design_items', [
            'id' => $item->id,
            'rate' => 15.0,
            'actual_quantity' => 60,
        ]);
    }

    public function test_mix_design_can_be_deleted(): void
    {
        $mixDesign = MixDesign::factory()->create([
            'plant_id' => $this->plant->id,
            'partner_id' => $this->patron->id,
            'concrete_grade_id' => $this->concreteGrade->id,
            'unit_id' => $this->unit->id,
        ]);

        $response = $this->actingAs($this->user)->delete("/inventory/mixdesigns/{$mixDesign->id}");

        $response->assertSessionHasNoErrors();
        $response->assertRedirect();

        $this->assertSoftDeleted($mixDesign);
    }
}
