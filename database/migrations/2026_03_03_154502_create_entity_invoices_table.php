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
        Schema::create('mm_entity_invoices', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('entity_id');
            $table->unsignedBigInteger('subscription_id')->index('mm_entity_invoices_subscription_id_foreign');
            $table->string('invoice_no', 50);
            $table->decimal('amount', 17);
            $table->decimal('tax_amount', 17)->default(0);
            $table->unsignedBigInteger('currency_id')->index('mm_entity_invoices_currency_id_foreign');
            $table->unsignedBigInteger('invoice_status');
            $table->timestamp('issued_at');
            $table->timestamp('due_date');
            $table->timestamp('paid_at')->nullable();
            $table->unsignedBigInteger('created_by')->nullable()->index('mm_entity_invoices_created_by_foreign');
            $table->unsignedBigInteger('updated_by')->nullable()->index('mm_entity_invoices_updated_by_foreign');
            $table->unsignedBigInteger('deleted_by')->nullable()->index('mm_entity_invoices_deleted_by_foreign');
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['entity_id', 'invoice_no']);
            $table->index(['entity_id', 'issued_at']);
            $table->index(['invoice_status', 'due_date']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('mm_entity_invoices');
    }
};
