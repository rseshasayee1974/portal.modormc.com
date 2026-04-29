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
        Schema::create('mm_patron_bank_accounts', function (Blueprint $table) {
            $table->id();

           $table->foreignId('plant_id')->constrained('mm_plants')->cascadeOnDelete();
            
            $table->foreignId('bank_account_type')
                  ->constrained('mm_bank_account_types')
                  ->cascadeOnDelete();
                  
            $table->foreignId('patron_id')
                  ->constrained('mm_patrons')
                  ->cascadeOnDelete();

            // Bank details
            $table->string('account_holder_name')->nullable();
            $table->string('account_number', 100);

            $table->string('bank_name', 150);
            $table->string('branch_name', 150)->nullable();
            $table->string('ifsc_code', 20);

            // Flags
            $table->boolean('is_primary')->default(0);
            $table->boolean('status')->default(1);
            $table->boolean('displayed')->default(1);

            $table->timestamps();
            $table->softDeletes();

            // Audit
            $table->foreignId('created_by')->nullable()->constrained('mm_users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('mm_users')->nullOnDelete();

            // Index
            $table->index(['plant_id', 'patron_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mm_patron_bank_accounts');
    }
};
