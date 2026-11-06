<?php
/*
Author: Cipher
Created: 2026-11-06 11:25:39 Asia/Calcutta (UTC+05:30)
*/

namespace Tests\Feature;

use App\Models\{Patron, Plant, Product, ProductUnit, Quantity, StockExhaust, Tax, User};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\TestCase;
use Spatie\Permission\Models\Role;

class StockExhaustPricingTest extends TestCase
{
    use RefreshDatabase;

    public function test_create_update_and_delete_preserve_pricing_and_stock(): void
    {
        $user = User::factory()->create();
        $plant = Plant::factory()->create();
        $unit = ProductUnit::factory()->create();
        $product = Product::factory()->create(['plant_id' => $plant->id, 'unit_id' => $unit->id]);
        $vendor = Patron::factory()->create(['plant_id' => $plant->id]);
        $tax = Tax::factory()->create(['plant_id' => $plant->id, 'tax_rate' => 18]);
        $stock = Quantity::create(['plant_id' => $plant->id, 'product_id' => $product->id, 'uom_id' => $unit->id,
            'quantity' => 20, 'opening_quantity' => 0, 'status' => 1]);
        $user->assignRole(Role::firstOrCreate(['name' => 'Platform Admin', 'guard_name' => 'web'], ['code' => 'PLATFORM_ADMIN']));
        $this->actingAs($user)->withSession(['active_plant_id' => $plant->id, 'active_entity_id' => $plant->entity_id]);
        $payload = ['partner_id' => $vendor->id, 'issued_date' => '2026-11-06', 'invoice_status' => 1,
            'bill_number' => 'STOCK-TEST-1', 'billed_date' => '2026-11-06', 'status' => 1,
            'is_tax_inclusive' => false, 'lines' => [['product_id' => $product->id, 'quantity_issued' => 2, 'issue_date' => '2026-11-06',
                'no_items_issued' => 1, 'units' => $unit->id, 'unit_price' => 118, 'tax_id' => $tax->id, 'discount_type' => '%', 'discount' => 10]]];

        $this->post(route('stock-exhausts.store'), $payload)->assertRedirect()->assertSessionHasNoErrors();
        $voucher = StockExhaust::latest('id')->firstOrFail();
        $this->assertFalse($voucher->is_tax_inclusive);
        $this->assertEquals(23.60, $voucher->discount_amount);
        $this->assertEquals(212.40, $voucher->untaxed_amount);
        $this->assertEquals(38.232, $voucher->tax_amount);
        $this->assertEquals(250.632, $voucher->total_amount);
        $this->assertEquals(250.632, $voucher->lines->first()->total_amount);
        $this->assertEquals(18, $stock->fresh()->quantity);

        $payload['is_tax_inclusive'] = true;
        $payload['lines'][0]['discount_type'] = 'amount';
        $payload['lines'][0]['discount'] = 0;
        $this->put(route('stock-exhausts.update', $voucher), $payload)->assertRedirect()->assertSessionHasNoErrors();
        $voucher->refresh();
        $this->assertTrue($voucher->is_tax_inclusive);
        $this->assertEquals(200, $voucher->untaxed_amount);
        $this->assertEquals(36, $voucher->tax_amount);
        $this->assertEquals(236, $voucher->total_amount);
        $this->assertEquals(236, $voucher->lines->first()->total_amount);
        $this->assertEquals(18, $stock->fresh()->quantity);

        $payload['lines'][0]['discount'] = 999;
        $this->put(route('stock-exhausts.update', $voucher), $payload)->assertSessionHasErrors('lines');
        $this->assertEquals(236, $voucher->fresh()->total_amount);
        $this->assertEquals(18, $stock->fresh()->quantity);

        $this->delete(route('stock-exhausts.destroy', $voucher))->assertRedirect();
        $this->assertSoftDeleted($voucher);
        $this->assertEquals(20, $stock->fresh()->quantity);
    }

    public function test_calculations_preserve_fractional_amounts_without_rounding(): void
    {
        $line = ['quantity_issued' => 0.33, 'unit_price' => 0.3333, 'discount_type' => 'amount', 'discount' => 0];
        $exclusive = \App\Services\StockExhaustPricing::line($line, 5, false);
        $inclusive = \App\Services\StockExhaustPricing::line($line, 5, true);
        $this->assertEqualsWithDelta(0.11548845, $exclusive['total_amount'], 1e-12);
        $this->assertEqualsWithDelta(0.109989, $exclusive['untaxed_amount'], 1e-12);
        $this->assertEqualsWithDelta(0.00549945, $exclusive['tax_amount'], 1e-12);
        $this->assertEqualsWithDelta(0.109989, $inclusive['total_amount'], 1e-12);
        $this->assertEqualsWithDelta(0.10475142857142857, $inclusive['untaxed_amount'], 1e-12);
        $this->assertEqualsWithDelta(0.00523757142857143, $inclusive['tax_amount'], 1e-12);
        $discounted = \App\Services\StockExhaustPricing::line(array_replace($line, ['discount_type' => '%', 'discount' => 100]), 5, false);
        $this->assertEqualsWithDelta(0.109989, $discounted['discount_amount'], 1e-12);
        $this->assertSame(0.0, $discounted['total_amount']);
        $noTax = \App\Services\StockExhaustPricing::line(['quantity_issued' => 0.1, 'unit_price' => 16, 'discount' => 1], 0, false);
        $this->assertEqualsWithDelta(0.6, $noTax['total_amount'], 1e-12);
        $this->assertSame(0.0, $noTax['tax_amount']);
    }

    public function test_tax_dropdown_accepts_sales_and_purchase_types_with_gst_groups(): void
    {
        $plant = Plant::factory()->create();
        session(['active_plant_id' => $plant->id]);
        $sales = Tax::factory()->create(['plant_id' => $plant->id, 'tax_type' => 'sales', 'tax_group' => 'GST']);
        $purchase = Tax::factory()->create(['plant_id' => $plant->id, 'tax_type' => 'purchase', 'tax_group' => 'IGST']);
        Tax::factory()->create(['plant_id' => $plant->id, 'tax_type' => 'sales', 'tax_group' => 'TDS']);
        Tax::factory()->create(['plant_id' => $plant->id, 'tax_type' => 'purchase', 'tax_group' => 'GST', 'status' => 0]);
        Tax::factory()->create(['tax_type' => 'sales', 'tax_group' => 'GST']);

        $options = TaxesDropdown(['sales', 'purchase'], ['GST', 'IGST']);
        $this->assertEqualsCanonicalizing([$sales->id, $purchase->id], $options->pluck('id')->all());
        $this->assertSame([$sales->id], TaxesDropdown('sales', ['GST', 'IGST'])->pluck('id')->all());
        foreach ($options as $tax) {
            $this->assertNotEmpty($tax->name);
        }
    }
}
