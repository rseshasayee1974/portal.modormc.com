<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('notification_emails', function (Blueprint $table) {
            $table->id();
            $table->foreignId('plant_id')->constrained('mm_plants')->onDelete('cascade');
            $table->string('type', 40);
            $table->string('role_name', 40);
            $table->string('email', 100);
            $table->tinyInteger('status')->default(1);
            $table->timestamps(); // created_at, updated_at
            $table->foreignId('created_by')->nullable()->constrained('mm_users')->onDelete('set null');
            $table->foreignId('updated_by')->nullable()->constrained('mm_users')->onDelete('set null');
            $table->softDeletes(); // deleted_at
            $table->foreignId('deleted_by')->nullable()->constrained('mm_users')->onDelete('set null');
            $table->tinyInteger('deleted')->default(0);
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('notification_emails');
    }
};
