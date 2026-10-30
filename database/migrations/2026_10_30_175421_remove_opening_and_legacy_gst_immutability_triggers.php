<?php
/*
Author: ragul-onemodo
Created: 2026-10-30 17:54:38 Asia/Calcutta (UTC+05:30)
*/

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        if (!in_array(DB::connection()->getDriverName(), ['mysql', 'mariadb'], true)) {
            return;
        }

        $tables = [
            'mm_opening_balance_batches', 'mm_opening_balance_audit_logs',
            'mm_journal_entries', 'mm_journal_entry_lines',
            'mm_gst_imports', 'mm_gst_rules', 'mm_gst_classifications',
            'mm_gst_reviews', 'mm_gst_snapshots', 'mm_gst_inbound',
            'mm_gst_events', 'mm_gst_periods',
        ];
        $triggers = DB::table('information_schema.TRIGGERS')
            ->where('TRIGGER_SCHEMA', DB::connection()->getDatabaseName())
            ->whereIn('EVENT_OBJECT_TABLE', $tables)
            ->whereIn('EVENT_MANIPULATION', ['UPDATE', 'DELETE'])
            ->get(['TRIGGER_NAME', 'ACTION_STATEMENT']);

        foreach ($triggers as $trigger) {
            $body = strtolower($trigger->ACTION_STATEMENT);
            // Remove only explicit immutability blocks, preserving other triggers.
            if (!str_contains($body, 'signal') || !preg_match('/immutable|append.only|cannot be (changed|deleted)|opening entries are locked/', $body)) {
                continue;
            }
            $name = str_replace('`', '``', $trigger->TRIGGER_NAME);
            DB::unprepared('DROP TRIGGER IF EXISTS `'.$name.'`');
        }
    }

    public function down(): void
    {
        // Removed legacy restrictions must not be reintroduced on rollback.
    }
};
