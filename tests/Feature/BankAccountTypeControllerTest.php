<?php

namespace Tests\Feature;

use App\Models\BankAccountType;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BankAccountTypeControllerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->actingAs(User::factory()->create());
    }

    public function test_can_view_index()
    {
        BankAccountType::factory()->count(3)->create();

        $response = $this->get(route('bank-account-types.index'));

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->component('BankAccountTypes/Index')
            ->has('bankAccountTypes', 3)
        );
    }

    public function test_can_store_bank_account_type()
    {
        $data = ['type' => 'Test Bank Account Type'];

        $response = $this->postJson(route('bank-account-types.store'), $data);

        $response->assertStatus(200);
        $this->assertDatabaseHas('mm_bank_account_types', $data);
        $response->assertJsonStructure(['bankAccountType', 'message']);
    }

    public function test_can_update_bank_account_type()
    {
        $bankAccountType = BankAccountType::factory()->create(['type' => 'Old Type']);
        $newValue = 'New Type';

        $response = $this->putJson(route('bank-account-types.update', $bankAccountType->id), ['type' => $newValue]);

        $response->assertStatus(200);
        $this->assertDatabaseHas('mm_bank_account_types', ['id' => $bankAccountType->id, 'type' => $newValue]);
        $response->assertJsonStructure(['bankAccountType', 'message']);
    }

    public function test_can_destroy_bank_account_type()
    {
        $bankAccountType = BankAccountType::factory()->create();

        $response = $this->deleteJson(route('bank-account-types.destroy', $bankAccountType->id));

        $response->assertStatus(200);
        
        if (in_array('Illuminate\Database\Eloquent\SoftDeletes', class_uses(BankAccountType::class))) {
            $this->assertSoftDeleted($bankAccountType);
        } else {
            $this->assertDatabaseMissing('mm_bank_account_types', ['id' => $bankAccountType->id]);
        }
    }
}
