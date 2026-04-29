<?php

namespace Tests\Feature;

use App\Models\ContactType;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ContactTypeControllerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->actingAs(User::factory()->create());
    }

    public function test_can_view_index()
    {
        ContactType::factory()->count(3)->create();

        $response = $this->get(route('contact-types.index'));

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->component('ContactTypes/Index')
            ->has('contactTypes', 3)
        );
    }

    public function test_can_store_contact_type()
    {
        $data = ['type' => 'Test Contact Type'];

        $response = $this->postJson(route('contact-types.store'), $data);

        $response->assertStatus(200);
        $this->assertDatabaseHas('mm_contact_types', $data);
        $response->assertJsonStructure(['contactType', 'message']);
    }

    public function test_can_update_contact_type()
    {
        $contactType = ContactType::factory()->create(['type' => 'Old Type']);
        $newValue = 'New Type';

        $response = $this->putJson(route('contact-types.update', $contactType->id), ['type' => $newValue]);

        $response->assertStatus(200);
        $this->assertDatabaseHas('mm_contact_types', ['id' => $contactType->id, 'type' => $newValue]);
        $response->assertJsonStructure(['contactType', 'message']);
    }

    public function test_can_destroy_contact_type()
    {
        $contactType = ContactType::factory()->create();

        $response = $this->deleteJson(route('contact-types.destroy', $contactType->id));

        $response->assertStatus(200);
        
        if (in_array('Illuminate\Database\Eloquent\SoftDeletes', class_uses(ContactType::class))) {
            $this->assertSoftDeleted($contactType);
        } else {
            $this->assertDatabaseMissing('mm_contact_types', ['id' => $contactType->id]);
        }
    }
}
