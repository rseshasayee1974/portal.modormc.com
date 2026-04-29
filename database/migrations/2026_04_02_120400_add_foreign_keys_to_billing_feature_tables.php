<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('mm_plan_features', function (Blueprint $table) {
            $table->foreign('plan_id')->references('id')->on('mm_plans')->onDelete('cascade');
            $table->foreign('feature_id')->references('id')->on('mm_features')->onDelete('cascade');
        });

        Schema::table('mm_usage_logs', function (Blueprint $table) {
            $table->foreign('entity_id')->references('id')->on('mm_entities')->onDelete('cascade');
        });

        Schema::table('mm_entity_subscriptions', function (Blueprint $table) {
            $table->foreign('scheduled_plan_id')->references('id')->on('mm_plans')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('mm_entity_subscriptions', function (Blueprint $table) {
            $table->dropForeign(['scheduled_plan_id']);
        });

        Schema::table('mm_usage_logs', function (Blueprint $table) {
            $table->dropForeign(['entity_id']);
        });

        Schema::table('mm_plan_features', function (Blueprint $table) {
            $table->dropForeign(['plan_id']);
            $table->dropForeign(['feature_id']);
        });
    }
};
