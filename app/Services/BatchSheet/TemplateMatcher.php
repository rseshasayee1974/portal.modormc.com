<?php

namespace App\Services\BatchSheet;

use App\Models\BatchSheetTemplate;
use Illuminate\Support\Facades\Log;

class TemplateMatcher
{
    protected PatternRecognitionService $patternService;

    public function __construct(PatternRecognitionService $patternService)
    {
        $this->patternService = $patternService;
    }

    /**
     * Match extracted raw header fields and material rows to an existing template
     * for the plant with O(1) best-case time complexity.
     *
     * @param array $rawFields
     * @param int $plantId
     * @param array $materialRows
     * @param string|null $rawText
     * @return BatchSheetTemplate|null
     */
    public function match(
        array $rawFields,
        int $plantId,
        array $materialRows = [],
        ?string $rawText = null
    ): ?BatchSheetTemplate {
        Log::info("TemplateMatcher: Matching pattern for plant {$plantId} (" . count($rawFields) . " headers, " . count($materialRows) . " materials)");

        if (empty($rawFields) && empty($materialRows)) {
            return null;
        }

        return $this->patternService->findBestMatch($plantId, $rawFields, $materialRows, $rawText);
    }
}
