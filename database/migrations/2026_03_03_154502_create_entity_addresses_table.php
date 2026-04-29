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
        Schema::create('mm_entity_addresses', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('entity_id');
            $table->unsignedBigInteger('address_type')->index('mm_entity_addresses_address_type_foreign');
            $table->string('line_1', 191);
            $table->string('line_2', 191)->nullable();
            $table->string('city', 191);
            $table->string('zipcode', 20)->nullable();
            $table->string('landmark', 191)->nullable();
            $table->unsignedBigInteger('country_id')->nullable();
            $table->unsignedBigInteger('state_id')->nullable()->index('mm_entity_addresses_state_id_foreign');
            $table->tinyInteger('is_primary')->default(0);
            $table->unsignedBigInteger('created_by')->nullable()->index('mm_entity_addresses_created_by_foreign');
            $table->unsignedBigInteger('updated_by')->nullable()->index('mm_entity_addresses_updated_by_foreign');
            $table->unsignedBigInteger('deleted_by')->nullable()->index('mm_entity_addresses_deleted_by_foreign');
            $table->timestamps();
            $table->softDeletes();

            $table->index(['country_id', 'state_id']);
            $table->unique(['entity_id', 'address_type']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('mm_entity_addresses');
    }
};
