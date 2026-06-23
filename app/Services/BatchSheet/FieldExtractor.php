<?php

namespace App\Services\BatchSheet;

use App\Models\BatchSheetFieldDictionary;
use App\Models\BatchSheetTemplate;
use Illuminate\Support\Facades\Log;

class FieldExtractor
{
    /**
     * Normalize raw extracted header fields using either a layout template
     * or by fuzzy-matching labels against the Field Dictionary.
     */
    public function extract(array $rawFields, ?BatchSheetTemplate $template = null): array
    {
        $normalized = [];
        $fieldScores = [];

        // If template exists, use its defined mappings first
        if ($template && !empty($template->field_mapping)) {
            Log::info("FieldExtractor: Applying template '{$template->name}'");
            foreach ($template->field_mapping as $canonicalName => $rawLabel) {
                // Find if this rawLabel exists in rawFields
                $matchedKey = $this->findKeyCaseInsensitive($rawFields, $rawLabel);
                if ($matchedKey !== null) {
                    $normalized[$canonicalName] = $rawFields[$matchedKey];
                    $fieldScores[$canonicalName] = 100.0; // Template matches have 100% confidence
                }
            }
        }

        // For any remaining canonical fields that aren't filled yet, resolve via dictionary
        foreach ($rawFields as $rawLabel => $rawValue) {
            // Skip labels that are numeric or too long
            if (is_numeric($rawLabel) || strlen($rawLabel) > 50) {
                continue;
            }

            $dictEntry = BatchSheetFieldDictionary::resolveCanonical($rawLabel, 'header');
            if ($dictEntry) {
                $canonical = $dictEntry->canonical_name;

                // Only overwrite if it wasn't mapped by template (which has 100% confidence)
                if (!isset($normalized[$canonical])) {
                    $normalized[$canonical] = $rawValue;
                    // Score confidence based on exact or fuzzy match
                    $distance = levenshtein(strtolower(trim($rawLabel)), strtolower($canonical));
                    $confidence = max(100.0 - ($distance * 10), 60.0);
                    $fieldScores[$canonical] = $confidence;
                }
            }
        }

        return [
            'header' => $normalized,
            'field_scores' => $fieldScores,
        ];
    }

    protected function findKeyCaseInsensitive(array $array, string $searchKey): ?string
    {
        $searchKeyLower = strtolower(trim($searchKey));
        foreach ($array as $key => $val) {
            if (strtolower(trim($key)) === $searchKeyLower) {
                return $key;
            }
        }
        return null;
    }
}
