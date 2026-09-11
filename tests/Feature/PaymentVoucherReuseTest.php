<?php

namespace Tests\Feature;

use Illuminate\Database\QueryException;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class PaymentVoucherReuseTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->assertSame('sqlite', DB::connection()->getDriverName());
        $this->assertSame(':memory:', DB::connection()->getDatabaseName());
        Schema::create('mm_payments', function (Blueprint $table) {
            $table->id();
            $table->integer('plant_id');
            $table->softDeletes();
            $table->integer('deleted_by')->nullable();
        });
        Schema::create('mm_journal_entries', function (Blueprint $table) {
            $table->id();
            $table->integer('plant_id');
            $table->string('voucher_type');
            $table->string('voucher_number');
            $table->string('ref_module')->nullable();
            $table->integer('ref_id');
            $table->softDeletes();
            $table->integer('deleted_by')->nullable();
            $table->boolean('is_deleted')->default(false);
            $table->unique(['plant_id', 'voucher_type', 'voucher_number'], 'uk_voucher');
        });
    }

    private function migrateVoucherIndex(): void
    {
        (require database_path('migrations/2026_09_11_130000_allow_deleted_payment_voucher_reuse.php'))->up();
    }

    private function voucher(array $attributes = []): array
    {
        return array_merge(['plant_id' => 1, 'voucher_type' => 'PAYMENT',
            'voucher_number' => 'Pay/26-27/0001', 'ref_module' => 'payment', 'ref_id' => 1], $attributes);
    }

    public function test_deleted_payment_is_repaired_and_number_can_be_reused_repeatedly(): void
    {
        DB::table('mm_payments')->insert(['id' => 1, 'plant_id' => 1, 'deleted_at' => '2026-09-11 12:00:00', 'deleted_by' => 7]);
        DB::table('mm_journal_entries')->insert($this->voucher());
        $this->migrateVoucherIndex();
        $this->assertNotNull(DB::table('mm_journal_entries')->where('ref_id', 1)->value('deleted_at'));
        DB::table('mm_journal_entries')->insert($this->voucher(['ref_id' => 2]));
        DB::table('mm_journal_entries')->where('ref_id', 2)->update(['deleted_at' => '2026-09-11 13:00:00']);
        DB::table('mm_journal_entries')->insert($this->voucher(['ref_id' => 3]));
        $this->assertSame(3, DB::table('mm_journal_entries')->count());
        $this->assertSame(1, DB::table('mm_journal_entries')->whereNull('deleted_at')->count());
    }

    public function test_active_duplicate_is_rejected(): void
    {
        $this->migrateVoucherIndex();
        DB::table('mm_journal_entries')->insert($this->voucher());
        $this->expectException(QueryException::class);
        DB::table('mm_journal_entries')->insert($this->voucher(['ref_id' => 2]));
    }

    public function test_legacy_migration_followed_by_replacement_keeps_active_uniqueness(): void
    {
        (require database_path('migrations/2026_09_10_174648_drop_uk_voucher_from_journal_entries_table.php'))->up();
        $this->assertTrue(Schema::hasIndex('mm_journal_entries', 'uk_voucher'));
        $this->migrateVoucherIndex();
        $this->assertFalse(Schema::hasIndex('mm_journal_entries', 'uk_voucher'));
        DB::table('mm_journal_entries')->insert($this->voucher());
        $this->expectException(QueryException::class);
        DB::table('mm_journal_entries')->insert($this->voucher(['ref_id' => 2]));
    }

    public function test_replacement_handles_previously_removed_legacy_index(): void
    {
        Schema::table('mm_journal_entries', fn (Blueprint $table) => $table->dropUnique('uk_voucher'));
        $this->migrateVoucherIndex();
        DB::table('mm_journal_entries')->insert($this->voucher());
        $this->expectException(QueryException::class);
        DB::table('mm_journal_entries')->insert($this->voucher(['ref_id' => 2]));
    }

    public function test_other_plants_and_voucher_types_can_use_same_number(): void
    {
        $this->migrateVoucherIndex();
        DB::table('mm_journal_entries')->insert($this->voucher());
        DB::table('mm_journal_entries')->insert($this->voucher(['plant_id' => 2]));
        DB::table('mm_journal_entries')->insert($this->voucher(['voucher_type' => 'RECEIPT']));
        $this->assertSame(3, DB::table('mm_journal_entries')->count());
    }

    public function test_other_journal_sources_keep_original_uniqueness(): void
    {
        $this->migrateVoucherIndex();
        DB::table('mm_journal_entries')->insert($this->voucher(['ref_module' => 'manual', 'deleted_at' => '2026-09-11 12:00:00']));
        $this->expectException(QueryException::class);
        DB::table('mm_journal_entries')->insert($this->voucher(['ref_module' => 'manual', 'ref_id' => 2]));
    }
}
