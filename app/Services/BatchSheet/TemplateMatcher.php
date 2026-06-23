<?php

namespace App\Services\BatchSheet;

use App\Models\BatchSheetTemplate;
use Illuminate\Support\Facades\Log;

class TemplateMatcher
{
    /**
     * Match extracted raw header fields to an existing template for the plant
     * based on the keys (labels) present in the document.
     */
    public function match(array $rawFields, int $plantId): ?BatchSheetTemplate
    {
        Log::info("TemplateMatcher: Attempting to match template for plant {$plantId} with " . count($rawFields) . " raw fields");

        if (empty($rawFields)) {
            return null;
        }

        $templates = BatchSheetTemplate::where('plant_id', $plantId)
            ->where('is_active', true)
            ->get();

        if ($templates->isEmpty()) {
            Log::info("TemplateMatcher: No active templates found for plant {$plantId}");
            return null;
        }

        $rawLabelsLower = array_map('strtolower', array_keys($rawFields));
        
        $bestMatch = null;
        $highestSimilarity = 0.0;
        $threshold = 0.7; // 70% key overlap threshold

        foreach ($templates as $template) {
            $mapping = $template->field_mapping ?? [];
            if (empty($mapping)) continue;

            // Template expected labels
            $templateLabelsLower = array_map('strtolower', array_values($mapping));

            // Calculate Jaccard similarity: intersection / union of keys
            $intersection = array_intersect($rawLabelsLower, $templateLabelsLower);
            $union = array_unique(array_merge($rawLabelsLower, $templateLabelsLower));

            if (empty($union)) continue;

            $similarity = count($intersection) / count($union);
            Log::debug("TemplateMatcher: Comparing with template '{$template->name}' - Similarity: {$similarity}");

            if ($similarity > $highestSimilarity && $similarity >= $threshold) {
                $highestSimilarity = $similarity;
                $bestMatch = $template;
            }
        }

        if ($bestMatch) {
            Log::info("TemplateMatcher: Matched template '{$bestMatch->name}' (Similarity: {$highestSimilarity})");
            // Increment usage count
            $bestMatch->increment('usage_count');
        } else {
            Log::info("TemplateMatcher: No matching template found above threshold");
        }

        return $bestMatch;
    }
}
