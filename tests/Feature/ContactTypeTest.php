<?php

namespace Tests\Feature;

use App\Models\ContactType;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\TestCase;

class ContactTypeTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->actingAs(User::factory()->create());
    }

    public function test_can_create_mm_contact_types()
    {
        $data = ContactType::factory()->make()->getAttributes();
        
        $model = ContactType::create($data);
        $this->assertModelMatchesDatabase($model);
    }

    public function test_can_update_mm_contact_types()
    {
        $model = ContactType::factory()->create();
        $newData = ContactType::factory()->make()->getAttributes();

        $model->update($newData);
        $this->assertModelMatchesDatabase($model);
    }

    public function test_can_delete_mm_contact_types()
    {
        $model = ContactType::factory()->create();

        $model->delete();
        
        if (in_array('Illuminate\Database\Eloquent\SoftDeletes', class_uses(ContactType::class))) {
            $this->assertSoftDeleted($model);
        } else {
            $this->assertDatabaseMissing($model->getTable(), ['id' => $model->id]);
        }
    }
}
