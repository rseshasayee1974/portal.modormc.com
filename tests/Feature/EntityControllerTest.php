<?php

namespace Tests\Feature;

use App\Models\Entity;
use App\Models\EntityType;
use App\Models\AddressType;
use App\Models\ContactType;
use App\Models\BankAccountType;
use App\Models\Country;
use App\Models\StateCode;
use App\Models\EntityAddress;
use App\Models\EntityContact;
use App\Models\EntityBankAccount;
use App\Models\EntityTax;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EntityControllerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->actingAs(User::factory()->create());
    }

    public function test_can_view_index()
    {
        EntityType::factory()->count(2)->create();
        Entity::factory()->count(3)->create();

        $response = $this->get(route('entities.index'));

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->component('Entities/Index')
            ->has('entities', 3)
            ->has('entityTypes')
            ->has('addressTypes')
        );
    }

    public function test_can_store_entity_with_relations()
    {
        $entityType = EntityType::factory()->create();
        $addressType = AddressType::factory()->create();
        $contactType = ContactType::factory()->create();
        $bankAccountType = BankAccountType::factory()->create();
        $country = Country::factory()->create();

        $data = [
            'entity_type' => $entityType->id,
            'legal_name' => 'Acme Corp',
            'is_active' => true,
            'is_suspended' => false,
            'addresses' => [
                [
                    'address_type' => $addressType->id,
                    'line_1' => '123 Main St',
                    'city' => 'Anytown',
                    'country_id' => $country->id,
                    'is_primary' => 1
                ]
            ],
            'contacts' => [
                [
                    'contact_type' => $contactType->id,
                    'contact_person' => 'John Doe',
                    'email' => 'john@acme.com',
                    'is_primary' => 1
                ]
            ],
            'bank_accounts' => [
                [
                    'account_type' => $bankAccountType->id,
                    'account_number' => 'ACC123456',
                    'bank_name' => 'Global Bank',
                    'is_primary' => 1
                ]
            ],
            'taxes' => [
                [
                    'tax_type' => 'VAT',
                    'tax_number' => 'V12345678',
                    'is_primary' => 1
                ]
            ]
        ];

        $response = $this->postJson(route('entities.store'), $data);

        $response->assertStatus(200);
        $this->assertDatabaseHas('mm_entities', [
            'legal_name' => 'Acme Corp',
            'entity_type' => $entityType->id
        ]);
        
        $entity = Entity::where('legal_name', 'Acme Corp')->first();
        
        $this->assertDatabaseHas('mm_entity_addresses', [
            'entity_id' => $entity->id,
            'line_1' => '123 Main St'
        ]);

        $this->assertDatabaseHas('mm_entity_contacts', [
            'entity_id' => $entity->id,
            'contact_person' => 'John Doe'
        ]);

        $this->assertDatabaseHas('mm_entity_bank_accounts', [
            'entity_id' => $entity->id,
            'account_number' => 'ACC123456'
        ]);

        $this->assertDatabaseHas('mm_entity_taxes', [
            'entity_id' => $entity->id,
            'tax_number' => 'V12345678'
        ]);
        
        $response->assertJsonStructure(['entity', 'message']);
    }

    public function test_can_update_entity_and_sync_relations()
    {
        $entityType = EntityType::factory()->create();
        $addressType1 = AddressType::factory()->create();
        $addressType2 = AddressType::factory()->create();
        $addressType3 = AddressType::factory()->create();
        
        $entity = Entity::factory()->create([
            'entity_type' => $entityType->id,
            'legal_name' => 'Old Name'
        ]);
        
        $address1 = EntityAddress::factory()->create([
            'entity_id' => $entity->id,
            'address_type' => $addressType1->id,
            'line_1' => 'Old Address 1',
            'city' => 'Old City 1'
        ]);

        $address2 = EntityAddress::factory()->create([
            'entity_id' => $entity->id,
            'address_type' => $addressType2->id,
            'line_1' => 'Old Address 2',
            'city' => 'Old City 2'
        ]);

        $newData = [
            'entity_type' => $entityType->id,
            'legal_name' => 'New Name',
            'is_active' => true,
            'is_suspended' => false,
            'addresses' => [
                // Update Address 1
                [
                    'id' => $address1->id,
                    'address_type' => $addressType1->id,
                    'line_1' => 'Updated Address 1',
                    'city' => 'Updated City 1',
                    'is_primary' => 1
                ],
                // Add new Address 3
                [
                    'address_type' => $addressType3->id, // Use type 3 to avoid soft-delete unique constraint conflict with type 2
                    'line_1' => 'New Address 3',
                    'city' => 'New City 3',
                    'is_primary' => 0
                ]
                // Address 2 is omitted, should be deleted
            ],
            'contacts' => [],
            'bank_accounts' => [],
            'taxes' => []
        ];

        $response = $this->putJson(route('entities.update', $entity->id), $newData);

        $response->assertStatus(200);
        
        // Assert entity updated
        $this->assertDatabaseHas('mm_entities', [
            'id' => $entity->id,
            'legal_name' => 'New Name'
        ]);
        
        // Assert address 1 updated
        $this->assertDatabaseHas('mm_entity_addresses', [
            'id' => $address1->id,
            'line_1' => 'Updated Address 1'
        ]);
        
        // Assert address 3 added
        $this->assertDatabaseHas('mm_entity_addresses', [
            'entity_id' => $entity->id,
            'line_1' => 'New Address 3'
        ]);

        // Assert address 2 deleted
        if (in_array('Illuminate\Database\Eloquent\SoftDeletes', class_uses(EntityAddress::class))) {
            $this->assertSoftDeleted($address2);
        } else {
            $this->assertDatabaseMissing('mm_entity_addresses', ['id' => $address2->id]);
        }
    }

    public function test_can_destroy_entity_and_cascade()
    {
        $entityType = EntityType::factory()->create();
        $entity = Entity::factory()->create([
            'entity_type' => $entityType->id
        ]);
        
        $address = EntityAddress::factory()->create([
            'entity_id' => $entity->id
        ]);

        $response = $this->deleteJson(route('entities.destroy', $entity->id));

        $response->assertStatus(200);
        
        if (in_array('Illuminate\Database\Eloquent\SoftDeletes', class_uses(Entity::class))) {
            $this->assertSoftDeleted($entity);
            $this->assertSoftDeleted($address);
        } else {
            $this->assertDatabaseMissing('mm_entities', ['id' => $entity->id]);
            $this->assertDatabaseMissing('mm_entity_addresses', ['id' => $address->id]);
        }
    }
}
