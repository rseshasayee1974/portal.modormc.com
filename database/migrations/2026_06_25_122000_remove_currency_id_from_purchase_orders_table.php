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
            try {
                Schema::table('mm_purchase_orders', function (Blueprint $table) {
                    $table->dropForeign(['currency_id']);
                });
            } catch (\Exception $e) {}

            try {
                Schema::table('mm_purchase_orders', function (Blueprint $table) {
                    $table->dropColumn('currency_id');
                });
            } catch (\Exception $e) {}
=======
            Schema::table('mm_purchase_orders', function (Blueprint $table) {
                try { $table->dropForeign(['currency_id']); } catch (\Exception $e) {}
                $table->dropColumn('currency_id');
            });
>>>>>>> 33d737f1d3cca4718d4bc2b852c3c9a78f726555
        }
    }

    public function down(): void
    {
        Schema::table('mm_purchase_orders', function (Blueprint $table) {
            $table->foreignId('currency_id')->nullable()->after('closed_status')->constrained('mm_currencies')->nullOnDelete();
        });
    }
};
