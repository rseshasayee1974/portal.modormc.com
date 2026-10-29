<?php

/*
Author: ragul-onemodo
Created: 2026-10-18 21:20:38 Asia/Calcutta (UTC+05:30)
*/

namespace App\Services\Gst;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpException;

final class GstImportPreview
{
    public function inspect(int $plant, string $period, string $raw): array
    {
        abort_if(strlen($raw) > 10 * 1024 * 1024, 413);
        GstData::period($period);
        try {
            $input = json_decode($raw, true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            return ['valid' => false, 'errors' => ['file' => ['Invalid JSON']], 'rows' => [], 'duplicate' => false];
        }
        $validator = Validator::make(is_array($input) ? $input : [], [
            'schema' => 'required|in:modormc.2b.v1', 'gstin' => 'required|string|size:15', 'period' => 'required|in:'.$period,
            'source_sha256' => ['required', 'regex:/^[a-f0-9]{64}$/', 'not_in:'.str_repeat('0', 64)],
            'generated_at' => 'required|date', 'documents' => 'present|array|list|max:20000',
        ]);
        $errors = $validator->errors()->toArray();
        $rows = [];
        $seen = [];
        $duplicates = [];
        $warnings = [];
        if (($input['gstin'] ?? '') !== DB::table('mm_plants')->where('id', $plant)->value('gstin')) {
            $errors['gstin'][] = 'Recipient GSTIN does not match the active plant';
        }
        $totals = array_fill_keys(GstData::HEADS, 0);
        if (is_array($input['documents'] ?? null) && array_is_list($input['documents']) && count($input['documents']) <= 20000) {
            foreach ($input['documents'] as $i => $row) {
                try {
                    abort_unless(is_array($row), 422, 'Document must be an object');
                    $v = GstData::invoice($row);
                    if ($v['amounts']['igst'] !== 0 && ($v['amounts']['cgst'] !== 0 || $v['amounts']['sgst'] !== 0)) {
                        $warnings[] = 'Row '.($i + 1).': mixed tax heads; verify line-level source amounts and place-of-supply treatment';
                    }
                    $identity = GstData::hash([$v['supplier_gstin'], $v['number'], $v['date'], $v['kind']]);
                    if (isset($seen[$identity])) {
                        $duplicates[] = ['row' => $i + 1, 'duplicate_of' => $seen[$identity]];
                    } else {
                        $seen[$identity] = $i + 1;
                    }
                    foreach ($totals as $h => $_) {
                        $totals[$h] += $v['amounts'][$h];
                    }
                    $rows[] = ['row' => $i + 1, 'number' => $v['number'], 'supplier_gstin' => $v['supplier_gstin'], 'amounts' => $v['amounts']];
                } catch (ValidationException $e) {
                    foreach ($e->errors() as $field => $messages) {
                        $errors['documents.'.$i.'.'.$field] = array_map(fn ($m) => 'Row '.($i + 1).': '.$m, $messages);
                    }
                } catch (HttpException $e) {
                    $errors['documents.'.$i] = ['Row '.($i + 1).': '.$e->getMessage()];
                }
            }
        }
        $duplicate = DB::table('mm_gst_imports as i')->join('mm_gst_periods as p', 'p.id', '=', 'i.period_id')->where('p.plant_id', $plant)->where('p.period', $period)->where('i.digest', hash('sha256', $raw))->exists();

        return ['valid' => ! $errors, 'errors' => $errors, 'warnings' => $warnings, 'rows' => array_slice($rows, 0, 200), 'document_count' => count($rows), 'preview_limit' => 200, 'duplicate_rows' => $duplicates, 'duplicate' => $duplicate, 'totals' => $totals, 'sha256' => hash('sha256', $raw)];
    }
}
