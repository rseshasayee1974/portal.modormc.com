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
        Schema::create('mm_entities', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('entity_type');
            $table->unsignedBigInteger('parent_id')->nullable();
            $table->string('legal_name', 191);
            $table->string('alias', 191)->nullable();
            $table->string('email', 191)->nullable();
            $table->string('url', 191)->nullable();
            $table->string('logo_file', 191)->nullable();
            $table->text('description')->nullable();
            $table->string('time_zone', 50)->nullable();
            $table->tinyInteger('is_active')->default(0);
            $table->tinyInteger('is_suspended')->default(0);
            $table->auditColumns();

            $table->index(['entity_type', 'parent_id', 'is_active']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('mm_entities');
    }
};
