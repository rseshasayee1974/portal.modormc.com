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
        Schema::table('mm_roles', function (Blueprint $table) {
            if (!Schema::hasColumn('mm_roles', 'code')) {
                // Add nullable code first
                $table->string('code')->nullable()->after('id');
            }
            if (!Schema::hasColumn('mm_roles', 'description')) {
                $table->text('description')->nullable()->after('name');
            }
            if (!Schema::hasColumn('mm_roles', 'is_system')) {
                $table->boolean('is_system')->default(false)->after('level');
            }
            if (!Schema::hasColumn('mm_roles', 'status')) {
                $table->enum('status', ['active', 'inactive'])->default('active')->after('is_system');
            }
        });

        // Populate code for existing roles to avoid unique constraint violation
        $roles = DB::table('mm_roles')->whereNull('code')->orWhere('code', '')->get();
        foreach ($roles as $role) {
            $code = strtoupper(Str::slug($role->name, '_'));
            // Ensure uniqueness
            $tempCode = $code;
            $counter = 1;
            while (DB::table('mm_roles')->where('code', $tempCode)->exists()) {
                $tempCode = $code . '_' . $counter++;
            }
            DB::table('mm_roles')->where('id', $role->id)->update(['code' => $tempCode]);
        }

        // Add unique constraint and index
        Schema::table('mm_roles', function (Blueprint $table) {
            $table->string('code')->change();
            // Only add unique index if it doesn't exist
            $indices = DB::select("SHOW INDEX FROM mm_roles WHERE Key_name = 'mm_roles_code_unique'");
            if (empty($indices)) {
                $table->unique('code', 'mm_roles_code_unique');
            }
            
            $indicesStatus = DB::select("SHOW INDEX FROM mm_roles WHERE Key_name = 'mm_roles_code_status_index'");
            if (empty($indicesStatus)) {
                 $table->index(['code', 'status'], 'mm_roles_code_status_index');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('mm_roles', function (Blueprint $table) {
            $table->dropColumn(['code', 'description', 'is_system', 'status']);
        });
    }
};
