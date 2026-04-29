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
        Schema::create('mm_voucher_types', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('journal_name', 100);
            $table->string('short_code', 20)->unique();
            $table->boolean('is_system_generated')->default(false);
            $table->string('prefix', 20)->nullable();
            $table->string('voucher_category', 100)->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('mm_voucher_types');
    }
};
