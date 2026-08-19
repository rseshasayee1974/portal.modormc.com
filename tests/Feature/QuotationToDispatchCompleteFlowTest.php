<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Plant;
use App\Models\Patron;
use App\Models\Site;
use App\Models\MixDesign;
use App\Models\Tax;
use App\Models\ProductUnit;
use App\Models\Personnel;
use App\Models\Machine;
use App\Models\Quotation;
use App\Models\QuotationItem;
use App\Models\CustomerPO;
use App\Models\CustomerPOItem;
use App\Models\SalesOrder;
use App\Models\Batch;
use App\Models\Dispatch;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class QuotationToDispatchCompleteFlowTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;
    protected Plant $plant;
    protected Patron $customer;
    protected Site $site;
    protected MixDesign $mixDesign;
    protected Tax $tax;
    protected ProductUnit $uom;
    protected Personnel $salesExecutive;
    protected Personnel $driver;
    protected Machine $truck;

    protected function setUp(): void
    {
        parent::setUp();

        // 1. Setup User and Plant environment
        $this->user = User::factory()->create();
        $this->actingAs($this->user);

        $this->plant = Plant::factory()->create(['name' => 'Main Test Plant']);

        session([
            'active_plant_id' => $this->plant->id,
            'active_entity_id' => $this->plant->entity_id,
        ]);

        // 2. Setup Supporting Entities
        $this->customer = Patron::factory()->create([
            'plant_id' => $this->plant->id,
            'legal_name' => 'Apex Construction Corp',
            'email' => 'apex@construction.com',
            'mobile' => '9876543210',
        ]);

        $this->site = Site::factory()->create([
            'plant_id' => $this->plant->id,
            'name' => 'Tower A Site',
            'site_address_1' => '123 Boulevard Street',
        ]);

        $this->mixDesign = MixDesign::factory()->create([
            'plant_id' => $this->plant->id,
            'design_name' => 'M30-Standard',
            'design_code' => 'M30-STD',
            'rate_per_qty' => 4500.00,
        ]);

        $this->tax = Tax::factory()->create([
            'plant_id' => $this->plant->id,
            'tax_name' => 'GST 18%',
            'tax_rate' => 18.00,
        ]);

        $this->uom = ProductUnit::factory()->create([
            'unit_code' => 'CBM',
            'unit_name' => 'Cubic Meter',
        ]);

        $this->salesExecutive = Personnel::factory()->create([
            'id' => $this->user->id,
            'plant_id' => $this->plant->id,
            'first_name' => 'John',
            'last_name' => 'Doe',
        ]);

        $this->driver = Personnel::factory()->create([
            'plant_id' => $this->plant->id,
            'first_name' => 'Robert',
            'last_name' => 'Driver',
        ]);

        $this->truck = Machine::factory()->create([
            'plant_id' => $this->plant->id,
            'registration' => 'KA-01-AB-1234',
            'vehicle_model' => 'Transit Mixer 01',
        ]);
    }

    /**
     * Test complete end-to-end flow:
     * Quotation Creation -> Quotation Accepted -> Customer PO Creation -> Sales Order Creation -> Batching -> Dispatch
     * Verifying all fields carry forward cleanly without missing/null values or mismatches.
     */
    public function test_complete_flow_from_quotation_to_dispatch_with_data_integrity()
    {
        // =========================================================================
        // STEP 1: CREATE QUOTATION
        // =========================================================================
        $quotePayload = [
            'patron_id'          => $this->customer->id,
            'site_id'            => $this->site->id,
            'sales_executive_id' => $this->salesExecutive->id,
            'quote_date'         => now()->toDateString(),
            'validity_date'      => now()->addDays(15)->toDateString(),
            'is_tax_inclusive'   => false,
            'status'             => 0, // Draft
            'adjustment'         => 0,
            'amount_untaxed'     => 45500.00, // (4500 * 10) + 500 pump_rate
            'tax_amount'         => 8100.00,  // (4500 * 10) * 18%
            'amount_tax'         => 8100.00,
            'amount_total'       => 53600.00, // 45500 + 8100
            'items'              => [
                [
                    'mix_design_id'  => $this->mixDesign->id,
                    'quantity'       => 10.0,
                    'rate'           => 4500.00,
                    'tax_id'         => $this->tax->id,
                    'uom_id'         => $this->uom->id,
                    'pump_type'      => 1,
                    'pump_rate'      => 500.00,
                    'pump_rates'     => [
                        ['concrete_pump' => 1, 'pump_rate' => 500.00]
                    ],
                    'notes'          => 'Pump required for 4th floor slab.',
                    'untaxed_amount' => 45500.00,
                    'tax_amount'     => 8100.00,
                    'amount_total'   => 53600.00,
                ]
            ]
        ];

        $quoteResponse = $this->post(route('quotations.store'), $quotePayload);
        $quoteResponse->assertStatus(302);

        $quotation = Quotation::with('items')->latest('id')->first();

        $this->assertNotNull($quotation);
        $this->assertEquals($this->customer->id, $quotation->patron_id);
        $this->assertEquals($this->site->id, $quotation->site_id);
        $this->assertEquals($this->salesExecutive->id, $quotation->sales_executive_id);
        $this->assertEquals(0, $quotation->status);
        $this->assertCount(1, $quotation->items);

        $quoteItem = $quotation->items->first();
        $this->assertEquals($this->mixDesign->id, $quoteItem->mix_design_id);
        $this->assertEquals(10.0, (float)$quoteItem->quantity);
        $this->assertEquals(4500.00, (float)$quoteItem->rate);
        $this->assertEquals($this->tax->id, $quoteItem->tax_id);
        $this->assertEquals(500.00, (float)$quoteItem->pump_rate);

        // =========================================================================
        // STEP 2: ACCEPT QUOTATION
        // =========================================================================
        $quotation->update(['status' => 1]); // Accepted
        $this->assertEquals(1, $quotation->fresh()->status);

        // =========================================================================
        // STEP 3: CONVERT TO CUSTOMER PO (CREATE CUSTOMER PO FROM QUOTATION)
        // =========================================================================
        $poPayload = [
            'quotation_id'          => $quotation->id,
            'patron_id'             => $quotation->patron_id,
            'site_id'               => $quotation->site_id,
            'sales_executive_id'    => $quotation->sales_executive_id,
            'order_date'            => now()->toDateString(),
            'customer_po_reference' => 'PO-APEX-2026-001',
            'is_tax_inclusive'      => $quotation->is_tax_inclusive,
            'status'                => 1, // Confirmed
            'notes'                 => 'Special PO notes',
            'items'                 => [
                [
                    'mix_design_id' => $quoteItem->mix_design_id,
                    'quantity'      => $quoteItem->quantity,
                    'rate'          => $quoteItem->rate,
                    'tax_id'        => $quoteItem->tax_id,
                    'tax_amount'    => $quoteItem->tax_amount,
                    'pump_type'     => $quoteItem->pump_type,
                    'pump_rate'     => $quoteItem->pump_rate,
                ]
            ]
        ];

        $poResponse = $this->post(route('customer-po.store'), $poPayload);
        $poResponse->assertStatus(302);

        $customerPO = CustomerPO::with('items')->latest('id')->first();

        $this->assertNotNull($customerPO);
        $this->assertEquals($quotation->id, $customerPO->quotation_id);
        $this->assertEquals($quotation->patron_id, $customerPO->patron_id);
        $this->assertEquals($quotation->site_id, $customerPO->site_id);
        $this->assertEquals($quotation->sales_executive_id, $customerPO->sales_executive_id);
        $this->assertEquals('PO-APEX-2026-001', strtoupper($customerPO->customer_po_reference));
        $this->assertEquals(1, $customerPO->status);
        $this->assertCount(1, $customerPO->items);

        $poItem = $customerPO->items->first();
        $this->assertEquals($this->mixDesign->id, $poItem->mix_design_id);
        $this->assertEquals(10.0, (float)$poItem->quantity);
        $this->assertEquals(4500.00, (float)$poItem->rate);
        $this->assertEquals($this->tax->id, $poItem->tax_id);

        // =========================================================================
        // STEP 4: CREATE SALES ORDER FROM CUSTOMER PO
        // =========================================================================
        $soPayload = [
            'customer_po_id'     => $customerPO->id,
            'customer_id'        => $customerPO->patron_id,
            'site_id'            => $customerPO->site_id,
            'sales_executive_id' => $this->salesExecutive->id,
            'mix_design_id'      => $poItem->mix_design_id,
            'total_qty'          => $poItem->quantity,
            'rate'               => $poItem->rate,
            'tax_id'             => $poItem->tax_id,
            'pump_rate'          => 500.00,
            'is_tax_inclusive'   => $customerPO->is_tax_inclusive,
            'delivery_date'      => now()->toDateString(),
            'notes'              => $customerPO->notes,
            'status'             => SalesOrder::STATUS_IN_PROGRESS,
        ];

        $soResponse = $this->post(route('salesorders.store'), $soPayload);
        $soResponse->assertSessionHasNoErrors();
        $soResponse->assertStatus(302);

        $salesOrder = SalesOrder::latest('id')->first();
        // dump($salesOrder->toArray());

        $this->assertNotNull($salesOrder);
        $this->assertEquals($customerPO->id, $salesOrder->customer_po_id);
        $this->assertEquals($customerPO->patron_id, $salesOrder->customer_id);
        $this->assertEquals($customerPO->site_id, $salesOrder->site_id);
        $this->assertEquals($this->mixDesign->id, $salesOrder->mix_design_id);
        $this->assertEquals(10.0, (float)$salesOrder->total_qty);
        $this->assertEquals($this->salesExecutive->id, $salesOrder->sales_executive_id ?? $customerPO->sales_executive_id);
        $this->assertEquals($this->tax->id, $salesOrder->tax_id);
        $this->assertEquals(SalesOrder::STATUS_IN_PROGRESS, $salesOrder->status);

        // =========================================================================
        // STEP 5: BATCHING
        // =========================================================================
        $batchPayload = [
            'sales_order_id'      => $salesOrder->id,
            'batch_size'          => 6.0,
            'start_time'          => now()->subMinutes(30)->toDateTimeString(),
            'end_time'            => now()->toDateTimeString(),
            'status'              => Batch::STATUS_DISPATCHED,
            'operator_id'         => $this->user->id,
            'shift'               => 'Day',
            'truck_id'            => $this->truck->id,
            'transport_id'        => null,
            'driver_id'           => $this->driver->id,
            'sales_executive_id'  => $salesOrder->sales_executive_id,
            'uom_id'              => $this->uom->id,
            'empty_weight_truck'  => 12000,
            'loaded_weight_truck' => 26400,
            'net_weight'          => 14400,
            'concrete_pump'       => 'Boom Pump',
            'materials'           => [],
        ];

        $batchResponse = $this->post(route('batches.store'), $batchPayload);
        $batchResponse->assertStatus(302);

        $batch = Batch::with('dispatches')->latest('id')->first();

        $this->assertNotNull($batch);
        $this->assertEquals($salesOrder->id, $batch->sales_order_id);
        $this->assertEquals(6.0, (float)$batch->batch_size);
        $this->assertEquals(Batch::STATUS_DISPATCHED, $batch->status);

        // Verify Batch getters carry forward Sales Order details
        $this->assertEquals(4500.00, (float)$batch->rate);
        $this->assertEquals($this->tax->id, $batch->tax_id);

        // =========================================================================
        // STEP 6: DISPATCH VERIFICATION
        // =========================================================================
        $dispatch = Dispatch::where('batch_id', $batch->id)->first();

        $this->assertNotNull($dispatch);
        $this->assertEquals($salesOrder->id, $dispatch->sales_order_id);
        $this->assertEquals($batch->id, $dispatch->batch_id);
        $this->assertEquals($salesOrder->customer_id, $dispatch->customer_id);
        $this->assertEquals($salesOrder->mix_design_id, $dispatch->mixdesign_id);
        $this->assertEquals($salesOrder->site_id, $dispatch->unload_site_id);
        $this->assertEquals($this->truck->id, $dispatch->truck_id);
        $this->assertEquals($this->driver->id, $dispatch->driver_id);
        $this->assertEquals($this->salesExecutive->id, $dispatch->sales_executive_id);
        $this->assertEquals(12000, (float)$dispatch->empty_weight_truck);
        $this->assertEquals(26400, (float)$dispatch->loaded_weight_truck);
        $this->assertEquals(14400, (float)$dispatch->net_weight);

        // Verify Dispatch Quantities and Rates
        $this->assertEquals(6.0, (float)$dispatch->delivered_qty); // Volumetric batch size
        $this->assertEquals(4500.00, (float)$dispatch->load_rate);
        $this->assertEquals(27000.00, (float)$dispatch->load_untax_amount); // 6.0 * 4500

        // =========================================================================
        // DATA INTEGRITY & CONTINUITY CHECKS
        // =========================================================================
        $this->assertEquals($quotation->patron_id, $customerPO->patron_id);
        $this->assertEquals($customerPO->patron_id, $salesOrder->customer_id);
        $this->assertEquals($salesOrder->customer_id, $dispatch->customer_id);

        $this->assertEquals($quotation->site_id, $customerPO->site_id);
        $this->assertEquals($customerPO->site_id, $salesOrder->site_id);
        $this->assertEquals($salesOrder->site_id, $dispatch->unload_site_id);

        $this->assertEquals($quoteItem->mix_design_id, $poItem->mix_design_id);
        $this->assertEquals($poItem->mix_design_id, $salesOrder->mix_design_id);
        $this->assertEquals($salesOrder->mix_design_id, $dispatch->mixdesign_id);

        $this->assertEquals($quoteItem->rate, $poItem->rate);
        $this->assertEquals($poItem->rate, $salesOrder->rate);
        $this->assertEquals($salesOrder->rate, $dispatch->load_rate);
    }
}
