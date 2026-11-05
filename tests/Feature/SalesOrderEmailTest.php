<?php
/*
Author: ragul-onemodo
Created: 2026-11-05 15:16:48 Asia/Calcutta (UTC+05:30)
*/

namespace Tests\Feature;

use App\Http\Controllers\NotificationEmailController;
use App\Models\SalesOrder;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\TestCase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;

class SalesOrderEmailTest extends TestCase
{
    private const DELIVERIES = 'mm_sales_order_email_deliveries';

    protected function setUp(): void
    {
        parent::setUp();
        config([
            'database.default' => 'sales_order_email_test',
            'database.connections.sales_order_email_test' => ['driver' => 'sqlite', 'database' => ':memory:', 'foreign_key_constraints' => true],
            'mail.default' => 'zeptomail_api',
            'mail.from.address' => 'different-sender@example.test',
            'mail.mailers.zeptomail_api.token' => 'fake-token',
        ]);

        Schema::create('mm_plants', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->softDeletes();
        });
        Schema::create('mm_sales_orders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('plant_id');
            $table->string('prefix');
            $table->string('order_no');
            $table->decimal('total_qty', 10, 3);
            $table->timestamps();
            $table->softDeletes();
        });
        Schema::create('mm_notification_emails', function (Blueprint $table) {
            $table->id();
            $table->foreignId('plant_id');
            $table->string('type');
            $table->string('role_name');
            $table->string('email');
            $table->boolean('status');
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
        $migration = require database_path('migrations/2026_11_05_151500_create_sales_order_email_deliveries_table.php');
        $migration->up();

        DB::table('mm_plants')->insert(['id' => 1, 'name' => 'Plant & Yard']);
        Http::preventStrayRequests();
    }

    public function test_creation_sends_once_per_active_admin_address_for_the_order_plant(): void
    {
        $this->recipient('admin@example.test');
        $this->recipient('ADMIN@EXAMPLE.TEST', ['role_name' => 'SUPER_ADMIN']);
        $this->recipient('second@example.test');
        $this->recipient('wrong-plant@example.test', ['plant_id' => 2]);
        $this->recipient('wrong-role@example.test', ['role_name' => 'Sales Manager']);
        $this->recipient('wrong-type@example.test', ['type' => 'Quotation']);
        $this->recipient('disabled@example.test', ['status' => 0]);
        $this->recipient('deleted@example.test', ['deleted_at' => now()]);
        $this->recipient('invalid-address');
        Http::fake(['https://api.zeptomail.com/*' => Http::response([], 200)]);

        $order = DB::transaction(fn () => $this->createOrder());
        $order->update(['total_qty' => 20]);
        $this->assertDatabaseCount(self::DELIVERIES, 2);
        Http::assertNothingSent();

        $this->artisan('sales-orders:send-emails')->assertSuccessful();
        $this->artisan('sales-orders:send-emails')->assertSuccessful();

        Http::assertSentCount(2);
        foreach (['admin@example.test', 'second@example.test'] as $address) {
            Http::assertSent(fn (Request $request) => $request['from']['address'] === 'noreply@modormc.com'
                && array_column(array_column($request['to'], 'email_address'), 'address') === [$address]
                && str_contains($request['subject'], 'SO/26-27/21')
                && str_contains($request['htmlbody'], 'Plant &amp; Yard'));
            $this->assertDatabaseHas(self::DELIVERIES, ['recipient' => $address, 'status' => 'sent', 'attempts' => 1]);
        }
    }

    public function test_order_rollback_removes_its_deliveries_and_disabled_recipients_are_skipped(): void
    {
        $this->recipient('admin@example.test');
        Http::fake();

        DB::beginTransaction();
        $this->createOrder();
        $this->assertDatabaseCount(self::DELIVERIES, 1);
        DB::rollBack();
        $this->assertDatabaseCount(self::DELIVERIES, 0);

        $this->createOrder();
        DB::table('mm_notification_emails')->update(['status' => 0]);
        $this->artisan('sales-orders:send-emails')->assertSuccessful();
        $this->assertDatabaseHas(self::DELIVERIES, ['status' => 'cancelled', 'attempts' => 0]);
        Http::assertNothingSent();
    }

    public function test_only_failed_recipients_retry_after_the_delay(): void
    {
        $this->recipient('retry@example.test');
        $this->recipient('sent@example.test');
        $fail = true;
        Http::fake(function (Request $request) use (&$fail) {
            $retry = $request['to'][0]['email_address']['address'] === 'retry@example.test';
            return Http::response([], $retry && $fail ? 503 : 200);
        });

        $this->createOrder();
        $this->artisan('sales-orders:send-emails')->assertSuccessful();
        $this->assertDatabaseHas(self::DELIVERIES, ['recipient' => 'retry@example.test', 'status' => 'pending', 'attempts' => 1]);
        $this->assertDatabaseHas(self::DELIVERIES, ['recipient' => 'sent@example.test', 'status' => 'sent', 'attempts' => 1]);

        $fail = false;
        $this->artisan('sales-orders:send-emails')->assertSuccessful();
        Http::assertSentCount(2);
        $this->travel(2)->minutes();
        $this->artisan('sales-orders:send-emails')->assertSuccessful();
        Http::assertSentCount(3);
        $this->assertDatabaseHas(self::DELIVERIES, ['recipient' => 'retry@example.test', 'status' => 'sent', 'attempts' => 2]);
    }

    public function test_persistent_delivery_failures_stop_after_five_attempts(): void
    {
        $this->recipient('admin@example.test');
        Http::fake(['https://api.zeptomail.com/*' => Http::response([], 503)]);

        $this->createOrder();
        for ($attempt = 1; $attempt <= 5; $attempt++) {
            $this->artisan('sales-orders:send-emails')->assertSuccessful();
            $this->travel(61)->minutes();
        }
        $this->assertDatabaseHas(self::DELIVERIES, ['status' => 'failed', 'attempts' => 5, 'sent_at' => null]);

        $this->artisan('sales-orders:send-emails')->assertSuccessful();
        Http::assertSentCount(5);
    }

    public function test_bulk_entry_creates_separate_recipients_and_skips_existing_addresses(): void
    {
        $this->recipient('EXISTING@example.test', ['status' => 0]);
        $controller = \Mockery::mock(NotificationEmailController::class)->makePartial()->shouldAllowMockingProtectedMethods();
        $controller->shouldReceive('authorizeModule')->once()->with('create');
        session(['active_plant_id' => 1]);

        $controller->store(\Illuminate\Http\Request::create('/', 'POST', [
            'type' => 'Sales Order', 'role_name' => 'Administrator', 'status' => 1,
            'email' => "First@example.test, existing@example.test; second@example.test\nfirst@example.test",
        ]));

        $this->assertDatabaseCount('mm_notification_emails', 3);
        $this->assertDatabaseHas('mm_notification_emails', ['email' => 'EXISTING@example.test', 'status' => 0]);
        foreach (['first@example.test', 'second@example.test'] as $address) {
            $this->assertDatabaseHas('mm_notification_emails', [
                'plant_id' => 1, 'type' => 'Sales Order', 'role_name' => 'Administrator', 'email' => $address, 'status' => 1,
            ]);
        }
        $this->assertSame('2 recipient(s) added. 2 duplicate(s) skipped.', session('success'));
    }

    public function test_an_invalid_address_rejects_the_entire_bulk_entry(): void
    {
        $controller = \Mockery::mock(NotificationEmailController::class)->makePartial()->shouldAllowMockingProtectedMethods();
        $controller->shouldReceive('authorizeModule')->once()->with('create');
        session(['active_plant_id' => 1]);

        try {
            $controller->store(\Illuminate\Http\Request::create('/', 'POST', [
                'type' => 'Sales Order', 'role_name' => 'Administrator', 'status' => 1,
                'email' => 'valid@example.test, invalid-address',
            ]));
            $this->fail('Invalid bulk email entry was accepted.');
        } catch (\Illuminate\Validation\ValidationException $exception) {
            $this->assertSame(['Email address #2 is invalid.'], $exception->errors()['email']);
        }

        $this->assertDatabaseCount('mm_notification_emails', 0);
    }

    private function createOrder(): SalesOrder
    {
        return SalesOrder::create(['plant_id' => 1, 'prefix' => 'SO/26-27/', 'order_no' => '21', 'total_qty' => 10]);
    }

    private function recipient(string $email, array $overrides = []): void
    {
        DB::table('mm_notification_emails')->insert(array_replace([
            'plant_id' => 1, 'type' => 'Sales Order', 'role_name' => 'Administrator', 'email' => $email, 'status' => 1,
        ], $overrides));
    }
}
