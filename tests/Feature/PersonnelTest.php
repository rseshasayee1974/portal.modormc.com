<?php

namespace Tests\Feature;

use App\Models\Personnel;
use App\Models\Entity;
use App\Models\Plant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PersonnelTest extends TestCase
{
    use RefreshDatabase;

    protected $user;
    protected $entity;
    protected $plant;

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->user = User::factory()->create();
        $this->entity = Entity::factory()->create();
        $this->plant = Plant::factory()->create(['entity_id' => $this->entity->id]);
        
        session(['active_entity_id' => $this->entity->id]);
        session(['active_plant_id' => $this->plant->id]);
        
        $this->actingAs($this->user);
    }

    public function test_can_view_personnel_index()
    {
        $response = $this->get(route('personnel.index'));

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page->component('Personnel/Index'));
    }

    public function test_can_create_personnel()
    {
        $data = [
            'first_name' => 'John',
            'last_name' => 'Doe',
            'employment_type' => 'permanent',
            'gender' => 'male',
            'status' => 'active',
            'contacts' => [
                ['contact_type' => 'Phone', 'contact_value' => '1234567890', 'is_primary' => true]
            ]
        ];

        $response = $this->post(route('personnel.store'), $data);

        $response->assertRedirect();
        $this->assertDatabaseHas('mm_personnels', ['first_name' => 'John']);
        $this->assertDatabaseHas('mm_personnel_contacts', ['contact_value' => '1234567890']);
    }

    public function test_can_update_personnel()
    {
        $personnel = Personnel::factory()->create([
            'entity_id' => $this->entity->id,
            'plant_id' => $this->plant->id,
        ]);

        $data = [
            'first_name' => 'Jane',
            'last_name' => 'Smith',
            'employment_type' => 'permanent',
            'status' => 'inactive',
        ];

        $response = $this->put(route('personnel.update', $personnel->id), $data);

        $response->assertRedirect();
        $this->assertDatabaseHas('mm_personnels', [
            'id' => $personnel->id,
            'first_name' => 'Jane',
            'status' => 'inactive'
        ]);
    }

    public function test_can_delete_personnel()
    {
        $personnel = Personnel::factory()->create([
            'entity_id' => $this->entity->id,
            'plant_id' => $this->plant->id,
        ]);

        $response = $this->delete(route('personnel.destroy', $personnel->id));

        $response->assertRedirect();
        $this->assertSoftDeleted('mm_personnels', ['id' => $personnel->id]);
    }

    public function test_create_personnel_fails_with_incomplete_contacts()
    {
        $data = [
            'first_name' => 'John',
            'last_name' => 'Doe',
            'employment_type' => 'permanent',
            'gender' => 'male',
            'status' => 'active',
            'contacts' => [
                ['contact_type' => '', 'contact_value' => '1234567890', 'is_primary' => true], // empty type
                ['contact_type' => 'Email', 'contact_value' => '', 'is_primary' => false]     // empty value
            ]
        ];

        $response = $this->post(route('personnel.store'), $data);

        $response->assertSessionHasErrors([
            'contacts.0.contact_type',
            'contacts.1.contact_value'
        ]);
        $this->assertDatabaseMissing('mm_personnels', ['first_name' => 'John']);
    }

    public function test_update_personnel_updates_adds_and_removes_contacts()
    {
        $personnel = Personnel::factory()->create([
            'entity_id' => $this->entity->id,
            'plant_id' => $this->plant->id,
        ]);

        // First create a contact
        $contactRecord = \App\Models\Contact::create([
            'plant_id' => $personnel->plant_id,
            'name' => 'Test Contact',
            'mobile' => '1111111111',
            'contact_type_id' => 1,
            'status' => 1,
        ]);
        $contactPivot = $personnel->contacts()->create([
            'contact_id' => (string) $contactRecord->id,
            'contact_type' => 'Phone',
            'contact_value' => '1111111111',
            'is_primary' => true,
        ]);

        // Submit update:
        // 1. Update existing contact from Phone 1111111111 to Mobile 2222222222
        // 2. Add a new contact Email test@test.com
        $data = [
            'first_name' => 'Jane',
            'employment_type' => 'permanent',
            'status' => 'active',
            'contacts' => [
                [
                    'contact_id' => (string) $contactRecord->id,
                    'contact_type' => 'Mobile',
                    'contact_value' => '2222222222',
                    'is_primary' => true,
                ],
                [
                    'contact_type' => 'Email',
                    'contact_value' => 'test@test.com',
                    'is_primary' => false,
                ]
            ]
        ];

        $response = $this->put(route('personnel.update', $personnel->id), $data);
        $response->assertRedirect();

        // Assert existing updated
        $this->assertDatabaseHas('mm_personnel_contacts', [
            'employee_id' => $personnel->id,
            'contact_id' => (string) $contactRecord->id,
            'contact_type' => 'Mobile',
            'contact_value' => '2222222222',
        ]);

        // Assert new created
        $this->assertDatabaseHas('mm_personnel_contacts', [
            'employee_id' => $personnel->id,
            'contact_type' => 'Email',
            'contact_value' => 'test@test.com',
        ]);

        // Submit update again: delete the new Email contact by omitting it
        $data2 = [
            'first_name' => 'Jane',
            'employment_type' => 'permanent',
            'status' => 'active',
            'contacts' => [
                [
                    'contact_id' => (string) $contactRecord->id,
                    'contact_type' => 'Mobile',
                    'contact_value' => '2222222222',
                    'is_primary' => true,
                ]
            ]
        ];

        $response2 = $this->put(route('personnel.update', $personnel->id), $data2);
        $response2->assertRedirect();

        // Email contact should be deleted
        $this->assertDatabaseMissing('mm_personnel_contacts', [
            'employee_id' => $personnel->id,
            'contact_type' => 'Email',
            'contact_value' => 'test@test.com',
        ]);
    }

    public function test_update_personnel_with_phone_number_containing_special_characters()
    {
        $personnel = Personnel::factory()->create([
            'entity_id' => $this->entity->id,
            'plant_id' => $this->plant->id,
        ]);

        $contactRecord = \App\Models\Contact::create([
            'plant_id' => $personnel->plant_id,
            'name' => 'Special Contact',
            'mobile' => '1234567890',
            'contact_type_id' => 1,
            'status' => 1,
        ]);

        $personnel->contacts()->create([
            'contact_id' => (string) $contactRecord->id,
            'contact_type' => 'Phone',
            'contact_value' => '1234567890',
            'is_primary' => true,
        ]);

        $data = [
            'first_name' => 'Jane',
            'employment_type' => 'permanent',
            'status' => 'active',
            'contacts' => [
                [
                    'contact_id' => (string) $contactRecord->id,
                    'contact_type' => 'Phone',
                    'contact_value' => '+91 99887 76655', // Special characters + and space
                    'is_primary' => true,
                ]
            ]
        ];

        $response = $this->put(route('personnel.update', $personnel->id), $data);
        $response->assertRedirect();

        $this->assertDatabaseHas('mm_personnel_contacts', [
            'employee_id' => $personnel->id,
            'contact_id' => (string) $contactRecord->id,
            'contact_value' => '+91 99887 76655',
        ]);

        $this->assertDatabaseHas('mm_contacts', [
            'id' => $contactRecord->id,
            'mobile' => '+91 99887 76655',
        ]);
    }

    public function test_update_personnel_with_uuid_contact_id_handles_safely()
    {
        $personnel = Personnel::factory()->create([
            'entity_id' => $this->entity->id,
            'plant_id' => $this->plant->id,
        ]);

        $uuid = 'dcf76f00-77a0-4595-af69-f464bd812e45';

        // Create pivot contact directly with UUID contact_id (like seeder does)
        $personnel->contacts()->create([
            'contact_id' => $uuid,
            'contact_type' => 'Phone',
            'contact_value' => '+91 9988776655',
            'is_primary' => true,
        ]);

        $data = [
            'first_name' => 'Jane',
            'employment_type' => 'permanent',
            'status' => 'active',
            'contacts' => [
                [
                    'contact_id' => $uuid,
                    'contact_type' => 'Phone',
                    'contact_value' => '+91 1122334455',
                    'is_primary' => true,
                ]
            ]
        ];

        // Should not throw SQL truncation error or datetime format issue
        $response = $this->put(route('personnel.update', $personnel->id), $data);
        $response->assertRedirect();

        $this->assertDatabaseHas('mm_personnel_contacts', [
            'employee_id' => $personnel->id,
            'contact_id' => $uuid,
            'contact_value' => '+91 1122334455',
        ]);
    }
}

