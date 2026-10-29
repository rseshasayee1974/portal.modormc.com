<?php
/*
Author: ragul-onemodo
Created: 2026-10-13 12:33:34 Asia/Calcutta (UTC+05:30)
*/

namespace App\Services;

use App\Models\Plant;
use Illuminate\Database\Eloquent\Model;

class PrintBrandHeader
{
    public static function resolve(array $context): array
    {
        $company = $context['data']['company'] ?? $context['company'] ?? [];
        if (!is_array($company)) $company = [];
        $plant = ($context['plant'] ?? null) instanceof Plant ? $context['plant'] : null;
        if (!$plant) {
            foreach (['payment', 'invoice', 'inv', 'order', 'quotation', 'inward', 'test', 'batch'] as $key) {
                $document = $context[$key] ?? $context['data'][$key] ?? null;
                if (!$document instanceof Model) continue;
                if ($key === 'batch') {
                    $plant = $document->plant ?? $document->salesOrder?->plant ?? $document->workOrder?->plant;
                } elseif ($key === 'inward') {
                    $plant = $document->order?->plant;
                } else {
                    $plant = $document->plant;
                }
                if ($plant instanceof Plant) break;
            }
        }
        $plantId = $company['plant_id'] ?? $context['data']['plant_id'] ?? null;
        if (!$plant && $plantId) $plant = Plant::find($plantId);
        // Formatted sample documents use their supplied company, never an unrelated plant.
        if (!$plant && !$company) {
            $plantId = app(PlantContextService::class)->plantId() ?? session('active_plant_id');
            if ($plantId) $plant = Plant::find($plantId);
        }
        $entity = $plant?->entity;
        if ($entity?->exists && !array_key_exists('logo_file', $entity->getAttributes())) {
            $entity = $plant->entity()->first();
        }
        $logo = null;
        $batchSheetLogo = !empty($context['batchSheetBrand']) && $plant
            ? (\App\Models\CustomSetting::getForModule($plant->id, 'batching')['batch_sheet_logo_path'] ?? null) : null;
        foreach ([$batchSheetLogo, $plant?->logo_path, $entity?->logo_file, $company['logo_path'] ?? null] as $path) {
            if ($logo = PdfLogo::dataUri($path)) break;
        }
        $address = $plant?->addresses?->firstWhere('is_primary', true) ?? $plant?->addresses?->first();
        $addressText = $address
            ? implode(', ', array_filter([$address->line_1, $address->line_2, $address->city,
                $address->state?->state_name ?? $address->state_code, $address->zipcode]))
            : implode(', ', array_filter([$company['address'] ?? '', $company['city'] ?? '',
                $company['state'] ?? '', $company['pin'] ?? '']));
        return [
            'logo' => $logo,
            'name' => $plant?->name ?: ($company['name'] ?? ''),
            'address' => $addressText,
            'gstin' => $plant?->gstin ?: ($company['gstin'] ?? ''),
        ];
    }
}
