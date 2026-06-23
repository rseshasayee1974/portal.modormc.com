<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        DB::table('mm_menus')
            ->where('title', 'Sales Order')
            ->update(['title' => 'Customer PO']);

        DB::table('mm_terms_condition')
            ->where('order_type', 'Sales Order')
            ->update(['order_type' => 'Customer PO']);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::table('mm_menus')
            ->where('title', 'Customer PO')
            ->update(['title' => 'Sales Order']);

        DB::table('mm_terms_condition')
            ->where('order_type', 'Customer PO')
            ->update(['order_type' => 'Sales Order']);
    }
};
