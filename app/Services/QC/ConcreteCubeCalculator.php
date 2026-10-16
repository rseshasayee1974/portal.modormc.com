<?php
/*
Author: Cipher
Created: 2026-10-15 16:42:44 Asia/Calcutta (UTC+05:30)
*/

namespace App\Services\QC;

use App\Models\QC\QcTest;
use Illuminate\Validation\ValidationException;

class ConcreteCubeCalculator
{
    public function supports(QcTest $test): bool
    {
        $name = strtolower(($test->testType?->name ?? '').' '.($test->testType?->code ?? ''));
        return !preg_match('/flexur|slump|tensile|beam|cement|aggregate/', $name)
            && ($test->testType?->category === 'Concrete' || str_contains($name, 'cube') || str_contains($name, 'compressive'));
    }

    public function dimensions(QcTest $test): array
    {
        $value = $test->configuration_snapshot['specimen_dimensions']
            ?? $test->config?->specimen_dimensions
            ?? $test->sample?->specimen_size
            ?? $test->testType?->specimen_dimensions
            ?? '150x150x150 mm';
        if (!preg_match('/^\s*(\d+(?:\.\d+)?)\s*(?:mm\s*)?[x\x{00d7}*]\s*(\d+(?:\.\d+)?)\s*(?:mm\s*)?[x\x{00d7}*]\s*(\d+(?:\.\d+)?)\s*(?:mm)?\s*$/iu', $value, $matches)
            || min((float) $matches[1], (float) $matches[2], (float) $matches[3]) <= 0) {
            throw ValidationException::withMessages(['specimen_size' => 'Configure valid L x W x H dimensions in millimetres before testing concrete cubes.']);
        }
        [$length, $width, $height] = array_map('floatval', array_slice($matches, 1, 3));
        return [
            'length_mm' => $length, 'width_mm' => $width, 'height_mm' => $height,
            'area_mm2' => $length * $width,
            'volume_m3' => $length * $width * $height / 1_000_000_000,
        ];
    }

    public function observation(array $input, array $dimensions): array
    {
        $weight = isset($input['weight_kg']) && $input['weight_kg'] !== '' ? round((float) $input['weight_kg'], 2) : null;
        $load = isset($input['load_kn']) && $input['load_kn'] !== '' ? round((float) $input['load_kn'], 2) : null;
        return [
            'weight_kg' => $weight, 'load_kn' => $load,
            'cross_sectional_area' => $dimensions['area_mm2'],
            'density_kg_m3' => $weight !== null ? round($weight / $dimensions['volume_m3'], 2) : null,
            'strength_mpa' => $load !== null ? round($load * 1000 / $dimensions['area_mm2'], 2) : null,
        ];
    }

    public function snapshot(QcTest $test, array $dimensions): void
    {
        $snapshot = $test->configuration_snapshot ?: $test->config?->generateConfigurationSnapshot() ?: [];
        $snapshot['specimen_dimensions'] = implode('x', array_map(fn ($key) => $dimensions[$key], ['length_mm', 'width_mm', 'height_mm'])).' mm';
        $test->configuration_snapshot = $snapshot;
    }
}
