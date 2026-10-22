<?php



use App\Models\EntityUser;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\PermissionRegistrar;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('mm_push_notification_logs', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('plant_id')->index();
            $table->unsignedBigInteger('actor_id')->nullable();
            $table->unsignedBigInteger('recipient_user_id')->nullable()->index();
            $table->string('subject_type');
            $table->unsignedBigInteger('subject_id');
            $table->string('operation', 20);
            $table->string('title');
            $table->json('payload');
            $table->string('status', 20)->default('pending')->index();
            $table->unsignedInteger('attempts')->default(0);
            $table->text('error')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->string('provider', 50)->nullable();
            $table->string('provider_message_id')->nullable()->index();
            $table->timestamp('last_attempt_at')->nullable();
            $table->timestamp('next_attempt_at')->nullable()->index();
            $table->timestamp('delivered_at')->nullable();
            $table->timestamp('read_at')->nullable();
            $table->auditColumns();
            $table->index(['subject_type', 'subject_id']);
        });
        if (Schema::hasTable('mm_permissions')) {
            DB::table('mm_permissions')->updateOrInsert(['name' => 'CRM_LEAD.APPROVE', 'guard_name' => 'web'], [
                'module' => 'CRM_LEAD', 'description' => 'Approve CRM leads', 'is_system' => true,
            ]);
            $permission = DB::table('mm_permissions')->where('name', 'CRM_LEAD.APPROVE')->where('guard_name', 'web')->value('id');
            foreach (DB::table('mm_roles')->whereNull('deleted_at')->whereIn('code', ['SAAS_OWNER', 'PLATFORM_ADMIN', 'SUPER_ADMIN', 'ADMINISTRATOR', 'SALES_MANAGER'])->pluck('id') as $role) {
                DB::table('mm_role_has_permissions')->insertOrIgnore(['role_id' => $role, 'permission_id' => $permission]);
            }
            app(PermissionRegistrar::class)->forgetCachedPermissions();
            EntityUser::clearGlobalRolesCache();
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('mm_push_notification_logs');
        // Preserve permission grants that may also be configured by administrators.
    }
};
