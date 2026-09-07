<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('qc_test_parameters', function (Blueprint $table) {
            $table->string('data_type', 50)->default('text')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('qc_test_parameters', function (Blueprint $table) {
            $table->string('data_type', 50)->default('decimal')->nullable(false)->change();
        });
    }
};
