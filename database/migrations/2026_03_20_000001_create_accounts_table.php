<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('mm_accounts', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('entity_id')->nullable()->index();
            $table->string('code')->nullable();
            $table->string('title', 255);
            $table->tinyInteger('status')->default(1)->comment('1=active, 0=inactive');
            $table->softDeletes();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamp('created')->nullable();
            $table->timestamp('modified')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();

            // Foreign key to mm_entities
            $table->foreign('entity_id')
                  ->references('id')
                  ->on('mm_entities')
                  ->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mm_accounts');
    }
};
