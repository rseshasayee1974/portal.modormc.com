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
        Schema::create('mm_entity_contacts', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('entity_id');
            $table->unsignedBigInteger('contact_type')->index('mm_entity_contacts_contact_type_foreign');
            $table->string('contact_person', 191);
            $table->string('email', 191)->nullable();
            $table->string('mobile', 20)->nullable();
            $table->string('alt_mobile', 20)->nullable();
            $table->string('landline', 20)->nullable();
            $table->tinyInteger('is_primary')->default(0);
            $table->unsignedBigInteger('created_by')->nullable()->index('mm_entity_contacts_created_by_foreign');
            $table->unsignedBigInteger('updated_by')->nullable()->index('mm_entity_contacts_updated_by_foreign');
            $table->unsignedBigInteger('deleted_by')->nullable()->index('mm_entity_contacts_deleted_by_foreign');
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['entity_id', 'contact_type']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('mm_entity_contacts');
    }
};
