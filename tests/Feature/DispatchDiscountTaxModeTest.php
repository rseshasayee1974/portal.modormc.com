<?php
/*
Author: ragul-onemodo
Created: 2026-11-03 14:42:32 Asia/Calcutta (UTC+05:30)
*/

namespace Tests\Feature;

use App\Models\{CustomSetting, Dispatch, Invoice, MixDesign, Patron, Plant};
use App\Services\DispatchDiscountCalculator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\TestCase;
use Illuminate\Validation\ValidationException;

class DispatchDiscountTaxModeTest extends TestCase
{
    use RefreshDatabase;

    public function test_discount_reduces_tax_for_exclusive_and_inclusive_rates(): void
    {
        foreach ([[1000, false], [1180, true]] as [$rate, $inclusive]) {
            $amounts = DispatchDiscountCalculator::calculate([
                'delivered_qty' => 1, 'load_rate' => $rate, 'discount_amount' => 100,
            ], $inclusive, [9, 9]);
            $this->assertSame(900.0, $amounts['load_untax_amount']);
            $this->assertSame(162.0, $amounts['load_tax_amount']);
            $this->assertSame(1062.0, $amounts['load_total_amount']);
        }
        $amounts = DispatchDiscountCalculator::calculate([
            'delivered_qty' => 1, 'load_rate' => 1000, 'pump_charges' => 1000,
            'discount_amount' => 200, 'transport_expenses' => 50, 'round_off' => 0.25,
        ], false, [9, 9]);
        $this->assertSame(1800.0, $amounts['load_untax_amount']);
        $this->assertSame(162.0, $amounts['load_tax_amount']);
        $this->assertSame(2012.25, $amounts['load_total_amount']);
        $zero = DispatchDiscountCalculator::calculate(['delivered_qty' => 1, 'load_rate' => 1000, 'discount_amount' => 1000], false, [18]);
        $this->assertSame(0.0, $zero['load_total_amount']);
    }

    public function test_discount_cannot_exceed_subtotal(): void
    {
        $this->expectException(ValidationException::class);
        DispatchDiscountCalculator::calculate(['delivered_qty' => 1, 'load_rate' => 100, 'discount_amount' => 101], false, [18]);
    }

    public function test_setting_is_plant_specific_and_preserves_legacy_default(): void
    {
        $plant = Plant::factory()->create();
        $this->assertSame('after_tax', CustomSetting::dispatchDiscountTaxMode($plant->id));
        CustomSetting::create(['plant_id' => $plant->id, 'module_name' => 'batching', 'module_id' => 0,
            'settings' => ['dispatch_discount_tax_mode' => 'before_tax']]);
        $this->assertSame('before_tax', CustomSetting::dispatchDiscountTaxMode($plant->id));
        $this->assertSame('after_tax', CustomSetting::dispatchDiscountTaxMode($plant->id + 1));
    }

    public function test_invoice_does_not_deduct_before_tax_discount_twice(): void
    {
        $this->mock(\App\Accounting\AccountingPostingService::class, function ($mock) {
            $mock->shouldReceive('post')->once()->andReturn(new \App\Models\JournalEntry());
        });
        $plant = Plant::factory()->create();
        session(['active_plant_id' => $plant->id]);
        $patron = Patron::factory()->create(['plant_id' => $plant->id]);
        $design = MixDesign::factory()->create(['plant_id' => $plant->id, 'partner_id' => $patron->id]);
        $dispatch = Dispatch::withoutEvents(fn () => Dispatch::create([
            'plant_id' => $plant->id, 'customer_id' => $patron->id, 'mixdesign_id' => $design->id,
            'delivered_qty' => 1, 'load_rate' => 1000, 'discount_amount' => 100,
            'discount_tax_mode' => 'before_tax', 'load_untax_amount' => 900,
            'load_tax_amount' => 162, 'load_total_amount' => 1062, 'round_off' => 0,
        ]));
        $invoice = Invoice::createFromSource($dispatch, 'invoice', ['plant_id' => $plant->id, 'is_tax_inclusive' => false]);
        $this->assertEquals(900, $invoice->subtotal);
        $this->assertEquals(162, $invoice->tax_amount);
        $this->assertEquals(100, $invoice->discount_total);
        $this->assertEquals(0, $invoice->global_discount);
        $this->assertEquals(1062, $invoice->total_amount);
        $this->assertEquals(0, $invoice->round_off);
    }

    public function test_setting_and_dispatch_save_use_server_calculation(): void
    {
        $plant = Plant::factory()->create();
        $user = \App\Models\User::factory()->create();
        $user->assignRole(\Spatie\Permission\Models\Role::firstOrCreate(
            ['name' => 'Platform Admin', 'guard_name' => 'web'], ['code' => 'PLATFORM_ADMIN']
        ));
        $this->actingAs($user)->withSession(['active_plant_id' => $plant->id, 'active_entity_id' => $plant->entity_id]);
        $tax = \App\Models\Tax::factory()->create(['plant_id' => $plant->id, 'tax_rate' => 18]);
        $this->post(route('settings.customsetting.update'), ['module' => 'batching',
            'settings' => ['dispatch_discount_tax_mode' => 'before_tax']])->assertRedirect()->assertSessionHasNoErrors();
        $payload = ['payment_mode' => 'credit', 'dispatch_status' => 'Draft', 'delivered_qty' => 1,
            'status' => ['is_tax_inclusive' => false], 'financials' => [
                'load_rate' => 1000, 'load_tax_id' => $tax->id, 'discount_amount' => 100,
                'load_untax_amount' => 9999, 'load_tax_amount' => 9999, 'load_total_amount' => 9999, 'round_off' => 0,
            ]];
        $this->post(route('dispatches.store'), $payload)->assertRedirect()->assertSessionHasNoErrors();
        $dispatch = Dispatch::latest('id')->firstOrFail();
        $this->assertSame('before_tax', $dispatch->discount_tax_mode);
        $this->assertEquals(900, $dispatch->load_untax_amount);
        $this->assertEquals(162, $dispatch->load_tax_amount);
        $this->assertEquals(1062, $dispatch->load_total_amount);
        $payload['financials']['discount_amount'] = 200;
        $this->put(route('dispatches.update', $dispatch->id), $payload)->assertRedirect()->assertSessionHasNoErrors();
        $this->assertEquals(944, $dispatch->fresh()->load_total_amount);
        $this->post(route('settings.customsetting.update'), ['module' => 'batching',
            'settings' => ['dispatch_discount_tax_mode' => 'invalid']])->assertSessionHasErrors('settings.dispatch_discount_tax_mode');
        $this->post(route('settings.customsetting.update'), ['module' => 'batching',
            'settings' => ['dispatch_discount_tax_mode' => 'after_tax']])->assertRedirect()->assertSessionHasNoErrors();
        $payload['financials'] = array_replace($payload['financials'], ['discount_amount' => 100,
            'load_untax_amount' => 1000, 'load_tax_amount' => 180, 'load_total_amount' => 1080]);
        $this->put(route('dispatches.update', $dispatch->id), $payload)->assertRedirect()->assertSessionHasNoErrors();
        $this->assertSame('after_tax', $dispatch->fresh()->discount_tax_mode);
        $this->assertEquals(1080, $dispatch->fresh()->load_total_amount);
    }
}
