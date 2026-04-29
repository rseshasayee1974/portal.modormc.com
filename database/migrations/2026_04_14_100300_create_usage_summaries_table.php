<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('mm_usage_summaries', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained('mm_users')->cascadeOnDelete();
            $table->string('mm_module', 40);
            $table->date('date');
            $table->string('month', 7);
            $table->unsignedBigInteger('tokens')->default(0);
            $table->unsignedInteger('requests')->default(0);
            $table->timestamps();

            $table->unique(['user_id', 'mm_module', 'date'], 'mm_usage_summaries_user_module_date_unique');
            $table->index(['user_id', 'month', 'mm_module']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mm_usage_summaries');
    }
};
