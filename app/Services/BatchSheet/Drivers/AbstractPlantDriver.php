<?php

namespace App\Services\BatchSheet\Drivers;

use App\Services\BatchSheet\Contracts\PlantDriverInterface;
use Carbon\Carbon;

abstract class AbstractPlantDriver implements PlantDriverInterface
{
    /**
     * Extract float numbers from a string line.
     */
    protected function extractNumbers(string $line): array
    {
        preg_match_all('/-?\d+(?:[.,]\d+)?/', $line, $matches);
        return array_map(fn($n) => (float) str_replace(',', '', $n), $matches[0]);
    }

    /**
     * Normalize date string to Y-m-d format.
     */
    protected function normalizeDate(?string $dateStr): ?string
    {
        if (empty($dateStr)) return null;
        try {
            return Carbon::parse(trim($dateStr))->format('Y-m-d');
        } catch (\Exception $e) {
            return trim($dateStr);
        }
    }

    /**
     * Normalize time string to H:i:s format.
     */
    protected function normalizeTime(?string $timeStr): ?string
    {
        if (empty($timeStr)) return null;
        try {
            return Carbon::parse(trim($timeStr))->format('H:i:s');
        } catch (\Exception $e) {
            if (preg_match('/(\d{1,2})[\s:-](\d{2})(?:[\s:-](\d{2}))?/', $timeStr, $matches)) {
                $h = str_pad($matches[1], 2, '0', STR_PAD_LEFT);
                $m = str_pad($matches[2], 2, '0', STR_PAD_LEFT);
                $s = isset($matches[3]) ? str_pad($matches[3], 2, '0', STR_PAD_LEFT) : '00';
                return "{$h}:{$m}:{$s}";
            }
            return trim($timeStr);
        }
    }

    /**
     * Extract a regex capture group or return null.
     */
    protected function matchRegex(string $pattern, string $text, int $group = 1): ?string
    {
        if (preg_match($pattern, $text, $matches)) {
            return isset($matches[$group]) ? trim($matches[$group]) : null;
        }
        return null;
    }
}
