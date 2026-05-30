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
        // 1. Add credentials fields to mm_entities table
        Schema::table('mm_entities', function (Blueprint $table) {
            if (!Schema::hasColumn('mm_entities', 'einv_username')) {
                $table->string('einv_username', 255)->nullable()->after('api_key');
            }
            if (!Schema::hasColumn('mm_entities', 'einv_password')) {
                $table->string('einv_password', 255)->nullable()->after('einv_username');
            }
        });

        // 2. Create mm_einvoice_auths table
        Schema::create('mm_einvoice_auths', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('entity_id');
            $table->unsignedBigInteger('user_id');
            $table->text('app_key')->nullable();
            $table->string('user_name', 255)->nullable();
            $table->text('auth_token')->nullable();
            $table->text('sek_key')->nullable();
            $table->timestamp('token_generated_at')->nullable();
            $table->timestamp('token_expiry_at')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->timestamps();

            // Indexes
            $table->index(['entity_id', 'user_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('mm_einvoice_auths');

        Schema::table('mm_entities', function (Blueprint $table) {
            $table->dropColumn(['einv_username', 'einv_password']);
        });
    }
};
