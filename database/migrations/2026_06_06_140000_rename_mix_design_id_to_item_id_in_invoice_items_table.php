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
        try {
            Schema::table('mm_invoice_items', function (Blueprint $table) {
                $table->dropForeign(['mix_design_id']);
            });
        } catch (\Exception $e) {}

        Schema::table('mm_invoice_items', function (Blueprint $table) {
            // Rename the column
            $table->renameColumn('mix_design_id', 'item_id');
            
            // Optionally, if item_id should now reference mm_products, uncomment the following line:
            // $table->foreign('item_id')->references('id')->on('mm_products')->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('mm_invoice_items', function (Blueprint $table) {
            // Drop the new foreign key constraint if you added one above
            // $table->dropForeign(['item_id']);
            
            // Rename back to mix_design_id
            $table->renameColumn('item_id', 'mix_design_id');
            
            // Restore the old foreign key constraint
            $table->foreign('mix_design_id')->references('id')->on('mm_mix_designs')->nullOnDelete();
        });
    }
};
