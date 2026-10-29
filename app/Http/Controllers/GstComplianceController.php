<?php

/*
Author: ragul-onemodo
Created: 2026-10-17 14:22:29 Asia/Calcutta (UTC+05:30)
*/

namespace App\Http\Controllers;

use App\Services\Gst\GstAccess;
use App\Services\Gst\GstData;
use App\Services\Gst\GstWorkspace;
use App\Services\Gst\TallyJournalExport;
use App\Services\PlantContextService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

final class GstComplianceController extends Controller
{
    public function __construct(private readonly PlantContextService $context, private readonly GstWorkspace $workspace, private readonly GstAccess $access) {}

    private function scope(Request $r, string $action): array
    {
        abort_unless($r->user(), 401);
        $plant = $this->context->requirePlantId();
        $permissions = $this->access->permissions($r->user(), $plant);
        abort_unless($permissions[$action] ?? false, 403, 'GST compliance permission required: '.$action);
        // A browser tab may still show the previous plant after a workspace switch.
        // The supplied ID asserts context; it never selects or authorizes a plant.
        $v = $r->validate(['plant_id' => 'sometimes|required|integer|min:1']);
        abort_if(isset($v['plant_id']) && (int) $v['plant_id'] !== $plant, 409,
            'The active plant has changed. Reload this page before continuing.');
        $period = GstData::period((string) $r->input('period', now()->format('Y-m')));

        return [$plant, $period, (int) $r->user()->id, $permissions];
    }

    public function index(Request $r): mixed
    {
        [$plant,$period,$actor,$permissions] = $this->scope($r, 'VIEW');

        return Inertia::render('GstCompliance/Index', ['workspace' => $this->workspace->view($plant, $period), 'permissions' => $permissions, 'actorId' => $actor]);
    }

    public function data(Request $r): mixed
    {
        [$p,$m] = $this->scope($r, 'VIEW');

        return response()->json($this->workspace->view($p, $m));
    }

    public function audit(Request $r): mixed
    {
        [$p,$m] = $this->scope($r, 'VIEW');

        return response()->json($this->workspace->verify($p, $m));
    }

    public function import(Request $r): mixed
    {
        [$p,$m,$u] = $this->scope($r, 'IMPORT');
        $r->validate(['file' => 'required|file|max:10240']);

        $preview = app(\App\Services\Gst\GstImportPreview::class)->inspect($p, $m, $r->file('file')->getContent());
        if (! $preview['valid']) throw \Illuminate\Validation\ValidationException::withMessages($preview['errors']);

        return response()->json($this->workspace->import($p, $m, $u, $r->file('file')->getContent()));
    }

    public function preview(Request $r, \App\Services\Gst\GstImportPreview $preview): mixed
    {
        [$p, $m] = $this->scope($r, 'IMPORT');
        $r->validate(['file' => 'required|file|max:10240']);
        return response()->json($preview->inspect($p, $m, $r->file('file')->getContent()));
    }

    public function classify(Request $r): mixed
    {
        [$p,$m,$u] = $this->scope($r, 'PREPARE');

        return response()->json($this->workspace->classify($p, $m, $u, $r->all()));
    }

    public function rule(Request $r): mixed
    {
        [$p,$m,$u] = $this->scope($r, 'PREPARE');

        return response()->json($this->workspace->rule($p, $m, $u, $r->all()));
    }

    public function prepare(Request $r): mixed
    {
        [$p,$m,$u] = $this->scope($r, 'PREPARE');

        return response()->json($this->workspace->prepare($p, $m, $u));
    }

    public function review(Request $r, string $type, int $id): mixed
    {
        [$p,$m,$u] = $this->scope($r, $type === 'inbound' ? 'TALLY_POST' : 'REVIEW');

        return response()->json($this->workspace->review($p, $m, $u, $type, $id, $r->all()));
    }

    public function inbound(Request $r): mixed
    {
        [$p,$m,$u] = $this->scope($r, 'TALLY_IMPORT');

        return response()->json($this->workspace->stageInbound($p, $m, $u, $r->all()));
    }

    public function export(Request $r, int $id): mixed
    {
        [$p,$m] = $this->scope($r, 'EXPORT');
        $s = $this->workspace->snapshot($p, $m, $id);

        return response()->streamDownload(fn () => print ($s->payload), 'gstr3b-plant-'.$p.'-'.$m.'-snapshot-'.$id.'.json', ['Content-Type' => 'application/json', 'X-Content-Type-Options' => 'nosniff']);
    }

    public function template(Request $r): mixed
    {
        [$p,$m] = $this->scope($r, 'IMPORT');
        $data = ['schema' => 'modormc.2b.v1', 'gstin' => DB::table('mm_plants')->where('id', $p)->value('gstin'), 'period' => $m, 'generated_at' => now()->utc()->toIso8601String(),
            'source_sha256' => str_repeat('0', 64), 'documents' => []];

        return response()->streamDownload(fn () => print (GstData::json($data)), '2b-template-'.$m.'.json', ['Content-Type' => 'application/json']);
    }

    public function tally(Request $r, TallyJournalExport $export): mixed
    {
        [$p,$m] = $this->scope($r, 'EXPORT');
        $v = $r->validate(['company' => 'required|string|max:200']);
        $xml = $export->build($p, $m, $v['company']);

        return response()->streamDownload(fn () => print ($xml), 'tally-journals-'.$p.'-'.$m.'.xml', ['Content-Type' => 'text/xml; charset=utf-8']);
    }
}
