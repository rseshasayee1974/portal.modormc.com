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
        Schema::create('mm_entity_subscriptions', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('entity_id')->unique();
            $table->unsignedBigInteger('plan_id');
            $table->unsignedBigInteger('subscription_status_id')->index('mm_entity_subscriptions_subscription_status_id_foreign');
            $table->timestamp('started_at');
            $table->timestamp('expires_at')->nullable();
            $table->unsignedBigInteger('created_by')->nullable()->index('mm_entity_subscriptions_created_by_foreign');
            $table->unsignedBigInteger('updated_by')->nullable()->index('mm_entity_subscriptions_updated_by_foreign');
            $table->unsignedBigInteger('deleted_by')->nullable()->index('mm_entity_subscriptions_deleted_by_foreign');
            $table->timestamps();
            $table->softDeletes();

            $table->index(['plan_id', 'subscription_status_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('mm_entity_subscriptions');
    }
};
