<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Patron;
use App\Models\Plant;
use App\Models\Tax;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\OrderTax;
use Illuminate\Support\Facades\Gate;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InvoiceTest extends TestCase
{
    use RefreshDatabase;

    private $user;
    private $plant;
    private $vendor;
    private $customer;
    private $tax;

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->user = User::factory()->create();
        $this->actingAs($this->user);
        Gate::before(fn () => true);
        
        $this->plant = Plant::factory()->create();
        session(['active_plant_id' => $this->plant->id]);
        
        $this->vendor = Patron::factory()->create(['name' => 'Demo Vendor']);
        $this->customer = Patron::factory()->create(['name' => 'Demo Customer']);
        
        $this->tax = Tax::factory()->create(['rate' => 18, 'status' => 1]);
    }

    public function test_can_list_invoices()
    {
        Invoice::factory(2)->create([
            'plant_id' => $this->plant->id,
            'vendor_id' => $this->vendor->id,
            'customer_id' => $this->customer->id,
        ]);

        $response = $this->get(route('invoices.index'));

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->component('Invoices/Index')
            ->has('invoices', 2)
        );
    }
    
    public function test_tax_split_intra_state_cgst_sgst()
    {
        $response = $this->post(route('invoices.store'), [
            'vendor_id' => $this->vendor->id,
            'customer_id' => $this->customer->id,
            'supplier_gstin' => '33AAAAA0000A1Z5', // TN
            'place_of_supply' => '33', // TN => Intra-state
            'invoice_date' => now()->format('Y-m-d'),
            'tax_id' => $this->tax->id,
            'items' => [
                [
                    'item_name' => 'Test Item',
                    'hsn_code' => '9999',
                    'quantity' => 1,
                    'price_unit' => 1000,
                    'discount_type' => '%',
                    'discount' => 0,
                ]
            ]
        ]);
        
        $response->assertRedirect();
        
        $invoice = Invoice::latest()->first();
        $this->assertEquals(1000, $invoice->subtotal);
        $this->assertEquals(180, $invoice->tax_amount);
        $this->assertEquals(1180, $invoice->total_amount);
        
        // Assert CGST 9% and SGST 9% created
        $taxes = OrderTax::where('order_type', Invoice::class)->where('order_id', $invoice->id)->get();
        $this->assertCount(2, $taxes);
        $this->assertEquals('CGST 9%', $taxes[0]->name);
        $this->assertEquals('SGST 9%', $taxes[1]->name);
        $this->assertEquals(90, $taxes[0]->amount);
        $this->assertEquals(90, $taxes[1]->amount);
    }
    
    public function test_tax_split_inter_state_igst()
    {
        $response = $this->post(route('invoices.store'), [
            'vendor_id' => $this->vendor->id,
            'customer_id' => $this->customer->id,
            'supplier_gstin' => '33AAAAA0000A1Z5', // TN
            'place_of_supply' => '27', // MH => Inter-state
            'invoice_date' => now()->format('Y-m-d'),
            'tax_id' => $this->tax->id,
            'items' => [
                [
                    'item_name' => 'Test Item',
                    'hsn_code' => '9999',
                    'quantity' => 1,
                    'price_unit' => 1000,
                    'discount_type' => '%',
                    'discount' => 0,
                ]
            ]
        ]);
        
        $response->assertRedirect();
        
        $invoice = Invoice::latest()->first();
        
        $taxes = OrderTax::where('order_type', Invoice::class)->where('order_id', $invoice->id)->get();
        $this->assertCount(1, $taxes);
        $this->assertEquals('IGST 18%', $taxes[0]->name);
        $this->assertEquals(180, $taxes[0]->amount);
    }
}
