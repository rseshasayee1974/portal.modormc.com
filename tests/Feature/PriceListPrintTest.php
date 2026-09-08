<?php

namespace Tests\Feature;

use App\Models\Entity;
use App\Models\MixDesign;
use App\Models\Patron;
use App\Models\Plant;
use App\Models\Quotation;
use App\Models\QuotationItem;
use App\Models\Site;
use App\Models\Tax;
use App\Models\User;
use App\Services\PrintDataFormatter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PriceListPrintTest extends TestCase
{
    use RefreshDatabase;

    public function test_price_list_print_includes_amount_column_and_hides_totals_breakdown(): void
    {
        $user = User::factory()->create();
        $entity = Entity::factory()->create();
        $plant = Plant::factory()->create(['entity_id' => $entity->id]);
        $patron = Patron::factory()->create(['plant_id' => $plant->id]);
        $site = Site::factory()->create(['patron_id' => $patron->id, 'plant_id' => $plant->id]);
        $tax = Tax::factory()->create(['tax_rate' => 18, 'plant_id' => $plant->id]);
        $mix = MixDesign::factory()->create(['plant_id' => $plant->id]);

        $quotation = Quotation::create([
            'plant_id'   => $plant->id,
            'patron_id'  => $patron->id,
            'site_id'    => $site->id,
            'status'     => 1,
            'quote_date' => now(),
            'validity_date' => now()->addDays(15),
        ]);

        QuotationItem::create([
            'quotation_id'   => $quotation->id,
            'mix_design_id'  => $mix->id,
            'tax_id'         => $tax->id,
            'quantity'       => 10,
            'rate'           => 4500,
            'untaxed_amount' => 45000,
            'tax_amount'     => 8100,
            'amount_total'   => 53100,
        ]);

        // Formatter test with isPriceList = true
        $data = PrintDataFormatter::fromQuotation($quotation, null, true);

        $this->assertEquals('PRICE LIST', $data['doc_title']);
        $this->assertTrue($data['settings']['pdf']['amount']);
        $this->assertFalse($data['settings']['pdf']['show_totals']);

        // Check view rendering with standard template
        $view = view('pdfs.templates.standard', ['data' => $data, 'is_pdf' => false])->render();
        
        $this->assertStringContainsString('Amount', $view);
        $this->assertStringNotContainsString('class="totals-split"', $view);
        $this->assertStringNotContainsString('class="breakdown-table"', $view);

        // Check view rendering with modern template
        $modernView = view('pdfs.templates.modern', ['data' => $data, 'is_pdf' => false])->render();
        $this->assertStringContainsString('Amount', $modernView);
        $this->assertStringNotContainsString('class="totals-block"', $modernView);

        // Check view rendering with box_layout template
        $boxView = view('pdfs.templates.box_layout', ['data' => $data, 'is_pdf' => false])->render();
        $this->assertStringContainsString('Amount', $boxView);
        $this->assertStringNotContainsString('class="totals-table"', $boxView);
    }
}
