<?php

namespace Tests\Feature;

use App\Http\Controllers\AccountDiscountController;
use App\Http\Middleware\TitleCaseInputs;
use App\Models\AccountDiscount;
use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class AccountDiscountTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->assertSame('sqlite', DB::connection()->getDriverName());
        $this->assertSame(':memory:', DB::connection()->getDatabaseName());
        (require database_path('migrations/2026_09_11_170000_create_account_discount_table.php'))->up();
        foreach (['mm_journal_entries', 'mm_ledgers', 'mm_patrons'] as $name) {
            Schema::create($name, function (Blueprint $table) use ($name) {
                $table->id();
                $table->integer('plant_id');
                $table->softDeletes();
                if ($name === 'mm_journal_entries') {
                    $table->string('voucher_number')->default('JV-1');
                    $table->string('voucher_type')->default('JOURNAL');
                } else {
                    $table->string($name === 'mm_ledgers' ? 'title' : 'legal_name')->default('Test');
                }
            });
            DB::table($name)->insert([['id' => 1, 'plant_id' => 6], ['id' => 2, 'plant_id' => 7]]);
        }
        session(['active_plant_id' => 6]);
        $this->signIn(11);
    }

    private function signIn(int $id): void
    {
        $user = $this->getMockBuilder(User::class)->onlyMethods(['isSystemAdmin'])->getMock();
        $user->setRawAttributes(['id' => $id]);
        $user->method('isSystemAdmin')->willReturn(true);
        $this->actingAs($user);
    }

    private function payload(array $overrides = []): array
    {
        return array_merge([
            'primary_type' => 'Sales', 'value_type' => 'amount', 'value' => '125.25',
            'journal_id' => 1, 'account_id' => 1, 'partner_id' => 1, 'move_id' => null,
            'date' => '2026-09-11', 'note' => 'Approved discount', 'status' => 1,
        ], $overrides);
    }

    private function createDiscount(array $overrides = []): AccountDiscount
    {
        $request = Request::create('/finance/discounts', 'POST', $this->payload($overrides));
        (new TitleCaseInputs)->handle($request, fn ($request) => (new AccountDiscountController)->store($request));
        return AccountDiscount::latest('id')->firstOrFail();
    }

    public function test_create_stamps_the_active_plant_and_audit_fields_and_preserves_enum_case(): void
    {
        $discount = $this->createDiscount(['plant_id' => 7, 'created_by' => 99, 'modified_by' => 99, 'amount' => '1.00']);
        $this->assertSame('amount', $discount->value_type);
        $this->assertSame('125.25', $discount->amount);
        $this->assertSame(6, $discount->plant_id);
        $this->assertSame(11, $discount->created_by);
        $this->assertSame(11, $discount->modified_by);
        $this->assertNotNull($discount->modified_at);
        $this->assertFalse(Schema::hasColumn('mm_account_discount', 'updated_at'));
    }

    public function test_percentage_and_its_amount_are_stored_separately(): void
    {
        $discount = $this->createDiscount(['primary_type' => 'Purchase', 'value_type' => 'percent', 'value' => '2.50', 'amount' => '37.50']);
        $this->assertSame('2.50', $discount->value);
        $this->assertSame('37.50', $discount->amount);
        $this->assertSame('Purchase', $discount->primary_type);
    }

    public function test_update_and_delete_do_not_modify_linked_journal_entries(): void
    {
        $discount = $this->createDiscount();
        $createdAt = $discount->created_at->toDateTimeString();
        $this->signIn(12);
        $request = Request::create('/finance/discounts/' . $discount->id, 'PUT', $this->payload(['value' => '80.10', 'status' => 0, 'created_by' => 99]));
        (new AccountDiscountController)->update($request, $discount);
        $discount->refresh();
        $this->assertSame('80.10', $discount->amount);
        $this->assertSame(0, $discount->status);
        $this->assertSame(11, $discount->created_by);
        $this->assertSame(12, $discount->modified_by);
        $this->assertSame($createdAt, $discount->created_at->toDateTimeString());
        (new AccountDiscountController)->destroy($discount);
        $this->assertDatabaseMissing('mm_account_discount', ['id' => $discount->id]);
        $this->assertSame(2, DB::table('mm_journal_entries')->count());
    }

    public function test_index_and_show_return_discount_details_and_plant_scoped_options(): void
    {
        $discount = $this->createDiscount();
        $request = Request::create('/finance/discounts', 'GET');
        $request->headers->set('X-Inertia', 'true');
        $response = (new AccountDiscountController)->index()->toResponse($request)->getData(true);
        $this->assertSame('Discounts/Index', $response['component']);
        $this->assertCount(1, $response['props']['discounts']);
        foreach (['partners', 'accounts', 'journals'] as $key) {
            $this->assertCount(1, $response['props'][$key]);
            $this->assertSame(1, $response['props'][$key][0]['id']);
        }
        $detail = (new AccountDiscountController)->show($discount)->getData(true);
        $this->assertSame('125.25', $detail['amount']);
    }

    public function test_foreign_plant_references_and_invalid_money_are_rejected(): void
    {
        foreach ([
            ['journal_id' => 2], ['account_id' => 2], ['partner_id' => 2],
            ['value' => '-1'], ['value' => '1.234'], ['value' => '1000000000000000'],
            ['value_type' => 'percent', 'value' => '101', 'amount' => '10'],
            ['value_type' => 'percent', 'value' => '10'],
            ['status' => 4], ['primary_type' => 'Other'],
        ] as $overrides) {
            try {
                $this->createDiscount($overrides);
                $this->fail('Invalid discount accepted: ' . json_encode($overrides));
            } catch (ValidationException $exception) {
                $this->assertNotEmpty($exception->errors());
            }
        }
        $this->assertSame(0, AccountDiscount::count());
    }

    public function test_other_plants_cannot_view_update_or_delete_a_discount(): void
    {
        $discount = $this->createDiscount();
        session(['active_plant_id' => 7]);
        $this->assertNull(AccountDiscount::find($discount->id));
        foreach (['show', 'update', 'destroy'] as $method) {
            try {
                $controller = new AccountDiscountController;
                $method === 'update' ? $controller->update(Request::create('/test', 'PUT', $this->payload()), $discount) : $controller->$method($discount);
                $this->fail('Expected 404');
            } catch (HttpException $exception) {
                $this->assertSame(404, $exception->getStatusCode());
            }
        }
    }

    public function test_a_user_without_an_active_plant_cannot_create_a_discount(): void
    {
        session()->forget('active_plant_id');
        try {
            $this->createDiscount();
            $this->fail('Expected a plant-selection error');
        } catch (HttpException $exception) {
            $this->assertSame(403, $exception->getStatusCode());
        }
    }

    public function test_missing_permissions_block_read_and_write_operations(): void
    {
        $discount = $this->createDiscount();
        $user = $this->getMockBuilder(User::class)->onlyMethods(['isSystemAdmin'])->getMock();
        $user->setRawAttributes(['id' => 30]);
        $user->method('isSystemAdmin')->willReturn(false);
        $this->actingAs($user);
        \Illuminate\Support\Facades\Gate::swap(new \Illuminate\Auth\Access\Gate(app(), fn () => $user));

        $controller = new AccountDiscountController;
        $actions = [
            fn () => $controller->index(),
            fn () => $controller->show($discount),
            fn () => $controller->store(Request::create('/test', 'POST', $this->payload())),
            fn () => $controller->update(Request::create('/test', 'PUT', $this->payload()), $discount),
            fn () => $controller->destroy($discount),
        ];
        foreach ($actions as $action) {
            try {
                $action();
                $this->fail('Expected a permission error');
            } catch (HttpException $exception) {
                $this->assertSame(403, $exception->getStatusCode());
            }
        }
        $this->assertSame(1, AccountDiscount::count());
    }
}
