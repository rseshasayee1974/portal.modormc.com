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
        Schema::table('mm_entity_invoices', function (Blueprint $table) {
            $table->foreign(['created_by'])->references(['id'])->on('mm_users')->onUpdate('no action')->onDelete('set null');
            $table->foreign(['currency_id'])->references(['id'])->on('mm_currencies')->onUpdate('no action')->onDelete('no action');
            $table->foreign(['deleted_by'])->references(['id'])->on('mm_users')->onUpdate('no action')->onDelete('set null');
            $table->foreign(['entity_id'])->references(['id'])->on('mm_entities')->onUpdate('no action')->onDelete('no action');
            $table->foreign(['invoice_status'])->references(['id'])->on('mm_invoice_statuses')->onUpdate('no action')->onDelete('no action');
            $table->foreign(['subscription_id'])->references(['id'])->on('mm_entity_subscriptions')->onUpdate('no action')->onDelete('no action');
            $table->foreign(['updated_by'])->references(['id'])->on('mm_users')->onUpdate('no action')->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('mm_entity_invoices', function (Blueprint $table) {
            $table->dropForeign('mm_entity_invoices_created_by_foreign');
            $table->dropForeign('mm_entity_invoices_currency_id_foreign');
            $table->dropForeign('mm_entity_invoices_deleted_by_foreign');
            $table->dropForeign('mm_entity_invoices_entity_id_foreign');
            $table->dropForeign('mm_entity_invoices_invoice_status_foreign');
            $table->dropForeign('mm_entity_invoices_subscription_id_foreign');
            $table->dropForeign('mm_entity_invoices_updated_by_foreign');
        });
    }
};
