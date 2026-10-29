<?php

/*
Author: ragul-onemodo
Created: 2026-10-18 21:17:17 Asia/Calcutta (UTC+05:30)
*/

namespace App\Services\Finance;

use App\Services\Gst\TallyJournalExport;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

final class AccountingTally
{
    public function __construct(private readonly AccountingWorkspace $workspace, private readonly TallyJournalExport $xml) {}

    public function configure(int $plant, int $actor, array $input): void
    {
        $v = Validator::make($input, ['tally_enabled' => 'required|boolean', 'tally_company' => 'required|string|max:200'])->validate();
        $this->workspace->write($plant, function () use ($plant, $actor, $v) {
            $previous = DB::table('mm_accounting_settings')->where('plant_id', $plant)->first();
            abort_if($previous && $previous->tally_company !== $v['tally_company'] && DB::table('mm_accounting_tally')->where('plant_id', $plant)->exists(), 409, 'Company cannot change after queue history exists');
            DB::table('mm_accounting_settings')->updateOrInsert(['plant_id' => $plant], $v);
            $this->workspace->event($plant, $actor, 'tally.configured', $v);
        });
    }

    public function connection(int $plant, int $actor): array
    {
        $result = ['status' => 'unconfigured', 'company_verified' => false];
        if (config('gst_compliance.tally_url')) {
            try {
                $r = Http::timeout(5)->connectTimeout(3)->withOptions(['allow_redirects' => false])->get(config('gst_compliance.tally_url'));
                $result['status'] = $r->successful() ? 'listener_reachable' : 'listener_error';
                $result['http_status'] = $r->status();
            } catch (\Throwable) {
                $result['status'] = 'unreachable';
            }
        }
        $this->workspace->write($plant, fn () => $this->workspace->event($plant, $actor, 'tally.connection_checked', $result));

        return $result;
    }

    public function enqueue(int $plant, int $actor, int $journal): object
    {
        return $this->workspace->write($plant, function () use ($plant, $actor, $journal) {
            $settings = DB::table('mm_accounting_settings')->where('plant_id', $plant)->first();
            abort_unless($settings?->tally_enabled, 409, 'Enable and configure Tally for this plant first');
            $j = $this->workspace->entry($plant, $journal);
            abort_unless(strtoupper($j->is_status) === 'POSTED' && $j->ref_module !== 'tally_import', 409, 'Only posted outbound journals can be queued');
            $xml = $this->xml->build($plant, substr($j->voucher_date, 0, 7), $settings->tally_company, $journal);
            $digest = hash('sha256', $xml);
            $old = DB::table('mm_accounting_tally')->where(['plant_id' => $plant, 'journal_id' => $journal])->first();
            if ($old) {
                abort_unless(hash_equals($old->digest, $digest), 409, 'Journal changed since export; reconcile and reverse instead of overwriting Tally');

                return $old;
            }
            $id = DB::table('mm_accounting_tally')->insertGetId(['plant_id' => $plant, 'journal_id' => $journal, 'company' => $settings->tally_company, 'guid' => $this->xml->guid($plant, $journal), 'xml' => $xml, 'digest' => $digest, 'created_at' => now(), 'updated_at' => now()]);
            $this->workspace->event($plant, $actor, 'tally.queued', ['id' => $id, 'journal_id' => $journal, 'digest' => $digest]);

            return DB::table('mm_accounting_tally')->find($id);
        });
    }

    public function send(int $plant, int $actor, int $id): object
    {
        $job = $this->workspace->write($plant, function () use ($plant, $actor, $id) {
            $j = DB::table('mm_accounting_tally')->where('plant_id', $plant)->find($id);
            abort_unless($j, 404);
            if ($j->status === 'confirmed') {
                return $j;
            }
            abort_unless($j->status === 'pending', 409, 'Delivery needs reconciliation before retry');
            abort_unless(DB::table('mm_accounting_settings')->where('plant_id', $plant)->value('tally_enabled'), 409, 'Tally is disabled');
            abort_unless(config('gst_compliance.tally_url'), 409, 'Configure GST_TALLY_URL on the host that can reach the private Tally listener');
            DB::table('mm_accounting_tally')->where('id', $id)->update(['status' => 'sending', 'lease' => (string) Str::uuid(), 'leased_at' => now(), 'attempts' => $j->attempts + 1, 'updated_at' => now()]);
            $this->workspace->event($plant, $actor, 'tally.sending', ['id' => $id]);

            return DB::table('mm_accounting_tally')->find($id);
        });
        if ($job->status === 'confirmed') {
            return $job;
        }
        // No automatic HTTP retries: a timeout may have created a remote voucher.
        try {
            $response = Http::timeout(30)->connectTimeout(5)->withOptions(['allow_redirects' => false])->withBody($job->xml, 'text/xml')->post(config('gst_compliance.tally_url'));
            $raw = substr($response->body(), 0, 1048577);
            $status = $response->successful() ? $this->xml->outcome($raw) : 'uncertain';
            $error = $status === 'confirmed' ? null : 'HTTP '.$response->status().'; inspect the retained response and target voucher before retry';
        } catch (\Throwable $e) {
            $raw = '';
            $status = 'uncertain';
            $error = 'Transport failed; remote delivery is uncertain. Verify the target GUID before retry.';
        }

        return $this->workspace->write($plant, function () use ($plant, $actor, $id, $job, $status, $raw, $error) {
            $current = DB::table('mm_accounting_tally')->where('plant_id', $plant)->find($id);
            abort_unless($current->status === 'sending' && hash_equals($current->lease, $job->lease), 409, 'Delivery lease changed; reconcile manually');
            DB::table('mm_accounting_tally')->where('id', $id)->update(['status' => $status, 'last_error' => $error, 'updated_at' => now()]);
            $this->workspace->event($plant, $actor, 'tally.receipt', ['id' => $id, 'status' => $status, 'response_base64' => base64_encode($raw), 'response_sha256' => hash('sha256', $raw), 'guid' => $job->guid, 'digest' => $job->digest]);

            return DB::table('mm_accounting_tally')->find($id);
        });
    }

    public function resolve(int $plant, int $actor, int $id, array $input): object
    {
        $v = Validator::make($input, ['action' => 'required|in:confirmed,retry_absent', 'reason' => 'required|string|min:20|max:4000'])->validate();

        return $this->workspace->write($plant, function () use ($plant, $actor, $id, $v) {
            $j = DB::table('mm_accounting_tally')->where('plant_id', $plant)->find($id);
            abort_unless($j, 404);
            abort_unless(in_array($j->status, ['failed', 'uncertain']) || ($j->status === 'sending' && now()->diffInSeconds(Carbon::parse($j->leased_at), true) > 300), 409, 'Job is not awaiting reconciliation');
            DB::table('mm_accounting_tally')->where('id', $id)->update(['status' => $v['action'] === 'confirmed' ? 'confirmed' : 'pending', 'lease' => null, 'last_error' => null, 'updated_at' => now()]);
            $this->workspace->event($plant, $actor, 'tally.resolved', ['id' => $id] + $v);

            return DB::table('mm_accounting_tally')->find($id);
        });
    }
}
