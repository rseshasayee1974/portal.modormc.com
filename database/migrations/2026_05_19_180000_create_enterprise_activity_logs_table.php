<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('mm_activity_log') && !Schema::hasTable('activity_logs')) {
            Schema::rename('mm_activity_log', 'activity_logs');
        }

        if (!Schema::hasTable('activity_logs')) {
            Schema::create('activity_logs', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->foreignId('user_id')->nullable()->constrained('mm_users')->nullOnDelete();
                $table->foreignId('plant_id')->nullable()->constrained('mm_plants')->nullOnDelete();
                $table->string('module_name', 125)->index();
                $table->string('entity_type', 125)->nullable()->index();
                $table->string('entity_id', 125)->nullable()->index();
                $table->string('action_type', 50)->index();
                $table->json('old_values')->nullable();
                $table->json('new_values')->nullable();
                $table->json('changed_fields')->nullable();
                $table->text('description')->nullable();
                $table->string('ip_address', 255)->nullable();
                $table->text('user_agent')->nullable();
                $table->string('device_type', 255)->nullable();
                $table->string('browser', 255)->nullable();
                $table->string('operating_system', 255)->nullable();
                $table->string('request_method', 20)->nullable();
                $table->text('request_url')->nullable();
                $table->string('route_name', 125)->nullable()->index();
                $table->integer('response_status')->nullable();
                $table->string('trace_id', 125)->nullable()->index();
                $table->json('metadata')->nullable();
                $table->timestamp('created_at')->nullable()->useCurrent();

                $table->index(['module_name', 'action_type'], 'activity_logs_module_action_idx');
                $table->index(['entity_type', 'entity_id'], 'activity_logs_entity_idx');
                $table->index(['user_id', 'created_at'], 'activity_logs_user_created_idx');
            });

            return;
        }

        Schema::table('activity_logs', function (Blueprint $table) {
            if (!Schema::hasColumn('activity_logs', 'user_id')) {
                $table->foreignId('user_id')->nullable()->after('id')->constrained('mm_users')->nullOnDelete();
            }
            if (!Schema::hasColumn('activity_logs', 'plant_id')) {
                $table->foreignId('plant_id')->nullable()->after('user_id')->constrained('mm_plants')->nullOnDelete();
            }
            if (!Schema::hasColumn('activity_logs', 'module_name')) {
                $table->string('module_name', 125)->nullable()->after('plant_id');
            }
            if (!Schema::hasColumn('activity_logs', 'entity_type')) {
                $table->string('entity_type', 125)->nullable()->after('module_name');
            }
            if (!Schema::hasColumn('activity_logs', 'entity_id')) {
                $table->string('entity_id', 125)->nullable()->after('entity_type');
            }
            if (!Schema::hasColumn('activity_logs', 'action_type')) {
                $table->string('action_type', 50)->nullable()->after('entity_id');
            }
            if (!Schema::hasColumn('activity_logs', 'old_values')) {
                $table->json('old_values')->nullable()->after('action_type');
            }
            if (!Schema::hasColumn('activity_logs', 'new_values')) {
                $table->json('new_values')->nullable()->after('old_values');
            }
            if (!Schema::hasColumn('activity_logs', 'changed_fields')) {
                $table->json('changed_fields')->nullable()->after('new_values');
            }
            if (!Schema::hasColumn('activity_logs', 'ip_address')) {
                $table->string('ip_address', 255)->nullable()->after('description');
            }
            if (!Schema::hasColumn('activity_logs', 'user_agent')) {
                $table->text('user_agent')->nullable()->after('ip_address');
            }
            if (!Schema::hasColumn('activity_logs', 'device_type')) {
                $table->string('device_type', 255)->nullable()->after('user_agent');
            }
            if (!Schema::hasColumn('activity_logs', 'browser')) {
                $table->string('browser', 255)->nullable()->after('device_type');
            }
            if (!Schema::hasColumn('activity_logs', 'operating_system')) {
                $table->string('operating_system', 255)->nullable()->after('browser');
            }
            if (!Schema::hasColumn('activity_logs', 'request_method')) {
                $table->string('request_method', 20)->nullable()->after('operating_system');
            }
            if (!Schema::hasColumn('activity_logs', 'request_url')) {
                $table->text('request_url')->nullable()->after('request_method');
            }
            if (!Schema::hasColumn('activity_logs', 'route_name')) {
                $table->string('route_name', 125)->nullable()->after('request_url');
            }
            if (!Schema::hasColumn('activity_logs', 'response_status')) {
                $table->integer('response_status')->nullable()->after('route_name');
            }
            if (!Schema::hasColumn('activity_logs', 'trace_id')) {
                $table->string('trace_id', 125)->nullable()->after('response_status');
            }
            if (!Schema::hasColumn('activity_logs', 'metadata')) {
                $table->json('metadata')->nullable()->after('trace_id');
            }
        });

        Schema::table('activity_logs', function (Blueprint $table) {
            $table->index(['module_name', 'action_type'], 'activity_logs_module_action_idx');
            $table->index(['entity_type', 'entity_id'], 'activity_logs_entity_idx');
            $table->index(['user_id', 'created_at'], 'activity_logs_user_created_idx');
            $table->index('route_name', 'activity_logs_route_name_idx');
            $table->index('trace_id', 'activity_logs_trace_id_idx');
        });

        DB::table('activity_logs')
            ->orderBy('id')
            ->chunkById(200, function ($rows) {
                foreach ($rows as $row) {
                    $legacyPayload = [];

                    if (isset($row->mm_properties) && $row->mm_properties) {
                        $decoded = json_decode((string) $row->mm_properties, true);
                        if (is_array($decoded)) {
                            $legacyPayload = $decoded;
                        }
                    }

                    $rawAction = (string) ($row->action_type ?? $row->event ?? $row->log_name ?? 'SYSTEM_EVENT');
                    $actionType = match (Str::upper($rawAction)) {
                        'CREATED' => 'CREATE',
                        'UPDATED' => 'UPDATE',
                        'DELETED', 'FORCEDELETED' => 'DELETE',
                        'RESTORED' => 'RESTORE',
                        'LOGIN' => 'LOGIN',
                        'LOGOUT' => 'LOGOUT',
                        'SUSPENSION', 'SUSPENDED_BY_ADMIN', 'SUSPENDED_BY_USER', 'ACTIVATED' => 'STATUS_CHANGE',
                        default => Str::upper(Str::snake($rawAction, '_')),
                    };

                    $moduleName = $row->module_name
                        ?? $row->log_name
                        ?? ($row->subject_type ? Str::snake(class_basename((string) $row->subject_type)) : 'system');

                    DB::table('activity_logs')
                        ->where('id', $row->id)
                        ->update([
                            'user_id' => $row->user_id ?? $row->causer_id ?? null,
                            'module_name' => $moduleName,
                            'entity_type' => $row->entity_type ?? $row->subject_type ?? null,
                            'entity_id' => $row->entity_id ?? (isset($row->subject_id) ? (string) $row->subject_id : null),
                            'action_type' => $actionType,
                            'trace_id' => $row->trace_id ?? $row->batch_uuid ?? null,
                            'metadata' => empty($legacyPayload) ? ($row->metadata ?? null) : json_encode($legacyPayload),
                        ]);
                }
            });

        DB::table('activity_logs')
            ->whereNull('module_name')
            ->update(['module_name' => 'system']);

        DB::table('activity_logs')
            ->whereNull('action_type')
            ->update(['action_type' => 'SYSTEM_EVENT']);
    }

    public function down(): void
    {
        if (Schema::hasTable('activity_logs')) {
            Schema::dropIfExists('activity_logs');
        }
    }
};
