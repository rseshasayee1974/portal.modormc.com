<?php

namespace App\Services;

class EwayBillDistance
{
    /**
     * A blank/zero distance asks NIC to calculate the distance from the two PIN codes.
     * A positive value is a user-supplied route distance in kilometres.
     */
    public static function fromRequest(array $data): ?int
    {
        foreach (['distance_km', 'distance', 'transDistance', 'Distance'] as $key) {
            $value = $data[$key] ?? null;
            if ($value !== null && $value !== '' && is_numeric($value) && (float) $value > 0) {
                return (int) ceil((float) $value);
            }
        }

        return null;
    }

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
