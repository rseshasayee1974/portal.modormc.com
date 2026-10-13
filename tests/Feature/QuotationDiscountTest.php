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

    public function test_discount_is_optional_and_blank_values_mean_zero(): void
    {
        foreach ([[], ['discount'=>null, 'discount_type'=>null], ['discount'=>'', 'discount_type'=>''], ['discount'=>0]] as $optional) {
            $line = QuotationCalculator::items([array_merge(['quantity'=>1, 'rate'=>500], $optional)], false)[0];
            $this->assertEquals(0, $line['discount']);
            $this->assertEquals(0, $line['discount_amount']);
            $this->assertEquals(500, $line['amount_total']);
            $this->assertNull($line['discount_type']);
        }
    }

    public function test_create_and_edit_requests_accept_optional_types_and_normalize_labels(): void
    {
        foreach ([\App\Http\Requests\StoreQuotationRequest::class, \App\Http\Requests\UpdateQuotationRequest::class] as $class) {
            foreach ([['Amount', 10, 'amount'], ['fixed', 10, 'amount'], ['Percentage', 10, '%'], [' % ', 10, '%'], ['', null, null], ['none', 0, null]] as [$type, $value, $expected]) {
                $request = $class::create('/', 'POST', ['items'=>[['discount_type'=>$type, 'discount'=>$value]]]);
                (new \ReflectionMethod($request, 'prepareForValidation'))->invoke($request);
                $this->assertSame($expected, $request->input('items.0.discount_type'));
                $validation = \Illuminate\Support\Facades\Validator::make($request->all(), [
                    'items.*.discount_type'=>'nullable|in:amount,%', 'items.*.discount'=>'nullable|numeric|min:0',
                ]);
                $this->assertFalse($validation->fails());
            }
        }
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
