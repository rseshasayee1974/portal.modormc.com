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
        // Drop tables first if they exist to handle clean retries from failed migrations
        Schema::dropIfExists('mm_ewaybill_details');
        Schema::dropIfExists('mm_ewaybill_auth');
        Schema::dropIfExists('mm_einvoice_invoice_rel');
        Schema::dropIfExists('mm_einvoice_auth');
        Schema::dropIfExists('mm_einvoice_auths'); // Clean up old plural table if present

        Schema::create('mm_einvoice_auth', function (Blueprint $table) {
            $table->integer('id')->autoIncrement();
            $table->integer('user_id');
            $table->integer('plant_id')->index(); // replaces copmany_id
            $table->string('auth_token', 255);
            $table->string('app_key', 255);
            $table->string('user_name', 100);
            $table->string('sek_key', 255);
            $table->dateTime('token_generated_at');
            $table->dateTime('token_expiry_at');
            $table->integer('created_by')->nullable();
            $table->dateTime('created_at')->nullable();
            $table->dateTime('modified_at')->nullable();
        });

        Schema::create('mm_einvoice_invoice_rel', function (Blueprint $table) {
            $table->integer('id')->autoIncrement();
            $table->integer('invoice_id')->index();
            $table->integer('cr_dr_id')->default(0);
            $table->string('einv_ackno', 191)->index('einvacc'); // varchar(191) avoids "key too long" error on utf8mb4
            $table->dateTime('einv_ack_date')->index('einvdate');
            $table->longText('einv_irn');
            $table->longText('einv_signed_invoice');
            $table->longText('einv_signed_qrcode');
            $table->string('einv_status', 50);
            $table->dateTime('einv_cancel_at')->nullable();
            $table->integer('plant_id')->index('plant'); // replaces company_id
            $table->tinyInteger('status')->default(0);
            $table->dateTime('created')->index('created');
            $table->integer('created_by')->nullable();
            $table->dateTime('modified')->nullable();
            $table->integer('modified_by')->nullable();
        });

        // Add fulltext index separately
        Schema::table('mm_einvoice_invoice_rel', function (Blueprint $table) {
            $table->fullText('einv_irn', 'einvirn');
        });

        Schema::create('mm_ewaybill_auth', function (Blueprint $table) {
            $table->integer('id')->autoIncrement();
            $table->bigInteger('plant_id')->index(); // replaces company_id
            $table->bigInteger('user_id');
            $table->string('username', 250)->nullable();
            $table->string('password', 250)->nullable();
            $table->string('gstin', 250)->nullable();
            $table->string('authtoken', 250)->nullable();
            $table->text('transaction_no')->nullable();
            $table->dateTime('token_generated_at');
            $table->dateTime('token_expiry_at')->nullable();
            $table->dateTime('created_at')->nullable();
            $table->bigInteger('created_by')->nullable();
            $table->dateTime('modified_at')->nullable();
            $table->bigInteger('modified_by')->nullable();
        });

        Schema::create('mm_ewaybill_details', function (Blueprint $table) {
            $table->integer('id')->autoIncrement();
            $table->bigInteger('plant_id')->index(); // replaces company_id
            $table->string('generation_type', 150)->nullable();
            $table->bigInteger('origin_id')->nullable();
            $table->string('ewaybill_no', 200)->nullable();
            $table->string('ewaybill_date', 200)->nullable();
            $table->string('valid_upto', 200)->nullable();
            $table->string('ewaybill_status', 200)->nullable();
            $table->dateTime('ewaybill_cancel_at')->nullable();
            $table->bigInteger('ewaybill_cancel_by')->nullable();
            $table->dateTime('ewaybill_reject_at')->nullable();
            $table->bigInteger('ewaybill_reject_by')->nullable();
            $table->dateTime('created_at')->nullable();
            $table->bigInteger('created_by')->nullable();
            $table->dateTime('modified_at')->nullable();
            $table->bigInteger('modified_by')->nullable();
            $table->tinyInteger('status')->default(-1);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('mm_ewaybill_details');
        Schema::dropIfExists('mm_ewaybill_auth');
        Schema::dropIfExists('mm_einvoice_invoice_rel');
        Schema::dropIfExists('mm_einvoice_auth');
    }
};
