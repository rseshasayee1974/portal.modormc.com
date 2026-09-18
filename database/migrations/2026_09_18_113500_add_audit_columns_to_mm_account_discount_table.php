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
        if (Schema::hasTable('mm_account_discount')) {
            Schema::table('mm_account_discount', function (Blueprint $table) {
                if (!Schema::hasColumn('mm_account_discount', 'created_at')) {
                    $table->timestamp('created_at')->nullable();
                }
                if (!Schema::hasColumn('mm_account_discount', 'created_by')) {
                    $table->unsignedBigInteger('created_by')->nullable();
                }
                if (!Schema::hasColumn('mm_account_discount', 'updated_at')) {
                    $table->timestamp('updated_at')->nullable();
                }
                if (!Schema::hasColumn('mm_account_discount', 'updated_by')) {
                    $table->unsignedBigInteger('updated_by')->nullable();
                }
                if (!Schema::hasColumn('mm_account_discount', 'deleted_at')) {
                    $table->softDeletes();
                }
                if (!Schema::hasColumn('mm_account_discount', 'deleted_by')) {
                    $table->unsignedBigInteger('deleted_by')->nullable();
                }
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasTable('mm_account_discount')) {
            Schema::table('mm_account_discount', function (Blueprint $table) {
                $columns = ['updated_at', 'updated_by', 'deleted_at', 'deleted_by'];
                foreach ($columns as $col) {
                    if (Schema::hasColumn('mm_account_discount', $col)) {
                        $table->dropColumn($col);
                    }
                }
            });
        }
    }
};
