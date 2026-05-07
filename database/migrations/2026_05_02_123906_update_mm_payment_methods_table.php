<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('mm_payment_methods', function (Blueprint $table) {
            // Check if column exists before adding to prevent errors
            if (!Schema::hasColumn('mm_payment_methods', 'description')) {
                $table->string('description', 255)->nullable()->after('name');
            }
            if (!Schema::hasColumn('mm_payment_methods', 'is_active')) {
                $table->boolean('is_active')->default(true)->after('description');
            }
            
            // Add missing audit and soft delete columns manually to avoid duplicates with existing timestamps
            if (!Schema::hasColumn('mm_payment_methods', 'created_by')) {
                $table->foreignId('created_by')->nullable()->after('updated_at')->constrained('users')->nullOnDelete();
            }
            if (!Schema::hasColumn('mm_payment_methods', 'updated_by')) {
                $table->foreignId('updated_by')->nullable()->after('created_by')->constrained('users')->nullOnDelete();
            }
            if (!Schema::hasColumn('mm_payment_methods', 'deleted_at')) {
                $table->softDeletes()->after('updated_by');
            }
            if (!Schema::hasColumn('mm_payment_methods', 'deleted_by')) {
                $table->foreignId('deleted_by')->nullable()->after('deleted_at')->constrained('users')->nullOnDelete();
            }
        });

        // Seed if empty
        if (DB::table('mm_payment_methods')->count() == 0) {
            DB::table('mm_payment_methods')->insert([
                ['name' => 'Cash', 'is_active' => true, 'created_at' => now(), 'updated_at' => now(), 'created_by' => 1],
                ['name' => 'UPI', 'is_active' => true, 'created_at' => now(), 'updated_at' => now(), 'created_by' => 1],
                ['name' => 'Bank Transfer', 'is_active' => true, 'created_at' => now(), 'updated_at' => now(), 'created_by' => 1],
                ['name' => 'Check', 'is_active' => true, 'created_at' => now(), 'updated_at' => now(), 'created_by' => 1],
            ]);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('mm_payment_methods', function (Blueprint $table) {
            $table->dropColumn(['description', 'is_active', 'created_by', 'updated_by', 'deleted_by']);
        });
    }
};
