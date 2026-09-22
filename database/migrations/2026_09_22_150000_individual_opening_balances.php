<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('mm_opening_balance_batches', function (Blueprint $t) {
            $t->dropUnique('mm_opening_balance_batches_plant_id_unique');
            $t->unsignedBigInteger('patron_id')->nullable();
            $t->unsignedBigInteger('account_id')->nullable();
            $t->string('active_key', 80)->nullable();
            $t->unsignedBigInteger('replaces_id')->nullable();
            $t->unique(['plant_id', 'active_key'], 'opening_balance_active_target');
        });
        if (!Schema::hasColumn('mm_opening_balance_batches', 'deleted_at')) {
            Schema::table('mm_opening_balance_batches', fn (Blueprint $t) => $t->softDeletes());
        }
        if (!Schema::hasColumn('mm_opening_balance_batches', 'deleted_by')) {
            Schema::table('mm_opening_balance_batches', fn (Blueprint $t) => $t->unsignedBigInteger('deleted_by')->nullable());
        }
    }
    public function down(): void
    {
        throw new RuntimeException('Individual opening balances cannot be merged automatically. Restore a backup to roll back.');
    }
};
