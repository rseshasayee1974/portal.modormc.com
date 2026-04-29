<?php

namespace Tests\Feature;

use App\Models\Accounts;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AccountsTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create();
        $this->actingAs($this->user);

        // Set a fake entity_id in session (required by controller scoping)
        session(['entity_id' => 1]);
    }

    // ──────────────────────────────────────────────
    // INDEX
    // ──────────────────────────────────────────────

    public function test_index_renders_inertia_page(): void
    {
        Accounts::factory()->count(3)->create(['entity_id' => 1]);

        $response = $this->get(route('accounts.index'));

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) =>
            $page->component('Accounts/Index')
                 ->has('accounts', 3)
        );
    }

    public function test_index_only_returns_non_deleted_records(): void
    {
        Accounts::factory()->create(['entity_id' => 1]);
        Accounts::factory()->deleted()->create(['entity_id' => 1]);

        $response = $this->get(route('accounts.index'));

        $response->assertInertia(fn ($page) =>
            $page->component('Accounts/Index')
                 ->has('accounts', 1)
        );
    }

    // ──────────────────────────────────────────────
    // STORE
    // ──────────────────────────────────────────────

    public function test_can_create_account(): void
    {
        $response = $this->postJson(route('accounts.store'), [
            'title' => 'ASSET',
        ]);

        $response->assertStatus(201)
                 ->assertJsonPath('message', 'Account created successfully!')
                 ->assertJsonPath('account.title', 'ASSET');

        $this->assertDatabaseHas('accounts', [
            'title'     => 'ASSET',
            'entity_id' => 1,
        ]);
    }

    public function test_store_requires_title(): void
    {
        $response = $this->postJson(route('accounts.store'), []);

        $response->assertStatus(422)
                 ->assertJsonValidationErrors(['title']);
    }

    // ──────────────────────────────────────────────
    // UPDATE
    // ──────────────────────────────────────────────

    public function test_can_update_account(): void
    {
        $account = Accounts::factory()->create(['entity_id' => 1]);

        $response = $this->putJson(route('accounts.update', $account->id), [
            'title' => 'REVENUE',
        ]);

        $response->assertStatus(200)
                 ->assertJsonPath('message', 'Account updated successfully!')
                 ->assertJsonPath('account.title', 'REVENUE');

        $this->assertDatabaseHas('accounts', [
            'id'    => $account->id,
            'title' => 'REVENUE',
        ]);
    }

    public function test_update_requires_title(): void
    {
        $account = Accounts::factory()->create(['entity_id' => 1]);

        $response = $this->putJson(route('accounts.update', $account->id), [
            'title' => '',
        ]);

        $response->assertStatus(422)
                 ->assertJsonValidationErrors(['title']);
    }

    public function test_cannot_update_deleted_account(): void
    {
                $account = Accounts::factory()->deleted()->create(['entity_id' => 1]);


        $response = $this->putJson(route('accounts.update', $account->id), [
            'title' => 'EXPENSE',
        ]);

        $response->assertStatus(404);
    }

    // ──────────────────────────────────────────────
    // DESTROY
    // ──────────────────────────────────────────────

    public function test_can_soft_delete_account(): void
    {
        $account = Accounts::factory()->create(['entity_id' => 1]);

        $response = $this->deleteJson(route('accounts.destroy', $account->id));

        $response->assertStatus(200)
                 ->assertJsonPath('message', 'Account deleted successfully!');

        $this->assertSoftDeleted('accounts', [
            'id'      => $account->id,
        ]);
    }

    public function test_cannot_delete_already_deleted_account(): void
    {
                $account = Accounts::factory()->deleted()->create(['entity_id' => 1]);


        $response = $this->deleteJson(route('accounts.destroy', $account->id));

        $response->assertStatus(404);
    }

    // ──────────────────────────────────────────────
    // MODEL UNIT TESTS
    // ──────────────────────────────────────────────

    public function test_accounts_factory_creates_valid_record(): void
    {
        $account = Accounts::factory()->create(['entity_id' => 1]);

        $this->assertDatabaseHas('accounts', ['id' => $account->id]);
        $this->assertNull($account->deleted_at);
        $this->assertEquals(1, $account->status);
    }

    public function test_account_name_type_returns_array(): void
    {
        $types = Accounts::accountNameType();

        $this->assertIsArray($types);
        $this->assertContains('ASSET', $types);
        $this->assertContains('LIABILITY', $types);
        $this->assertCount(6, $types);
    }
}
