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
        
        $plant = Plant::factory()->create(['name' => 'Main Plant']);
        session(['active_plant_id' => $plant->id]);
        Product::factory()->create(['title' => 'Sand', 'code' => 'SND01']);
        Patron::factory()->create(['name' => 'Test Client']);
    }

    public function test_can_list_quotations()
    {
        $plant = Plant::first();
        Quotation::factory(3)->create(['plant_id' => $plant->id]);

        $response = $this->get(route('quotations.index'));

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->component('Quotations/Index')
            ->has('quotations', 3)
        );
    }

    public function test_can_create_tax_inclusive_quotation()
    {
        $plant = Plant::first() ?: Plant::factory()->create();
        $patron = Patron::first() ?: Patron::factory()->create();
        $mixDesign = \App\Models\MixDesign::factory()->create(['plant_id' => $plant->id]);
        
        $payload = [
            'patron_id' => $patron->id,
            'site_id' => \App\Models\Site::factory()->create(['plant_id' => $plant->id])->id,
            'sales_executive_id' => null,
            'concrete_pump' => null,
            'quote_date' => now()->toDateString(),
            'validity_date' => now()->addDays(5)->toDateString(),
            'is_tax_inclusive' => true,
            'amount_untaxed' => 95.24,
            'tax_amount' => 4.76,
            'amount_tax' => 4.76,
            'amount_total' => 100.00,
            'adjustment' => 0,
            'status' => 0,
            'items' => [
                [
                    'mix_design_id' => $mixDesign->id,
                    'uom_id' => 1,
                    'tax_id' => null,
                    'quantity' => 1,
                    'rate' => 100,
                    'untaxed_amount' => 95.24,
                    'tax_amount' => 4.76,
                    'amount_total' => 100,
                ]
            ]
        ];

        // Set session variables expected by controllers
        session(['active_plant_id' => $plant->id]);

        $response = $this->post(route('quotations.store'), $payload);

        $response->assertStatus(302); // Redirects back
        $this->assertDatabaseHas('mm_quotations', [
            'patron_id' => $patron->id,
            'is_tax_inclusive' => true,
            'amount_total' => 100.00,
        ]);
    }

    public function test_can_send_quotation_email()
    {
        \Illuminate\Support\Facades\Notification::fake();

        $plant = Plant::first();
        session(['active_plant_id' => $plant->id]);

        $patron = Patron::factory()->create([
            'plant_id' => $plant->id,
        ]);
        
        // Create contact with email for the patron
        $contactType = \App\Models\ContactType::first() ?: \App\Models\ContactType::factory()->create();
        $contact = \App\Models\Contact::create([
            'plant_id' => $plant->id,
            'patron_id' => $patron->id,
            'contact_type_id' => $contactType->id,
            'name' => 'John Doe',
            'email' => 'john@example.com',
            'is_primary' => true,
        ]);

        $quotation = Quotation::factory()->create([
            'plant_id' => $plant->id,
            'patron_id' => $patron->id,
            'status' => Quotation::STATUS_DRAFT,
        ]);

        $response = $this->post(route('quotations.send-email', $quotation->id));

        $response->assertStatus(302);
        $response->assertSessionHas('success');

        \Illuminate\Support\Facades\Notification::assertSentTo(
            new \Illuminate\Notifications\AnonymousNotifiable,
            \App\Notifications\QuotationSentNotification::class,
            function ($notification, $channels, $notifiable) use ($quotation) {
                return $notifiable->routes['mail'] === 'john@example.com';
            }
        );

        $quotation->refresh();
        $this->assertEquals(Quotation::STATUS_SENT, (int)$quotation->status);
    }
}
