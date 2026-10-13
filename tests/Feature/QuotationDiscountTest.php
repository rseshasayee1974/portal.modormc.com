<?php
/*
Author: ragul-onemodo
Created: 2026-10-13 13:46:30 Asia/Calcutta (UTC+05:30)
*/
namespace Tests\Feature;

use App\Models\{Plant, Patron, Quotation, MixDesign, Tax};
use App\Services\Mobile\QuotationCalculator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\TestCase;
use Illuminate\Validation\ValidationException;

class QuotationDiscountTest extends TestCase
{
    use RefreshDatabase;

    public function test_amount_discount_is_persisted_and_recalculated_on_edit(): void
    {
        $plant = Plant::factory()->create();
        $patron = Patron::factory()->create(['plant_id'=>$plant->id]);
        $mix = MixDesign::factory()->create(['plant_id'=>$plant->id]);
        $tax = Tax::factory()->create(['plant_id'=>$plant->id, 'tax_rate'=>18]);
        $data = ['patron_id'=>$patron->id, 'quote_date'=>'2026-10-13', 'is_tax_inclusive'=>false,
            'items'=>[['mix_design_id'=>$mix->id, 'quantity'=>2, 'rate'=>500, 'tax_id'=>$tax->id,
                'discount_type'=>'amount', 'discount'=>100]]];
        $quote = Quotation::createWithItems($data, $plant->id);
        $item = $quote->items()->firstOrFail();
        $this->assertEquals(100, $item->discount_amount);
        $this->assertEquals(900, $item->untaxed_amount);
        $this->assertEquals(162, $item->tax_amount);
        $this->assertEquals(1062, $quote->fresh()->amount_total);
        $data['items'][0]['id']=$item->id;
        $data['items'][0]['discount_type']='%';
        $data['items'][0]['discount']=20;
        $quote->updateWithItems($data);
        $this->assertEquals(200, $item->fresh()->discount_amount);
        $this->assertEquals(944, $quote->fresh()->amount_total);
        $data['items'][0]['discount']=0;
        $quote->updateWithItems($data);
        $this->assertEquals(1180, $quote->fresh()->amount_total);
    }

    public function test_inclusive_percentage_discount_reduces_gross_before_tax_split(): void
    {
        $tax = Tax::factory()->create(['tax_rate'=>18]);
        $line = QuotationCalculator::items([['quantity'=>1, 'rate'=>1180, 'tax_id'=>$tax->id,
            'discount_type'=>'%', 'discount'=>10]], true)[0];
        $this->assertEquals(118, $line['discount_amount']);
        $this->assertEquals(900, $line['untaxed_amount']);
        $this->assertEquals(162, $line['tax_amount']);
        $this->assertEquals(1062, $line['amount_total']);
    }

    public function test_invalid_discounts_are_rejected(): void
    {
        foreach ([['discount_type'=>'%', 'discount'=>101], ['discount_type'=>'amount', 'discount'=>501],
            ['discount_type'=>'amount', 'discount'=>-1]] as $discount) {
            try {
                QuotationCalculator::items([array_merge(['quantity'=>1,'rate'=>500], $discount)], false);
                $this->fail('Invalid discount accepted');
            } catch (ValidationException $exception) {
                $this->assertArrayHasKey('items.0.discount', $exception->errors());
            }
        }
    }
}
