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
        Schema::create('mm_entity_bank_accounts', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('entity_id');
            $table->unsignedBigInteger('account_type')->index('mm_entity_bank_accounts_account_type_foreign');
            $table->string('account_number', 50);
            $table->string('bank_name', 191);
            $table->string('bank_branch', 191)->nullable();
            $table->string('ifsc_code', 20)->nullable();
            $table->text('bank_address')->nullable();
            $table->tinyInteger('is_primary')->default(0);
            $table->unsignedBigInteger('created_by')->nullable()->index('mm_entity_bank_accounts_created_by_foreign');
            $table->unsignedBigInteger('updated_by')->nullable()->index('mm_entity_bank_accounts_updated_by_foreign');
            $table->unsignedBigInteger('deleted_by')->nullable()->index('mm_entity_bank_accounts_deleted_by_foreign');
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['entity_id', 'account_type']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('mm_entity_bank_accounts');
    }
};
