<?php

namespace Tests\Feature;

use App\Models\BankAccountType;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\TestCase;

class BankAccountTypeTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->actingAs(User::factory()->create());
    }

    public function test_can_create_mm_bank_account_types()
    {
        $data = BankAccountType::factory()->make()->getAttributes();
        
        $model = BankAccountType::create($data);
        $this->assertModelMatchesDatabase($model);
    }

    public function test_can_update_mm_bank_account_types()
    {
        $model = BankAccountType::factory()->create();
        $newData = BankAccountType::factory()->make()->getAttributes();

        $model->update($newData);
        $this->assertModelMatchesDatabase($model);
    }

    public function test_can_delete_mm_bank_account_types()
    {
        $model = BankAccountType::factory()->create();

        $model->delete();
        
        if (in_array('Illuminate\Database\Eloquent\SoftDeletes', class_uses(BankAccountType::class))) {
            $this->assertSoftDeleted($model);
        } else {
            $this->assertDatabaseMissing($model->getTable(), ['id' => $model->id]);
        }
    }
}
