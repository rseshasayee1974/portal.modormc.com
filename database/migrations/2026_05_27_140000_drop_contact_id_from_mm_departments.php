<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('mm_departments', function (Blueprint $table) {
            if (Schema::hasColumn('mm_departments', 'contact_id')) {
                $table->dropForeign(['contact_id']);
                $table->dropColumn('contact_id');
            }
        });
    }

    public function down(): void
    {
        Schema::table('mm_departments', function (Blueprint $table) {
            $table->foreignId('contact_id')->nullable()->constrained('mm_contacts')->nullOnDelete();
        });
    }
};
