<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('mm_purchase_orders', 'currency_id')) {
<<<<<<< HEAD
=======
            try {
                Schema::table('mm_purchase_orders', function (Blueprint $table) {
                    $table->dropForeign(['currency_id']);
                });
            } catch (\Exception $e) {}

            try {
                Schema::table('mm_purchase_orders', function (Blueprint $table) {
                    $table->dropColumn('currency_id');
                });
            } catch (\Exception $e) {
                
            }
>>>>>>> b8fd6f177386bdf3af4f623db1298a0357e39cc3
            Schema::table('mm_purchase_orders', function (Blueprint $table) {
                try { $table->dropForeign(['currency_id']); } catch (\Exception $e) {}
                try { $table->dropColumn('currency_id'); } catch (\Exception $e) {}
            });
        }
    }

    public function down(): void
    {
        Schema::table('mm_purchase_orders', function (Blueprint $table) {
            $table->foreignId('currency_id')->nullable()->after('closed_status')->constrained('mm_currencies')->nullOnDelete();
        });
    }
};
