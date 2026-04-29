<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('mm_billings', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained('mm_users')->cascadeOnDelete();
            $table->string('month', 7);
            $table->decimal('total_amount', 12, 4)->default(0);
            $table->json('mm_breakdown_json');
            $table->string('status', 20)->default('pending');
            $table->timestamps();

            $table->unique(['user_id', 'month'], 'mm_billings_user_month_unique');
            $table->index(['user_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mm_billings');
    }
};
