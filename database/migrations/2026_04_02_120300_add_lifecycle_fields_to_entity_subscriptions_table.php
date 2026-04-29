<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('mm_entity_subscriptions', function (Blueprint $table) {
            $table->string('billing_cycle', 20)->default('monthly')->after('subscription_status_id');
            $table->unsignedBigInteger('scheduled_plan_id')->nullable()->after('plan_id');
            $table->timestamp('scheduled_change_at')->nullable()->after('expires_at');
        });
    }

    public function down(): void
    {
        Schema::table('mm_entity_subscriptions', function (Blueprint $table) {
            $table->dropColumn(['billing_cycle', 'scheduled_plan_id', 'scheduled_change_at']);
        });
    }
};
