<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\ExpenseType;
use App\Models\Expense;
use App\Models\PettyCash;
use App\Models\Plant;
use App\Models\Ledger;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PettyCashTest extends TestCase
{
    use RefreshDatabase;

    private $user;
    private $plant;
    private $ledger;
    private $expenseType;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user  = User::factory()->create();
        $this->actingAs($this->user);

        $this->plant     = Plant::factory()->create();
        $this->ledger    = Ledger::factory()->create();
        $this->expenseType = ExpenseType::factory()->create(['plant_id' => $this->plant->id]);

        session(['active_plant_id' => $this->plant->id]);
    }

    public function test_can_list_expense_types()
    {
        ExpenseType::factory(3)->create(['plant_id' => $this->plant->id]);

        $response = $this->get(route('expense-types.index'));

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->component('Expenses/Types/Index')
            ->has('expenseTypes', 4) // 3 + the one in setUp
        );
    }

    public function test_can_create_expense_type()
    {
        $response = $this->post(route('expense-types.store'), [
            'name'      => 'Fuel',
            'ledger_id' => $this->ledger->id,
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('mm_expense_types', ['name' => 'Fuel']);
    }

    public function test_can_create_expense()
    {
        $response = $this->post(route('expenses.store'), [
            'expense_type_id' => $this->expenseType->id,
            'paid_through'    => $this->ledger->id,
            'amount'          => 1500.00,
            'date'            => now()->format('Y-m-d'),
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('mm_expenses', ['amount' => 1500.00]);
    }

    public function test_expense_cannot_be_deleted()
    {
        $expense = Expense::factory()->create([
            'plant_id'        => $this->plant->id,
            'expense_type_id' => $this->expenseType->id,
            'paid_through'    => $this->ledger->id,
        ]);

        // Attempt delete should be blocked
        $response = $this->delete(route('expenses.destroy', $expense->id));
        $response->assertForbidden();
    }

    public function test_can_create_petty_cash_with_items()
    {
        $expense = Expense::factory()->create([
            'plant_id'        => $this->plant->id,
            'expense_type_id' => $this->expenseType->id,
            'paid_through'    => $this->ledger->id,
            'amount'          => 500,
        ]);

        $response = $this->post(route('petty-cash.store'), [
            'date'            => now()->format('Y-m-d H:i:s'),
            'opening_balance' => 10000,
            'items' => [
                [
                    'expense_id'  => $expense->id,
                    'amount'      => 500,
                    'debit'       => 500,
                    'credit'      => 0,
                    'date'        => now()->format('Y-m-d H:i:s'),
                    'description' => 'Test transaction',
                ]
            ]
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('mm_petty_cash', ['opening_balance' => 10000]);
        $this->assertDatabaseHas('mm_petty_cash_items', ['amount' => 500]);
    }

    public function test_closing_balance_is_auto_calculated()
    {
        $pc = PettyCash::factory()->create([
            'plant_id'        => $this->plant->id,
            'opening_balance' => 10000,
        ]);

        $expense = Expense::factory()->create([
            'plant_id'        => $this->plant->id,
            'expense_type_id' => $this->expenseType->id,
            'paid_through'    => $this->ledger->id,
            'amount'          => 2000,
        ]);

        $pc->items()->create([
            'plant_id'   => $this->plant->id,
            'expense_id' => $expense->id,
            'amount'     => 2000,
            'debit'      => 2000,
            'credit'     => 0,
            'date'       => now(),
        ]);

        $pc->refresh();

        $this->assertEquals(8000, (float) $pc->closing_balance); // 10000 - 2000
    }
}
