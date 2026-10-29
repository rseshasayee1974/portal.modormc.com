<?php

/*
Author: ragul-onemodo
Created: 2026-10-17 14:17:06 Asia/Calcutta (UTC+05:30)
*/

namespace App\Services\Gst;

final class GstMatcher
{
    private function key(array $a): string
    {
        $fy = (int) substr($a['date'], 0, 4) - ((int) substr($a['date'], 5, 2) < 4 ? 1 : 0);

        return $a['supplier_gstin'].'|'.$a['kind'].'|'.$fy.'|'.preg_replace('/[^A-Z0-9]/', '', strtoupper($a['number']));
    }

    public function match(array $books, array $statement, int $tolerance = 100): array
    {
        $index = [];
        $counts = [];
        $out = [];
        foreach ($statement as $i => $r) {
            $index[$this->key($r)][] = $i;
        }
        foreach ($books as $r) {
            $key = $this->key($r);
            $counts[$key] = ($counts[$key] ?? 0) + 1;
        }
        foreach ($books as $r) {
            $key = $this->key($r);
            $ids = $index[$key] ?? [];
            $status = 'missing_in_2b';
            $delta = [];
            if (count($ids) > 1 || (count($ids) === 1 && $counts[$key] > 1)) {
                $status = 'ambiguous';
            } elseif (count($ids) === 1) {
                $s = $statement[$ids[0]];
                foreach (GstData::HEADS as $h) {
                    $delta[$h] = $r['amounts'][$h] - $s['amounts'][$h];
                }
                $max = max(array_map('abs', $delta));
                $status = match (true) {
                    $r['number'] !== $s['number'] => 'number_variant', $r['date'] !== $s['date'] => 'date_mismatch',
                    ($r['reverse_charge'] ?? null) !== $s['reverse_charge'] && ($r['reverse_charge'] ?? null) !== null => 'rcm_mismatch',
                    $max > $tolerance => 'amount_mismatch', $max > 0 => 'tolerance', default => 'exact',
                };
                if (! $s['itc_available'] || in_array($s['ims'], ['pending', 'rejected'], true)) {
                    $status = 'itc_review_required';
                }
            }
            $out[] = ['source_key' => $r['source_key'], 'number' => $r['number'], 'status' => $status, 'candidate_count' => count($ids), 'statement_indices' => array_slice($ids, 0, 100), 'delta' => $delta];
        }
        foreach ($statement as $i => $r) {
            if (! isset($counts[$this->key($r)])) {
                $out[] = ['source_key' => null, 'number' => $r['number'], 'status' => 'missing_in_books', 'statement_indices' => [$i], 'candidate_count' => 0, 'delta' => []];
            }
        }

        return $out;
    }
}
