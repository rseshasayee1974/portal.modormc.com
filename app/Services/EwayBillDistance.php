<?php

namespace App\Services;

class EwayBillDistance
{
    public static function fromGateway(array $data): ?int
    {
        $data = array_combine(array_map('trim', array_keys($data)), array_values($data)) ?: [];
        foreach (['actualDist', 'ActualDist', 'transDistance', 'Distance', 'distance'] as $key) {
            $value = $data[$key] ?? null;
            if (is_numeric($value) && (float) $value > 0) {
                return (int) ceil((float) $value);
            }
        }
        return null;
    }

    public static function validFrom(string $date, ?int $distance): string
    {
        return $date . ($distance ? " [{$distance} Kms]" : ' [Distance unavailable]');
    }
}
