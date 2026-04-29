<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('mm_users', function (Blueprint $table): void {
            if (!Schema::hasColumn('mm_users', 'api_key')) {
                $table->string('api_key', 80)->nullable()->unique()->after('password');
            }

            if (!Schema::hasColumn('mm_users', 'plan')) {
                $table->string('plan', 32)->default('free')->after('api_key');
            }
        });
    }

    public function down(): void
    {
        Schema::table('mm_users', function (Blueprint $table): void {
            if (Schema::hasColumn('mm_users', 'plan')) {
                $table->dropColumn('plan');
            }

            if (Schema::hasColumn('mm_users', 'api_key')) {
                $table->dropUnique('mm_users_api_key_unique');
                $table->dropColumn('api_key');
            }
        });
    }
};
