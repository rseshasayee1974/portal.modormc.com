<?php

/*
Author: ragul-onemodo
Created: 2026-10-18 21:12:03 Asia/Calcutta (UTC+05:30)
*/

namespace App\Services\Finance;

final class AccountingMoney
{
    // Four decimal places preserve the existing portal ledger's stored precision.
    public static function units(mixed $value): int
    {
        abort_unless(is_int($value) || is_string($value), 422, 'Amounts must be decimal strings');
        abort_unless(preg_match('/^(-?)(\d{1,12})(?:\.(\d{1,4}))?$/D', (string) $value, $m), 422, 'Use a plain decimal with at most four places');
        $n = (int) $m[2] * 10000 + (int) str_pad($m[3] ?? '', 4, '0');

        return ($m[1] === '-' ? -1 : 1) * $n;
    }

    public static function decimal(int $n): string
    {
        return ($n < 0 ? '-' : '').intdiv(abs($n), 10000).'.'.str_pad((string) (abs($n) % 10000), 4, '0', STR_PAD_LEFT);
    }

    public static function percentage(int $amount, string $percent): int
    {
        $rate = self::units($percent);
        abort_unless($amount >= 0 && $rate >= 0 && $rate <= 1000000, 422, 'Percentage discounts must be between zero and 100');

        // Decompose first so a large monetary value is never multiplied by the full rate.
        return intdiv($amount, 1000000) * $rate + intdiv(($amount % 1000000) * $rate + 500000, 1000000);
    }

    public static function roundPaiseToRupee(int $paise): int
    {
        return ($paise < 0 ? -1 : 1) * intdiv(abs($paise) + 50, 100) * 100;
    }
}
