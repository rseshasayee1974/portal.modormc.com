<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('mm_modules', function (Blueprint $table): void {
            $table->id();
            $table->string('name', 40)->unique();
            $table->decimal('price_per_1000_tokens', 10, 4)->default(0);
            $table->decimal('price_per_request', 10, 4)->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mm_modules');
    }
};
