<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('mm_usage_logs', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('entity_id');
            $table->string('feature_code', 80);
            $table->unsignedInteger('used_count')->default(0);
            $table->string('period', 7);
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['entity_id', 'feature_code', 'period'], 'usage_logs_entity_feature_period_unique');
            $table->index(['feature_code', 'period']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mm_usage_logs');
    }
};
