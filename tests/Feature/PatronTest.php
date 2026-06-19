<?php

namespace Tests\Feature;

use App\Models\Patron;
use App\Models\Plant;
use App\Models\Entity;
use App\Models\User;
use App\Models\Contact;
use App\Models\ContactType;
use App\Models\AddressType;
use App\Models\BankAccountType;
use App\Models\StateCode;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PatronTest extends TestCase
{
    use RefreshDatabase;

    protected $user;
    protected $entity;
    protected $plant;
    protected $contactType;
    protected $addressType;
    protected $bankAccountType;
    protected $stateCode;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
        $this->entity = Entity::factory()->create();
        $this->plant = Plant::factory()->create(['entity_id' => $this->entity->id]);

        session(['active_entity_id' => $this->entity->id]);
        session(['active_plant_id' => $this->plant->id]);

        $this->actingAs($this->user);

        // Seed basic types
        $this->contactType = ContactType::firstOrCreate(['id' => 1], ['type' => 'Office']);
        $this->addressType = AddressType::firstOrCreate(['id' => 1], ['type' => 'Billing']);
        $this->bankAccountType = BankAccountType::firstOrCreate(['id' => 1], ['type' => 'Current']);
        $this->stateCode = StateCode::factory()->create(['state_code' => 'KA', 'state_name' => 'Karnataka']);
    }

    public function test_can_view_patron_index()
    {
        $response = $this->get(route('patrons.index'));

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page->component('Patrons/Index'));
    }

    public function test_can_create_patron_with_relations()
    {
        $data = [
            'patron_type' => ['Customer'],
            'legal_name' => 'Acme Corporation',
            'operational_status' => 'active',
            'status' => true,
            'displayed' => true,
            'contact_name' => 'John Doe',
            'contact_email' => 'john@acme.com',
            'contact_mobile' => '9876543210',
            'contact_type_id' => $this->contactType->id,
            'contact_status' => true,
            'contact_is_primary' => true,
            'address_line_1' => '123 Main St',
            'address_city' => 'Bangalore',
            'address_state_id' => $this->stateCode->id,
            'address_state_code' => 'KA',
            'address_zipcode' => '560001',
            'address_type_id' => $this->addressType->id,
            'address_status' => true,
            'address_is_primary' => true,
            'bank_accounts' => [
                [
                    'bank_account_type' => $this->bankAccountType->id,
                    'account_holder_name' => 'Acme Corp Account',
                    'account_number' => '1234567890',
                    'bank_name' => 'State Bank of India',
                    'branch_name' => 'Main Branch',
                    'ifsc_code' => 'SBIN0001234',
                    'is_primary' => true,
                    'status' => true,
                ]
            ]
        ];

        $response = $this->post(route('patrons.store'), $data);

        $response->assertRedirect();
        
        // Assert Patron
        $this->assertDatabaseHas('mm_patrons', [
            'legal_name' => 'Acme Corporation',
            'plant_id' => $this->plant->id,
        ]);

        $patron = Patron::where('legal_name', 'Acme Corporation')->first();

        // Assert Contact
        $this->assertDatabaseHas('mm_contacts', [
            'patron_id' => $patron->id,
            'name' => 'John Doe',
            'mobile' => '9876543210',
        ]);

        $contact = Contact::where('patron_id', $patron->id)->first();

        // Assert Address
        $this->assertDatabaseHas('mm_addresses', [
            'line_1' => '123 Main St',
            'city' => 'Bangalore',
            'zipcode' => '560001',
        ]);

        // Assert Bank Account
        $this->assertDatabaseHas('mm_patron_bank_accounts', [
            'patron_id' => $patron->id,
            'account_number' => '1234567890',
            'bank_name' => 'State Bank of India',
        ]);
    }

    public function test_can_update_patron_and_relations()
    {
        $patron = Patron::factory()->create([
            'plant_id' => $this->plant->id,
            'entity_id' => $this->entity->id,
            'legal_name' => 'Old Legal Name',
        ]);

        // Create initial contact and bank account
        $contact = $patron->contacts()->create([
            'plant_id' => $this->plant->id,
            'contact_type_id' => $this->contactType->id,
            'name' => 'Old Contact Name',
            'email' => 'old@test.com',
            'mobile' => '9999999999',
            'is_primary' => true,
            'status' => true,
        ]);

        $bankAccount = $patron->bankAccounts()->create([
            'plant_id' => $this->plant->id,
            'bank_account_type' => $this->bankAccountType->id,
            'account_holder_name' => 'Old Holder',
            'account_number' => '8888888888',
            'bank_name' => 'Old Bank',
            'ifsc_code' => 'IFSC0001111',
            'is_primary' => true,
            'status' => true,
        ]);

        // Update data
        $data = [
            'patron_type' => ['Customer'],
            'legal_name' => 'New Legal Name',
            'operational_status' => 'active',
            'status' => true,
            'displayed' => true,
            'contact_name' => 'New Contact Name',
            'contact_email' => 'new@test.com',
            'contact_mobile' => '8888888888',
            'contact_type_id' => $this->contactType->id,
            'contact_status' => true,
            'contact_is_primary' => true,
            'bank_accounts' => [
                // Update existing bank account
                [
                    'id' => $bankAccount->id,
                    'bank_account_type' => $this->bankAccountType->id,
                    'account_holder_name' => 'New Holder',
                    'account_number' => '8888888888',
                    'bank_name' => 'New Bank',
                    'ifsc_code' => 'IFSC0001111',
                    'is_primary' => true,
                    'status' => true,
                ],
                // Add new bank account
                [
                    'bank_account_type' => $this->bankAccountType->id,
                    'account_holder_name' => 'Added Holder',
                    'account_number' => '7777777777',
                    'bank_name' => 'Added Bank',
                    'ifsc_code' => 'IFSC0002222',
                    'is_primary' => false,
                    'status' => true,
                ]
            ]
        ];

        $response = $this->put(route('patrons.update', $patron->id), $data);

        $response->assertRedirect();

        // Assert updated Patron
        $this->assertDatabaseHas('mm_patrons', [
            'id' => $patron->id,
            'legal_name' => 'New Legal Name',
        ]);

        // Assert updated Contact
        $this->assertDatabaseHas('mm_contacts', [
            'id' => $contact->id,
            'name' => 'New Contact Name',
            'mobile' => '8888888888',
        ]);

        // Assert updated Bank Account
        $this->assertDatabaseHas('mm_patron_bank_accounts', [
            'id' => $bankAccount->id,
            'account_holder_name' => 'New Holder',
        ]);

        // Assert added Bank Account
        $this->assertDatabaseHas('mm_patron_bank_accounts', [
            'patron_id' => $patron->id,
            'account_holder_name' => 'Added Holder',
            'account_number' => '7777777777',
        ]);

        // Now remove the second bank account
        $data2 = [
            'patron_type' => ['Customer'],
            'legal_name' => 'New Legal Name',
            'operational_status' => 'active',
            'status' => true,
            'displayed' => true,
            'contact_name' => 'New Contact Name',
            'contact_email' => 'new@test.com',
            'contact_mobile' => '8888888888',
            'bank_accounts' => [
                [
                    'id' => $bankAccount->id,
                    'bank_account_type' => $this->bankAccountType->id,
                    'account_holder_name' => 'New Holder',
                    'account_number' => '8888888888',
                    'bank_name' => 'New Bank',
                    'ifsc_code' => 'IFSC0001111',
                    'is_primary' => true,
                    'status' => true,
                ]
            ]
        ];

        $response2 = $this->put(route('patrons.update', $patron->id), $data2);
        $response2->assertRedirect();

        // The second bank account should be soft deleted or hard deleted
        $this->assertSoftDeleted('mm_patron_bank_accounts', [
            'account_holder_name' => 'Added Holder',
            'account_number' => '7777777777',
        ]);
    }

    public function test_can_delete_patron()
    {
        $patron = Patron::factory()->create([
            'plant_id' => $this->plant->id,
            'entity_id' => $this->entity->id,
        ]);

        $response = $this->delete(route('patrons.destroy', $patron->id));

        $response->assertRedirect();
        $this->assertSoftDeleted('mm_patrons', ['id' => $patron->id]);
    }

    public function test_can_batch_store_patrons()
    {
        $data = [
            'patrons' => [
                [
                    'legal_name' => 'Acme Batch 1',
                    'patron_type' => ['Customer'],
                    'operational_status' => 'active',
                    'status' => true,
                    'displayed' => true,
                ],
                [
                    'legal_name' => 'Acme Batch 2',
                    'patron_type' => ['Vendor'],
                    'operational_status' => 'inactive',
                    'status' => false,
                    'displayed' => false,
                ]
            ]
        ];

        $response = $this->post(route('patrons.batchstore'), $data);

        $response->assertRedirect();
        $this->assertDatabaseHas('mm_patrons', ['legal_name' => 'Acme Batch 1']);
        $this->assertDatabaseHas('mm_patrons', ['legal_name' => 'Acme Batch 2']);
    }

    public function test_dropdown_returns_correct_options()
    {
        Patron::factory()->create([
            'plant_id' => $this->plant->id,
            'legal_name' => 'John Customer',
            'patron_type' => ['Customer'],
        ]);

        Patron::factory()->create([
            'plant_id' => $this->plant->id,
            'legal_name' => 'John Vendor',
            'patron_type' => ['Vendor'],
        ]);

        $response = $this->get(route('patrons.dropdown', ['type' => 'Customer']));
        $response->assertStatus(200);
        $response->assertJsonFragment([
            'label' => 'John Customer'
        ]);
        $response->assertJsonMissing([
            'label' => 'John Vendor'
        ]);
    }
}
