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
        Schema::create('mm_account_types', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('entity_id');
            $table->string('code')->nullable();
            $table->unsignedBigInteger('account_id');
            $table->unsignedBigInteger('parent_id')->nullable();
            $table->text('title')->nullable();
            $table->tinyInteger('status')->default(1);
            $table->dateTime('created_at')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->dateTime('updated_at')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->dateTime('deleted_at')->nullable();
            $table->unsignedBigInteger('deleted_by')->nullable();

            $table->foreign('entity_id')->references('id')->on('mm_entities')->onDelete('cascade');
            $table->foreign('account_id')->references('id')->on('mm_accounts')->onDelete('cascade');
            $table->foreign('parent_id')->references('id')->on('mm_account_types')->onDelete('set null');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mm_account_types');
    }
};
