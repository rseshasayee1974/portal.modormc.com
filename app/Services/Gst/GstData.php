<?php

/*
Author: ragul-onemodo
Created: 2026-10-17 14:17:06 Asia/Calcutta (UTC+05:30)
*/

namespace App\Services\Gst;

use Illuminate\Support\Facades\Validator;

final class GstData
{
    public const HEADS = ['taxable', 'igst', 'cgst', 'sgst', 'cess'];

    public const ROWS = ['3.1.a', '3.1.b', '3.1.c', '3.1.d', '3.1.e', '3.1.1.i', '3.1.1.ii', '4A1', '4A2', '4A3', '4A4', '4A5', '4B1', '4B2', '4D1', '4D2', '5.exempt.inter', '5.exempt.intra', '5.non_gst.inter', '5.non_gst.intra', '5.1.interest', '5.1.late_fee'];

    public const CHECKS = [
        'books_complete' => 'All invoices, bills, notes and adjustments are complete',
        'gstr1_compared' => 'Outward supplies agree with GSTR-1/1A and portal evidence',
        'latest_ims_2b' => 'Latest recomputed GSTR-2B and IMS actions verified',
        'exceptions_resolved' => 'Missing, duplicate and mismatched records investigated',
        'itc_conditions' => 'ITC eligibility, time limits, reversals and reclaims verified',
        'rcm_cash' => 'RCM liability and required cash payment verified',
        'pos_eco' => 'Place of supply, ECO and advances classifications verified',
        'liability_breakup' => 'Prior-period liability breakup reviewed',
        'interest_cash_balance' => 'Interest, late fees and minimum cash-ledger balance reviewed',
        'portal_setoff' => 'Portal credit/reversal/reclaim balances and payment setoff reviewed',
        'registration_scope' => 'All plants for this GSTIN consolidated or accounted for separately',
        'current_rules' => 'Applicable official rules and effective dates independently verified',
    ];

    public static function json(mixed $value): string
    {
        if (is_array($value)) {
            if (! array_is_list($value)) {
                ksort($value);
            }
            foreach ($value as &$v) {
                $v = json_decode(self::json($v), true, 512, JSON_THROW_ON_ERROR);
            }
        }

        return json_encode($value, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
    }

    public static function hash(mixed $v): string
    {
        return hash('sha256', self::json($v));
    }

    public static function period(string $p): string
    {
        Validator::make(['period' => $p], ['period' => ['required', 'regex:/^20[0-9]{2}-(0[1-9]|1[0-2])$/']])->validate();

        return $p;
    }

    public static function paise(string|int|float|null $amount): int
    {
        $text = trim((string) ($amount ?? '0'));
        abort_unless(preg_match('/^(-?)([0-9]{1,12})(?:\.([0-9]{1,4}))?$/D', $text, $m), 422, 'Invalid stored decimal amount');
        $fraction = str_pad($m[3] ?? '', 4, '0');
        $n = (int) $m[2] * 100 + (int) substr($fraction, 0, 2) + (substr($fraction, 2, 1) >= '5' ? 1 : 0);

        return ($m[1] === '-' ? -1 : 1) * $n;
    }

    public static function amounts(array $a): array
    {
        abort_if(array_diff(array_keys($a), self::HEADS) !== [], 422, 'Unknown tax head');
        $out = [];
        foreach (self::HEADS as $head) {
            $v = $a[$head] ?? 0;
            abort_unless(is_int($v) && abs($v) <= 1000000000000, 422, 'Use bounded integer paise');
            $out[$head] = $v;
        }

        return $out;
    }

    public static function invoice(array $a): array
    {
        $v = Validator::make($a, [
            'supplier_gstin' => ['required', 'regex:/^[0-9]{2}[A-Z]{5}[0-9]{4}[A-Z][1-9A-Z]Z[0-9A-Z]$/'],
            'number' => ['required', 'string', 'max:100', 'regex:/[A-Za-z0-9]/'], 'date' => 'required|date_format:Y-m-d',
            'kind' => 'required|in:INV,CN,DN', 'amounts' => 'required|array',
            'itc_available' => 'required|boolean', 'ims' => 'required|in:accepted,rejected,pending,deemed_accepted,not_applicable',
            'reverse_charge' => 'required|boolean', 'section' => 'required|string|max:40',
        ])->validate();
        $v['amounts'] = self::amounts($v['amounts']);
        foreach ($v['amounts'] as $n) {
            abort_if($v['kind'] === 'CN' ? $n > 0 : $n < 0, 422, 'Credit notes require negative values; other documents nonnegative values');
        }
        $v['itc_available'] = (bool) $v['itc_available'];
        $v['reverse_charge'] = (bool) $v['reverse_charge'];

        return $v;
    }
}
