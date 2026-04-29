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
        Schema::create('mm_countries', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('country_name', 150)->unique();
            $table->string('country_code', 10)->unique();
            $table->tinyInteger('is_active')->default(1);
             $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('mm_countries');
    }
};
