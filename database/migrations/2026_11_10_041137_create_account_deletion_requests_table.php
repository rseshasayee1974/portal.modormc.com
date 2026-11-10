<?php

/*
 * Author: Cipher
 * Created: 2026-11-10 10:11:37 Asia/Calcutta (UTC+05:30)
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('account_deletion_requests', function (Blueprint $table) {
            $table->id();
            $table->string('app_name')->default('OneErpSuite');
            $table->string('account_email');
            $table->string('account_identifier')->nullable();
            $table->text('additional_details')->nullable();
            $table->string('status')->default('pending');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('account_deletion_requests');
    }
};
