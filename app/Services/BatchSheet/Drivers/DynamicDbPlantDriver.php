<?php

namespace App\Services\BatchSheet\Drivers;

use App\Models\BatchSheetTemplate;
use Illuminate\Support\Facades\Log;

class DynamicDbPlantDriver extends AbstractPlantDriver
{
    public function getDriverCode(): string
    {
        return 'dynamic_db_template';
    }

    public function getDriverName(): string
    {
        return 'Dynamic Database Plant Template Driver';
    }

    public function getPlantSerial(): ?string
    {
        return null;
    }

    public function canHandle(string $rawText, array $context = []): bool
    {
        $plantId = $context['plant_id'] ?? session('active_plant_id');
        if (!$plantId) return false;

        // Check if any active DB template for this plant matches keywords in raw text
        $templates = BatchSheetTemplate::where('plant_id', $plantId)
            ->where('is_active', true)
            ->get();

        foreach ($templates as $t) {
            $keywords = $t->keywords ?? [];
            if (!empty($keywords)) {
                $matched = 0;
                foreach ($keywords as $kw) {
                    if (stripos($rawText, $kw) !== false) {
                        $matched++;
                    }
                }
                if ($matched >= max(1, count($keywords) / 2)) {
                    return true;
                }
            }
        }

        return false;
    }

    public function parse(string $rawText, array $context = []): array
    {
        $plantId = $context['plant_id'] ?? session('active_plant_id');
        $template = null;

        if ($plantId) {
            $template = BatchSheetTemplate::where('plant_id', $plantId)
                ->where('is_active', true)
                ->orderByDesc('usage_count')
                ->first();
        }

        $headerFields = [];
        if ($template && !empty($template->field_mapping)) {
            Log::info("DynamicDbPlantDriver: Applying dynamic DB template '{$template->name}'");
            foreach ($template->field_mapping as $canonical => $label) {
                if (preg_match('/' . preg_quote($label, '/') . '\s*[:=]?\s*([^\n\r]+)/i', $rawText, $m)) {
                    $headerFields[$canonical] = trim($m[1]);
                }
            }
            $template->increment('usage_count');
        }

        return [
            'headerFields' => $headerFields,
            'materialRows' => [],
            'confidence' => 90.0,
        ];
    }
}
