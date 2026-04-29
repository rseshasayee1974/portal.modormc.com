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
        // Fix mm_taxes
        Schema::table('mm_taxes', function (Blueprint $table) {
            if (!Schema::hasColumn('mm_taxes', 'entity_id')) {
                $table->unsignedBigInteger('entity_id')->nullable()->after('plant_id');
            }
            if (Schema::hasColumn('mm_taxes', 'updated_by')) {
                $table->renameColumn('updated_by', 'updated_by');
            }
            if (Schema::hasColumn('mm_taxes', 'updated_at')) {
                $table->renameColumn('updated_at', 'updated_at');
            }
        });

        // Fix party_rates
        Schema::table('mm_party_rates', function (Blueprint $table) {
            if (Schema::hasColumn('mm_party_rates', 'updated_by')) {
                $table->renameColumn('updated_by', 'updated_by');
            }
            if (Schema::hasColumn('mm_party_rates', 'updated_at')) {
                $table->renameColumn('updated_at', 'updated_at');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('mm_taxes', function (Blueprint $table) {
            if (Schema::hasColumn('mm_taxes', 'entity_id')) {
                $table->dropColumn('entity_id');
            }
            if (Schema::hasColumn('mm_taxes', 'updated_by')) {
                $table->renameColumn('updated_by', 'updated_by');
            }
            if (Schema::hasColumn('mm_taxes', 'updated_at')) {
                $table->renameColumn('updated_at', 'updated_at');
            }
        });

        Schema::table('mm_party_rates', function (Blueprint $table) {
            if (Schema::hasColumn('mm_party_rates', 'updated_by')) {
                $table->renameColumn('updated_by', 'updated_by');
            }
            if (Schema::hasColumn('mm_party_rates', 'updated_at')) {
                $table->renameColumn('updated_at', 'updated_at');
            }
        });
    }
};
