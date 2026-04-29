<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('mm_api_usage_logs', function (Blueprint $table): void {
            $table->unsignedBigInteger('entity_id')->nullable()->after('user_id');
            $table->unsignedBigInteger('plant_id')->nullable()->after('entity_id');
            $table->index(['user_id', 'entity_id', 'plant_id', 'mm_module', 'created_at'], 'api_usage_logs_scope_idx');
        });

        Schema::table('mm_usage_summaries', function (Blueprint $table): void {
            $table->unsignedBigInteger('entity_id')->nullable()->after('user_id');
            $table->unsignedBigInteger('plant_id')->nullable()->after('entity_id');
            $table->dropUnique('mm_usage_summaries_user_module_date_unique');
            $table->unique(
                ['user_id', 'entity_id', 'plant_id', 'mm_module', 'date'],
                'usage_summaries_scope_unique'
            );
            $table->index(['user_id', 'entity_id', 'plant_id', 'month', 'mm_module'], 'usage_summaries_scope_idx');
        });

        Schema::table('mm_billings', function (Blueprint $table): void {
            $table->unsignedBigInteger('entity_id')->nullable()->after('user_id');
            $table->unsignedBigInteger('plant_id')->nullable()->after('entity_id');
            $table->dropUnique('mm_billings_user_month_unique');
            $table->unique(['user_id', 'entity_id', 'plant_id', 'month'], 'billings_scope_unique');
            $table->index(['user_id', 'entity_id', 'plant_id', 'status'], 'billings_scope_status_idx');
        });
    }

    public function down(): void
    {
        Schema::table('mm_billings', function (Blueprint $table): void {
            $table->dropIndex('mm_billings_scope_status_idx');
            $table->dropUnique('mm_billings_scope_unique');
            $table->unique(['user_id', 'month'], 'billings_user_month_unique');
            $table->dropColumn(['entity_id', 'plant_id']);
        });

        Schema::table('mm_usage_summaries', function (Blueprint $table): void {
            $table->dropIndex('mm_usage_summaries_scope_idx');
            $table->dropUnique('mm_usage_summaries_scope_unique');
            $table->unique(['user_id', 'mm_module', 'date'], 'usage_summaries_user_module_date_unique');
            $table->dropColumn(['entity_id', 'plant_id']);
        });

        Schema::table('mm_api_usage_logs', function (Blueprint $table): void {
            $table->dropIndex('mm_api_usage_logs_scope_idx');
            $table->dropColumn(['entity_id', 'plant_id']);
        });
    }
};
