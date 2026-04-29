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
        Schema::table('mm_entity_subscriptions', function (Blueprint $table) {
            $table->foreign(['created_by'])->references(['id'])->on('mm_users')->onUpdate('no action')->onDelete('set null');
            $table->foreign(['deleted_by'])->references(['id'])->on('mm_users')->onUpdate('no action')->onDelete('set null');
            $table->foreign(['entity_id'])->references(['id'])->on('mm_entities')->onUpdate('no action')->onDelete('no action');
            $table->foreign(['plan_id'])->references(['id'])->on('mm_plans')->onUpdate('no action')->onDelete('no action');
            $table->foreign(['subscription_status_id'])->references(['id'])->on('mm_subscription_statuses')->onUpdate('no action')->onDelete('no action');
            $table->foreign(['updated_by'])->references(['id'])->on('mm_users')->onUpdate('no action')->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('mm_entity_subscriptions', function (Blueprint $table) {
            $table->dropForeign('mm_entity_subscriptions_created_by_foreign');
            $table->dropForeign('mm_entity_subscriptions_deleted_by_foreign');
            $table->dropForeign('mm_entity_subscriptions_entity_id_foreign');
            $table->dropForeign('mm_entity_subscriptions_plan_id_foreign');
            $table->dropForeign('mm_entity_subscriptions_subscription_status_id_foreign');
            $table->dropForeign('mm_entity_subscriptions_updated_by_foreign');
        });
    }
};
