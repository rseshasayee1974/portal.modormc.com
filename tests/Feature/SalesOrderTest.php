<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Quotation;
use App\Models\SalesOrder;
use App\Models\WorkOrder;
use App\Models\Patron;
use App\Models\Site;
use App\Models\Plant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SalesOrderTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;
    protected Plant $plant;
    protected Patron $patron;
    protected Site $site;

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->user = User::factory()->create();
        $this->actingAs($this->user);
        
        $this->plant = Plant::factory()->create(['name' => 'Main Plant']);
        session(['active_plant_id' => $this->plant->id]);

        $this->patron = Patron::factory()->create(['legal_name' => 'Test Patron']);
        $this->site = Site::factory()->create(['name' => 'Test Site']);
    }

    public function test_can_convert_quotation_to_sales_order()
    {
        $this->withoutExceptionHandling();
        $quotation = Quotation::factory()->create([
            'plant_id' => $this->plant->id,
            'patron_id' => $this->patron->id,
            'site_id' => $this->site->id,
            'is_salesorder' => 0,
        ]);

        $response = $this->patch(route('quotations.convert', $quotation->id), [
            'is_salesorder' => 1,
        ]);

        $response->assertSessionHasNoErrors();
        $response->assertStatus(302);
        
        $quotation->refresh();
        $this->assertEquals(1, $quotation->is_salesorder);

        $salesOrder = SalesOrder::where('quotation_id', $quotation->id)->first();
        $this->assertNotNull($salesOrder);
        $this->assertEquals($this->user->id, $salesOrder->converted_by_user_id);
    }
}
