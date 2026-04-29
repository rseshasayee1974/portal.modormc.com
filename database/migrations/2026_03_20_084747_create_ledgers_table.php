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
        Schema::create('mm_ledgers', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('entity_id');
            $table->unsignedBigInteger('account_type_id');
            $table->string('code')->nullable();
            $table->boolean('is_pnl')->default(false);
            $table->string('title');
            $table->string('slug')->nullable();
            $table->text('notes')->nullable();
            $table->text('description')->nullable();
            $table->tinyInteger('status')->default(1);
            
            $table->auditColumns();

            $table->foreign('entity_id')->references('id')->on('mm_entities')->onDelete('cascade');
            $table->foreign('account_type_id')->references('id')->on('mm_account_types')->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mm_ledgers');
    }
};
