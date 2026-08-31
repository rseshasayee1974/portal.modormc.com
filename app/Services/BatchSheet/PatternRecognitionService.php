<?php

namespace App\Services\BatchSheet;

use App\Models\BatchSheetFieldDictionary;
use App\Models\BatchSheetTemplate;
use App\Models\Product;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * PatternRecognitionService
 *
 * Provides high-performance, intelligent pattern recognition, template layout
 * fingerprinting, and automated pattern learning for concrete batch sheets.
 *
 * Complexity:
 * - Best Case Time Complexity: O(1) exact layout hash lookup.
 * - Average Match Time Complexity: O(K) where K is the number of distinct token keys.
 * - Space Complexity: O(K) minimal memory footprint per document.
 */
class PatternRecognitionService
{
    /**
     * Common plant manufacturing & software signature keywords for rapid brand/format classification.
     */
    protected const BRAND_SIGNATURES = [
        'schwing', 'stetter', 'mci 360', 'mci360', 'm1.5', 'm2.5', 'cp30', 'm1',
        'macons', 'apollo', 'aquarius', 'command alkon', 'putzmeister', 'liebherr',
        'kyc', 'simem', 'bhs', 'elkon', 'ajax', 'fiori'
    ];

    /**
     * Generate a deterministic, canonical layout fingerprint hash from raw keys.
     *
     * Time Complexity: O(K log K) for sorting K keys.
     * Space Complexity: O(K).
     */
    public function generateFingerprint(array $rawHeaderKeys, array $materialHeaders = []): string
    {
        $normalizedKeys = [];

        foreach ($rawHeaderKeys as $key) {
            if (is_string($key) && !empty(trim($key))) {
                $clean = Str::lower(preg_replace('/[^a-zA-Z0-9]/', '', $key));
                if (!empty($clean)) {
                    $normalizedKeys[] = $clean;
                }
            }
        }

        foreach ($materialHeaders as $mat) {
            $name = is_array($mat) ? ($mat['material_name'] ?? '') : (string)$mat;
            if (!empty(trim($name))) {
                $clean = Str::lower(preg_replace('/[^a-zA-Z0-9]/', '', $name));
                if (!empty($clean)) {
                    $normalizedKeys[] = 'mat_' . $clean;
                }
            }
        }

        $normalizedKeys = array_values(array_unique($normalizedKeys));
        sort($normalizedKeys, SORT_STRING);

        return 'fp_' . hash('sha256', implode('|', $normalizedKeys));
    }

    /**
     * Extract prominent anchor keywords from headers, materials, and raw OCR text.
     *
     * Time Complexity: O(K + T) where T is text length.
     * Space Complexity: O(K).
     */
    public function extractKeywords(array $rawHeaderKeys, array $materialHeaders = [], ?string $rawText = null): array
    {
        $keywords = [];

        // 1. Detect Brand & Control System Signatures
        $textLower = Str::lower($rawText ?? '');
        foreach (self::BRAND_SIGNATURES as $brand) {
            if (str_contains($textLower, $brand)) {
                $keywords[] = $brand;
            }
        }

        // 2. Add Clean Key Headers
        foreach ($rawHeaderKeys as $key) {
            if (is_string($key) && strlen($key) > 2 && strlen($key) <= 40) {
                $keywords[] = Str::lower(trim($key));
            }
        }

        // 3. Add Material Columns
        foreach ($materialHeaders as $mat) {
            $name = is_array($mat) ? ($mat['material_name'] ?? '') : (string)$mat;
            if (!empty(trim($name)) && strlen($name) <= 30) {
                $keywords[] = Str::lower(trim($name));
            }
        }

        return array_values(array_unique($keywords));
    }

