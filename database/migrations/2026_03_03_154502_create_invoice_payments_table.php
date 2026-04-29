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
        Schema::create('mm_invoice_payments', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('invoice_id');
            $table->unsignedBigInteger('gateway_id')->index('mm_invoice_payments_gateway_id_foreign');
            $table->string('transaction_ref', 191);
            $table->decimal('amount', 17);
            $table->unsignedBigInteger('payment_status_id')->index('mm_invoice_payments_payment_status_id_foreign');
            $table->timestamp('paid_at')->nullable();
            $table->unsignedBigInteger('created_by')->nullable()->index('mm_invoice_payments_created_by_foreign');
            $table->unsignedBigInteger('updated_by')->nullable()->index('mm_invoice_payments_updated_by_foreign');
            $table->unsignedBigInteger('deleted_by')->nullable()->index('mm_invoice_payments_deleted_by_foreign');
            $table->timestamps();
            $table->softDeletes();

            $table->index(['invoice_id', 'gateway_id', 'payment_status_id'], 'mip_invoice_gateway_status_idx');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('mm_invoice_payments');
    }
};
