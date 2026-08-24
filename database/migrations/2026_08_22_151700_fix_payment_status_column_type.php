<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() !== 'sqlite') {
            DB::statement("ALTER TABLE mm_payments MODIFY COLUMN status VARCHAR(30) DEFAULT 'completed'");
        }
    }

    public function down(): void
    {
        if (DB::getDriverName() !== 'sqlite') {
            DB::statement("ALTER TABLE mm_payments MODIFY COLUMN status ENUM('pending', 'paid', 'completed', 'failed') DEFAULT 'pending'");
        }
    }
};
