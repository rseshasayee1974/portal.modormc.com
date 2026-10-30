<?php
/*
Author: ragul-onemodo
Created: 2026-10-30 15:40:17 Asia/Calcutta (UTC+05:30)
*/

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\TestCase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

class ProductionSchemaRepairTest extends TestCase
{
    public function test_audit_schema_repair_preserves_account_updates_without_a_plant(): void
    {
        config(['database.default' => 'schema_repair_test', 'database.connections.schema_repair_test' => [
            'driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '',
        ], 'session.driver' => 'array']);
        Schema::create('mm_users', function (Blueprint $table) {
            $table->id();
            $table->timestamp('last_login')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
        Schema::create('mm_inventory_audit_logs', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('plant_id');
            $table->string('transaction_type');
            $table->string('action_type');
            $table->string('reference_type');
            $table->unsignedBigInteger('reference_id');
            $table->text('log_from')->nullable();
            $table->text('log_to')->nullable();
            $table->unsignedBigInteger('user_id')->nullable();
            $table->text('remarks')->nullable();
            $table->string('ip_address')->nullable();
            $table->timestamp('created_at')->nullable();
        });
        DB::table('mm_inventory_audit_logs')->insert([
            'plant_id' => 6, 'transaction_type' => 'UPDATE', 'action_type' => 'UPDATE',
            'reference_type' => 'User', 'reference_id' => 2,
        ]);
        $migration = require database_path('migrations/2026_10_30_154001_restore_nullable_plant_id_on_inventory_audit_logs_table.php');
        $migration->up();
        $migration->up();
        Log::spy();

        DB::table('mm_users')->insert(['id' => 2, 'last_login' => '2026-10-29 07:15:51']);
        User::findOrFail(2)->update(['last_login' => '2026-10-30 10:25:16']);

        $this->assertDatabaseHas('mm_inventory_audit_logs', ['id' => 1, 'plant_id' => 6]);
        $log = DB::table('mm_inventory_audit_logs')->whereNull('plant_id')->sole();
        $this->assertSame('User', $log->reference_type);
        $this->assertSame('UPDATE', $log->action_type);
        $this->assertSame('2026-10-30 10:25:16', json_decode($log->log_to, true)['last_login']);
        Log::shouldNotHaveReceived('error');

        $migration->down();
        $this->assertDatabaseHas('mm_inventory_audit_logs', ['id' => $log->id, 'plant_id' => null]);
    }
}
