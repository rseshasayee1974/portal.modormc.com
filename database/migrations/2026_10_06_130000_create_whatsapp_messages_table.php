<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('mm_whatsapp_messages')) {
            Schema::create('mm_whatsapp_messages', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('entity_id')->nullable()->index();
                $table->unsignedBigInteger('plant_id')->nullable()->index();
                $table->string('category', 100);
                $table->string('origin', 100);
                $table->unsignedBigInteger('origin_id');
                $table->string('provider', 50)->default('tendigit');
                $table->string('contact', 20);
                $table->string('template', 150);
                $table->json('parameters')->nullable();
                $table->string('status', 30)->default('pending')->index();
                $table->unsignedSmallInteger('response_status')->nullable();
                $table->longText('provider_response')->nullable();
                $table->text('error_message')->nullable();
                $table->timestamp('submitted_at')->nullable();
                $table->unsignedBigInteger('created_by')->nullable();
                $table->unsignedBigInteger('updated_by')->nullable();
                $table->timestamps();
                $table->index(['origin', 'origin_id', 'category'], 'whatsapp_origin_category_index');
            });
        }

        // Preserve previous submissions before removing the old dispatch-only fields.
        if (!Schema::hasTable('mm_dispatches')) return;
        if (Schema::hasColumns('mm_dispatches', ['whatsapp_submitted_at', 'whatsapp_contact', 'whatsapp_template'])) {
            DB::table('mm_dispatches')->whereNotNull('whatsapp_submitted_at')->orderBy('id')->chunkById(200, function ($rows) {
                foreach ($rows as $row) {
                    if (DB::table('mm_whatsapp_messages')->where('origin', 'dispatch')->where('origin_id', $row->id)
                        ->where('category', 'dispatch_details')->where('status', 'submitted')->exists()) continue;
                    DB::table('mm_whatsapp_messages')->insert([
                        'entity_id' => $row->entity_id ?? null, 'plant_id' => $row->plant_id ?? null,
                        'category' => 'dispatch_details', 'origin' => 'dispatch', 'origin_id' => $row->id,
                        'provider' => 'tendigit', 'contact' => $row->whatsapp_contact ?? '',
                        'template' => $row->whatsapp_template ?? 'modormc_dispatch', 'status' => 'submitted',
                        'submitted_at' => $row->whatsapp_submitted_at,
                        'created_at' => $row->whatsapp_submitted_at, 'updated_at' => $row->whatsapp_submitted_at,
                    ]);
                }
            });
        }
        $columns = array_values(array_filter(['whatsapp_submitted_at', 'whatsapp_contact', 'whatsapp_template'],
            fn ($column) => Schema::hasColumn('mm_dispatches', $column)));
        if ($columns) Schema::table('mm_dispatches', fn (Blueprint $table) => $table->dropColumn($columns));
    }

    public function down(): void
    {
        Schema::dropIfExists('mm_whatsapp_messages');
    }
};
