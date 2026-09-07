<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('qc_units')) {
            Schema::create('qc_units', function (Blueprint $table) {
                $table->id();
                $table->foreignId('plant_id')->nullable()->constrained('mm_plants')->onDelete('cascade');
                $table->string('code', 50);
                $table->string('name', 150);
                $table->string('symbol', 30);
                $table->string('dimension', 50)->nullable();
                $table->boolean('is_active')->default(true);
                $table->auditColumns();

                $table->index(['plant_id', 'is_active']);
                $table->index('code');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('qc_units');
    }
};
