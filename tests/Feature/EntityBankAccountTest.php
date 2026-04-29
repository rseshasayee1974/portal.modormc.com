<?php

namespace Tests\Feature;

use App\Models\EntityBankAccount;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\TestCase;

class EntityBankAccountTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->actingAs(User::factory()->create());
    }

    public function test_can_create_mm_entity_bank_accounts()
    {
        $data = EntityBankAccount::factory()->make()->getAttributes();
        
        $model = EntityBankAccount::create($data);
        $this->assertModelMatchesDatabase($model);
    }

    public function test_can_update_mm_entity_bank_accounts()
    {
        $model = EntityBankAccount::factory()->create();
        $newData = EntityBankAccount::factory()->make()->getAttributes();

        $model->update($newData);
        $this->assertModelMatchesDatabase($model);
    }

    public function test_can_delete_mm_entity_bank_accounts()
    {
        $model = EntityBankAccount::factory()->create();

        $model->delete();
        
        if (in_array('Illuminate\Database\Eloquent\SoftDeletes', class_uses(EntityBankAccount::class))) {
            $this->assertSoftDeleted($model);
        } else {
            $this->assertDatabaseMissing($model->getTable(), ['id' => $model->id]);
        }
    }
}
