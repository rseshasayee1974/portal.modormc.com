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
        Schema::create('mm_entity_taxes', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('entity_id');
            $table->string('tax_type', 50);
            $table->string('tax_number', 100);
            $table->unsignedBigInteger('country_id')->nullable()->index('mm_entity_taxes_country_id_foreign');
            $table->unsignedBigInteger('state_id')->nullable()->index('mm_entity_taxes_state_id_foreign');
            $table->tinyInteger('is_primary')->default(0);
            $table->unsignedBigInteger('created_by')->nullable()->index('mm_entity_taxes_created_by_foreign');
            $table->unsignedBigInteger('updated_by')->nullable()->index('mm_entity_taxes_updated_by_foreign');
            $table->unsignedBigInteger('deleted_by')->nullable()->index('mm_entity_taxes_deleted_by_foreign');
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['entity_id', 'tax_type']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('mm_entity_taxes');
    }
};
