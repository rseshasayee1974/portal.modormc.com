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
        Schema::table('mm_document_print_logs', function (Blueprint $table) {
            $table->string('document_sub_type')->nullable()->after('document_type');
            $table->string('document_reference')->nullable()->after('document_id');
            $table->string('user_name')->nullable()->after('user_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('mm_document_print_logs', function (Blueprint $table) {
            $table->dropColumn(['document_sub_type', 'document_reference', 'user_name']);
        });
    }
};
