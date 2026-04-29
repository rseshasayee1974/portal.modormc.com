<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('mm_plan_features', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('plan_id');
            $table->unsignedBigInteger('feature_id');
            $table->string('value', 100)->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['plan_id', 'feature_id']);
            $table->index(['feature_id', 'plan_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mm_plan_features');
    }
};
