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
        Schema::create('mm_print_templates', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('key')->unique();
            $table->string('category')->default('general'); // invoice, po, etc.
            $table->string('thumbnail')->nullable();
            $table->boolean('is_system')->default(false);
            $table->json('mm_config')->nullable(); // For colors, fonts, etc.
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mm_print_templates');
    }
};
