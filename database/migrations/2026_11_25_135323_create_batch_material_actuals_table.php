<?php

/*
Author: ragul-onemodo
Created: 2026-11-25 13:53:59 Asia/Calcutta (UTC+05:30)
*/

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('mm_batch_material_actuals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('batch_id')->constrained('mm_batches')->cascadeOnDelete();
            $table->unsignedBigInteger('product_id');
            $table->decimal('actual_qty', 10, 3);
            $table->timestamps();
            $table->unique(['batch_id', 'product_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mm_batch_material_actuals');
    }
};
