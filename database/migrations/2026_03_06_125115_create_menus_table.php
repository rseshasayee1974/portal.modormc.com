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
        Schema::create('mm_menus', function (Blueprint $table) {
            $table->id();
            $table->integer('menutype')->comment('1: TopNav, 2: SideNav');
            $table->string('title');
            $table->string('alias');
            $table->string('link')->default('#');
            $table->string('icon')->nullable();
            $table->boolean('published')->default(true);
            $table->unsignedBigInteger('parent_id')->default(0);
            $table->integer('level')->default(0);
            $table->integer('ordering')->default(0);
            $table->string('permission_name')->nullable();
            $table->unsignedBigInteger('entity_id')->nullable();
            
            $table->foreign('entity_id')->references('id')->on('mm_entities')->onDelete('cascade');
            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('mm_menus');
    }
};
