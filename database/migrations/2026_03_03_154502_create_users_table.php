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
        Schema::create('mm_users', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('email', 191);
            $table->string('username', 191);
            $table->string('password', 191);
            $table->text('two_factor_secret')->nullable();
            $table->text('two_factor_recovery_codes')->nullable();
            $table->timestamp('two_factor_confirmed_at')->nullable();
            $table->rememberToken();
            $table->timestamp('email_verified_at')->nullable();
            $table->tinyInteger('is_active')->default(0);
            $table->tinyInteger('is_otp_enabled')->default(0);
            $table->integer('failed_attempts')->default(0);
            $table->timestamp('last_login')->nullable();
            $table->string('last_visit_page', 191)->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->timestamp('lockout_until')->nullable();
            $table->string('otp_secret', 191)->nullable();
            $table->string('profile_photo_path', 2048)->nullable();
            $table->unsignedBigInteger('created_by')->nullable()->index('mm_users_created_by_foreign');
            $table->unsignedBigInteger('updated_by')->nullable()->index('mm_users_updated_by_foreign');
            $table->unsignedBigInteger('deleted_by')->nullable()->index('mm_users_deleted_by_foreign');
            $table->timestamps();
            $table->softDeletes();

            $table->index(['is_active', 'email_verified_at', 'lockout_until']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('mm_users');
    }
};
