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
        Schema::table('mm_plants', function (Blueprint $table) {
            if (!Schema::hasColumn('mm_plants', 'ewaybill_client_id')) {
                $table->string('ewaybill_client_id', 255)->nullable()->after('gstin');
            }
            if (!Schema::hasColumn('mm_plants', 'ewaybill_secret')) {
                $table->string('ewaybill_secret', 255)->nullable()->after('ewaybill_client_id');
            }
            if (!Schema::hasColumn('mm_plants', 'einvoice_client_id')) {
                $table->string('einvoice_client_id', 255)->nullable()->after('ewaybill_secret');
            }
            if (!Schema::hasColumn('mm_plants', 'einvoice_secret')) {
                $table->string('einvoice_secret', 255)->nullable()->after('einvoice_client_id');
            }
        });

        Schema::table('mm_einvoice_auths', function (Blueprint $table) {
            if (!Schema::hasColumn('mm_einvoice_auths', 'plant_id')) {
                $table->unsignedBigInteger('plant_id')->nullable()->after('entity_id');
                $table->index(['entity_id', 'plant_id', 'user_id']);
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('mm_plants', function (Blueprint $table) {
            $table->dropColumn([
                'ewaybill_client_id',
                'ewaybill_secret',
                'einvoice_client_id',
                'einvoice_secret'
            ]);
        });

        Schema::table('mm_einvoice_auths', function (Blueprint $table) {
            if (Schema::hasColumn('mm_einvoice_auths', 'plant_id')) {
                $table->dropIndex(['entity_id', 'plant_id', 'user_id']);
                $table->dropColumn('plant_id');
            }
        });
    }
};