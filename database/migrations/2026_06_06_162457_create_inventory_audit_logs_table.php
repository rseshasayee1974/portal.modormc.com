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
        Schema::create('mm_inventory_audit_logs', function (Blueprint $table) {
            $table->id();


            $table->string('transaction_type'); // product, stockin, stockout,  
            $table->string('reference_type')->nullable(); //Update, Delete 
            $table->unsignedBigInteger('reference_id')->nullable();

            $table->longText('log_from')->nullable(); 
            $table->longText('log_to')->nullable(); 

            $table->foreignId('user_id')->nullable()->constrained('mm_users')->nullOnDelete();
            $table->text('remarks')->nullable();

            $table->ipAddress('ip_address')->nullable();

            
            $table->timestamp('created_at')->useCurrent();
            
            $table->index(['reference_type', 'reference_id']);
    
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('mm_inventory_audit_logs');
    }
};
