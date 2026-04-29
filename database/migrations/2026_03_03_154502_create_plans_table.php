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
        Schema::create('mm_plans', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('plan_type', 191)->unique();
            $table->decimal('price_monthly', 17);
            $table->longText('monthly_plan_description')->nullable();
            $table->decimal('price_yearly', 17);
            $table->longText('yearly_plan_description')->nullable();
            $table->integer('max_users')->default(0);
            $table->json('mm_features_json')->nullable();
            $table->tinyInteger('is_active')->default(0);
             $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('mm_plans');
    }
};
