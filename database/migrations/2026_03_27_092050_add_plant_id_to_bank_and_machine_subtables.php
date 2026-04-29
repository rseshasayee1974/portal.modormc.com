<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('mm_patron_bank_accounts', function (Blueprint $table) {
            if (!Schema::hasColumn('mm_patron_bank_accounts', 'plant_id')) {
                $table->foreignId('plant_id')->after('id')->nullable()->constrained('mm_plants')->nullOnDelete();
            }
            if (Schema::hasColumn('mm_patron_bank_accounts', 'entity_id')) {
                $table->unsignedBigInteger('entity_id')->nullable()->change();
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('mm_patron_bank_accounts', function (Blueprint $table) {
            $table->dropForeign(['plant_id']);
            $table->dropColumn('plant_id');
            if (Schema::hasColumn('mm_patron_bank_accounts', 'entity_id')) {
                $table->unsignedBigInteger('entity_id')->nullable(false)->change();
            }
        });
    }
};
