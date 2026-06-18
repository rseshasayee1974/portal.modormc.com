<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasColumn('mm_personnels', 'contact_id')) {
            Schema::table('mm_personnels', function (Blueprint $table) {
                $table->foreignId('contact_id')->nullable()->after('plant_id')->constrained('mm_contacts')->nullOnDelete();
            });
        }

        if (!Schema::hasColumn('mm_personnels', 'department_id')) {
            Schema::table('mm_personnels', function (Blueprint $table) {
                $table->foreignId('department_id')->nullable()->after('contact_id')->constrained('mm_departments')->nullOnDelete();
            });
        }

        if (!Schema::hasColumn('mm_personnels', 'designation_id')) {
            Schema::table('mm_personnels', function (Blueprint $table) {
                $table->foreignId('designation_id')->nullable()->after('department_id')->constrained('mm_designations')->nullOnDelete();
            });
        }

        if (!Schema::hasColumn('mm_personnels', 'reporting_manager_id')) {
            Schema::table('mm_personnels', function (Blueprint $table) {
                $table->foreignId('reporting_manager_id')->nullable()->after('designation_id')->constrained('mm_personnels')->nullOnDelete();
            });
        }

        if (!Schema::hasColumn('mm_personnels', 'employee_code')) {
            Schema::table('mm_personnels', function (Blueprint $table) {
                $table->string('employee_code')->nullable()->unique()->after('reporting_manager_id');
            });
        }
        
        // Drop old employee_type column if it exists and create employment_type enum
        if (Schema::hasColumn('mm_personnels', 'employee_type')) {
            Schema::table('mm_personnels', function (Blueprint $table) {
                $table->dropColumn('employee_type');
            });
        }

        if (!Schema::hasColumn('mm_personnels', 'employment_type')) {
            Schema::table('mm_personnels', function (Blueprint $table) {
                $table->enum('employment_type', ['permanent', 'contract', 'trainee', 'temporary', 'consultant'])->default('permanent')->after('last_name');
            });
        }

        // Drop old gender if it exists and recreate as enum
        if (Schema::hasColumn('mm_personnels', 'gender')) {
            Schema::table('mm_personnels', function (Blueprint $table) {
                $table->dropColumn('gender');
            });
        }

        if (!Schema::hasColumn('mm_personnels', 'gender')) {
            Schema::table('mm_personnels', function (Blueprint $table) {
                $table->enum('gender', ['male', 'female', 'other'])->nullable()->after('employment_type');
            });
        }

        // Drop old status if it exists and recreate as enum
        if (Schema::hasColumn('mm_personnels', 'status')) {
            Schema::table('mm_personnels', function (Blueprint $table) {
                $table->dropColumn('status');
            });
        }

        if (!Schema::hasColumn('mm_personnels', 'status')) {
            Schema::table('mm_personnels', function (Blueprint $table) {
                $table->enum('status', ['active', 'inactive', 'terminated', 'resigned', 'retired'])->default('active')->after('gender');
            });
        }

        if (!Schema::hasColumn('mm_personnels', 'full_name')) {
            Schema::table('mm_personnels', function (Blueprint $table) {
                $isSqlite = \Illuminate\Support\Facades\DB::connection()->getDriverName() === 'sqlite';
                if ($isSqlite) {
                    $table->string('full_name')->virtualAs("first_name || ' ' || last_name")->after('last_name');
                } else {
                    $table->string('full_name')->virtualAs("concat_ws(' ', first_name, last_name)")->after('last_name');
                }
            });
        }

        if (!Schema::hasColumn('mm_personnels', 'email')) {
            Schema::table('mm_personnels', function (Blueprint $table) {
                $table->string('email')->unique()->nullable()->after('full_name');
            });
        }

        if (!Schema::hasColumn('mm_personnels', 'mobile')) {
            Schema::table('mm_personnels', function (Blueprint $table) {
                $table->string('mobile')->nullable()->after('email');
            });
        }

        if (!Schema::hasColumn('mm_personnels', 'exit_date')) {
            Schema::table('mm_personnels', function (Blueprint $table) {
                $table->date('exit_date')->nullable()->after('joining_date');
            });
        }

        if (!Schema::hasColumn('mm_personnels', 'pan')) {
            Schema::table('mm_personnels', function (Blueprint $table) {
                $table->string('pan')->nullable()->unique()->after('exit_date');
            });
        }

        if (!Schema::hasColumn('mm_personnels', 'aadhaar')) {
            Schema::table('mm_personnels', function (Blueprint $table) {
                $table->string('aadhaar')->nullable()->unique()->after('pan');
            });
        }

        if (!Schema::hasColumn('mm_personnels', 'uan')) {
            Schema::table('mm_personnels', function (Blueprint $table) {
                $table->string('uan')->nullable()->after('aadhaar');
            });
        }

        if (!Schema::hasColumn('mm_personnels', 'esi_number')) {
            Schema::table('mm_personnels', function (Blueprint $table) {
                $table->string('esi_number')->nullable()->after('uan');
            });
        }

        if (!Schema::hasColumn('mm_personnels', 'bank_account_no')) {
            Schema::table('mm_personnels', function (Blueprint $table) {
                $table->string('bank_account_no')->nullable()->after('esi_number');
            });
        }

        if (!Schema::hasColumn('mm_personnels', 'bank_ifsc')) {
            Schema::table('mm_personnels', function (Blueprint $table) {
                $table->string('bank_ifsc')->nullable()->after('bank_account_no');
            });
        }

        if (!Schema::hasColumn('mm_personnels', 'bank_name')) {
            Schema::table('mm_personnels', function (Blueprint $table) {
                $table->string('bank_name')->nullable()->after('bank_ifsc');
            });
        }

        if (!Schema::hasColumn('mm_personnels', 'photo')) {
            Schema::table('mm_personnels', function (Blueprint $table) {
                $table->string('photo')->nullable()->after('bank_name');
            });
        }

        if (!Schema::hasColumn('mm_personnels', 'meta')) {
            Schema::table('mm_personnels', function (Blueprint $table) {
                $table->json('meta')->nullable()->after('photo');
            });
        }
    }

    public function down(): void
    {
        Schema::table('mm_personnels', function (Blueprint $table) {
            $table->dropForeign(['contact_id']);
            $table->dropForeign(['department_id']);
            $table->dropForeign(['designation_id']);
            $table->dropForeign(['reporting_manager_id']);

            $table->dropColumn([
                'contact_id',
                'department_id',
                'designation_id',
                'reporting_manager_id',
                'employee_code',
                'full_name',
                'email',
                'mobile',
                'exit_date',
                'employment_type',
                'gender',
                'status',
                'pan',
                'aadhaar',
                'uan',
                'esi_number',
                'bank_account_no',
                'bank_ifsc',
                'bank_name',
                'photo',
                'meta'
            ]);

            // Restore original columns
            $table->string('employee_type')->nullable();
            $table->string('gender')->nullable();
            $table->string('status')->default('active');
        });
    }
};
