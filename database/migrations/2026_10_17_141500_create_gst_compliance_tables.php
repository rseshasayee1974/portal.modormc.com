<?php

/*
Author: ragul-onemodo
Created: 2026-10-17 14:17:06 Asia/Calcutta (UTC+05:30)
*/

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! in_array(DB::getDriverName(), ['sqlite', 'mysql'], true)) {
            throw new RuntimeException('Port the GST append-only triggers before using this database driver.');
        }
        Schema::create('mm_gst_periods', function (Blueprint $t) {
            $t->engine = 'InnoDB';
            $t->id();
            $t->unsignedBigInteger('plant_id');
            $t->string('period', 7);
            $t->string('gstin', 15);
            $t->unsignedBigInteger('revision')->default(0);
            $t->string('audit_head', 64)->default('');
            $t->unsignedBigInteger('audit_sequence')->default(0);
            $t->unique(['plant_id', 'period']);
        });
        foreach (['imports', 'rules', 'classifications', 'reviews', 'snapshots', 'inbound', 'events'] as $name) {
            Schema::create('mm_gst_'.$name, function (Blueprint $t) use ($name) {
                $t->engine = 'InnoDB';
                $t->id();
                $t->foreignId('period_id')->constrained('mm_gst_periods');
                $t->unsignedBigInteger('created_by');
                $t->timestamp('created_at');
                $t->longText('payload');
                $t->string('digest', 64);
                if ($name === 'imports') {
                    $t->longText('raw');
                    $t->unique(['period_id', 'digest']);
                }
                if ($name === 'rules') {
                    $t->string('version', 100);
                    $t->unique(['period_id', 'version']);
                }
                if ($name === 'classifications') {
                    $t->string('source_key', 120);
                    $t->string('source_hash', 64);
                    $t->index(['period_id', 'source_key']);
                }
                if ($name === 'reviews') {
                    $t->string('target_type', 20);
                    $t->unsignedBigInteger('target_id');
                    $t->string('decision', 20);
                    $t->index(['period_id', 'target_type', 'target_id']);
                }
                if ($name === 'snapshots') {
                    $t->string('inputs_hash', 64);
                }
                if ($name === 'inbound') {
                    $t->unsignedBigInteger('plant_id');
                    $t->string('remote_guid', 128);
                    $t->unique(['plant_id', 'remote_guid', 'digest']);
                }
                if ($name === 'events') {
                    $t->unsignedBigInteger('sequence');
                    $t->string('previous', 64);
                    $t->unique(['period_id', 'sequence']);
                }
            });
            foreach (['UPDATE', 'DELETE'] as $op) {
                $table = 'mm_gst_'.$name;
                $trigger = $table.'_no_'.strtolower($op);
                $sql = DB::getDriverName() === 'sqlite'
                    ? "CREATE TRIGGER {$trigger} BEFORE {$op} ON {$table} BEGIN SELECT RAISE(ABORT, 'Append-only GST evidence'); END"
                    : "CREATE TRIGGER {$trigger} BEFORE {$op} ON {$table} FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Append-only GST evidence'";
                DB::unprepared($sql);
            }
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('mm_gst_periods') && DB::table('mm_gst_periods')->exists()) {
            throw new RuntimeException('GST evidence exists. Preserve the tables when reverting application code.');
        }
        foreach (['events', 'inbound', 'snapshots', 'reviews', 'classifications', 'rules', 'imports'] as $name) {
            Schema::dropIfExists('mm_gst_'.$name);
        }
        Schema::dropIfExists('mm_gst_periods');
    }
};