    /**
     * Match a document against existing active templates with O(1) best case time complexity.
     *
     * @param int $plantId
     * @param array $rawHeaderFields
     * @param array $materialRows
     * @param string|null $rawText
     * @return BatchSheetTemplate|null
     */
    public function findBestMatch(
        int $plantId,
        array $rawHeaderFields,
        array $materialRows = [],
        ?string $rawText = null
    ): ?BatchSheetTemplate {
        if (empty($rawHeaderFields) && empty($materialRows)) {
            return null;
        }

        $rawHeaderKeys = array_keys($rawHeaderFields);
        $fingerprint = $this->generateFingerprint($rawHeaderKeys, $materialRows);

        // -------------------------------------------------------------
        // Step 1: O(1) Constant Time Exact Fingerprint Lookup (Best Case)
        // -------------------------------------------------------------
        $exactMatch = BatchSheetTemplate::query()
            ->where('plant_id', $plantId)
            ->where('is_active', true)
            ->where('layout_fingerprint', $fingerprint)
            ->first();

        if ($exactMatch) {
            Log::info("PatternRecognitionService: O(1) exact fingerprint match found: '{$exactMatch->name}' (ID: {$exactMatch->id})");
            $exactMatch->increment('usage_count');
            return $exactMatch;
        }

        // -------------------------------------------------------------
        // Step 2: O(K) Inverted Keyword & Similarity Matching (Fallback)
        // -------------------------------------------------------------
        $templates = BatchSheetTemplate::query()
            ->where('plant_id', $plantId)
            ->where('is_active', true)
            ->get();

        if ($templates->isEmpty()) {
            return null;
        }

        $extractedKeywords = $this->extractKeywords($rawHeaderKeys, $materialRows, $rawText);
        $extractedKeywordsMap = array_flip($extractedKeywords); // O(1) lookup map

        $rawKeysLowerMap = array_flip(array_map('strtolower', array_filter($rawHeaderKeys, 'is_string')));

        $bestTemplate = null;
        $highestScore = 0.0;
        $minThreshold = 0.55; // 55% weighted similarity threshold

        foreach ($templates as $template) {
            $templateKeywords = $template->keywords ?? [];
            $fieldMapping = $template->field_mapping ?? [];

            if (empty($templateKeywords) && empty($fieldMapping)) {
                continue;
            }

            // Keyword Overlap (Weight 40%)
            $kwMatches = 0;
            foreach ($templateKeywords as $kw) {
                if (isset($extractedKeywordsMap[strtolower($kw)])) {
                    $kwMatches++;
                }
            }
            $kwScore = !empty($templateKeywords) ? ($kwMatches / count($templateKeywords)) : 0.0;

            // Field Keys Overlap (Weight 60%)
            $fieldMatches = 0;
            $totalTemplateFields = count($fieldMapping);
            foreach ($fieldMapping as $canonical => $expectedRaw) {
                if (is_string($expectedRaw) && isset($rawKeysLowerMap[strtolower($expectedRaw)])) {
                    $fieldMatches++;
                }
            }
            $fieldScore = $totalTemplateFields > 0 ? ($fieldMatches / $totalTemplateFields) : 0.0;

            // Combined Weighted Score
            $totalScore = ($kwScore * 0.40) + ($fieldScore * 0.60);

            Log::debug("PatternRecognitionService: Evaluating template '{$template->name}' - Score: " . round($totalScore, 3));

            if ($totalScore > $highestScore && $totalScore >= $minThreshold) {
                $highestScore = $totalScore;
                $bestTemplate = $template;
            }
        }

        if ($bestTemplate) {
            Log::info("PatternRecognitionService: Matched template '{$bestTemplate->name}' via fuzzy scoring (Score: " . round($highestScore, 3) . ")");
            $bestTemplate->increment('usage_count');
        }

        return $bestTemplate;
    }

    /**
     * Automatically learn and persist a recognized pattern/template.
     * When a batch sheet is confirmed by user or verified with high accuracy,
     * this method saves or enriches the template for instant future matches.
     *
     * @param int $plantId
     * @param array $headerFields [canonical => value or rawLabel => value]
     * @param array $materials
     * @param string|null $rawText
     * @param string $sourceType
     * @param string|null $customName
     * @return BatchSheetTemplate
     */
    public function autoLearnPattern(
        int $plantId,
        array $headerFields,
        array $materials = [],
        ?string $rawText = null,
        string $sourceType = 'ocr_image',
        ?string $customName = null
    ): BatchSheetTemplate {
        $rawHeaderKeys = array_keys($headerFields);
        $fingerprint = $this->generateFingerprint($rawHeaderKeys, $materials);
        $keywords = $this->extractKeywords($rawHeaderKeys, $materials, $rawText);

        // 1. Build canonical field mappings
        $fieldMapping = [];
        foreach ($headerFields as $key => $val) {
            if (!is_string($key)) continue;

            $dict = BatchSheetFieldDictionary::resolveCanonical($key, 'header');
            $canonical = $dict ? $dict->canonical_name : Str::snake($key);
            $fieldMapping[$canonical] = $key;
        }

        // 2. Build material mappings (material name -> product mapping)
        $materialMapping = [];
        $plantProducts = Product::where('plant_id', $plantId)->get(['id', 'title'])->keyBy('id');

        foreach ($materials as $mat) {
            $name = is_array($mat) ? ($mat['material_name'] ?? '') : (string)$mat;
            $productId = is_array($mat) ? ($mat['product_id'] ?? null) : null;

            if (!empty($name)) {
                $productTitle = $productId && $plantProducts->has($productId)
                    ? $plantProducts->get($productId)->title
                    : $name;
                $materialMapping[$name] = $productTitle;
            }
        }

        // 3. Determine Template Name
        if (empty($customName)) {
            $detectedBrand = 'Standard';
            foreach (self::BRAND_SIGNATURES as $b) {
                if (in_array($b, $keywords, true)) {
                    $detectedBrand = Str::title($b);
                    break;
                }
            }
            $customName = "{$detectedBrand} Batch Sheet Format (" . strtoupper(substr(md5($fingerprint), 0, 6)) . ")";
        }

        // 4. Create or Update Template with Fingerprint
        $template = BatchSheetTemplate::updateOrCreate(
            [
                'plant_id' => $plantId,
                'layout_fingerprint' => $fingerprint,
            ],
            [
                'name' => $customName,
                'source_type' => $sourceType,
                'field_mapping' => $fieldMapping,
                'material_mapping' => $materialMapping,
                'keywords' => $keywords,
                'is_active' => true,
            ]
        );

        $template->increment('usage_count');
        

        Log::info("PatternRecognitionService: Pattern auto-learned and persisted to template '{$template->name}' (ID: {$template->id}, FP: {$fingerprint})");

        return $template;
    }
}
