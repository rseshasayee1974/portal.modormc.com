<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('mm_opening_balance_audit_logs', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('plant_id');
            $table->unsignedBigInteger('batch_id');
            $table->unsignedInteger('version');
            $table->string('action', 20);
            $table->unsignedBigInteger('user_id');
            $table->string('actor_name')->nullable();
            $table->text('reason')->nullable();
            $table->json('before_values')->nullable();
            $table->json('after_values');
            $table->json('journal')->nullable();
            $table->timestamp('created_at');
            $table->unique(['batch_id', 'version']);
            $table->index(['plant_id', 'batch_id', 'id']);
        });
    }

    public function down(): void { Schema::dropIfExists('mm_opening_balance_audit_logs'); }
};
