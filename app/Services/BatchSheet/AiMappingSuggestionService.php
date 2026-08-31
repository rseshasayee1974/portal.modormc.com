<?php

namespace App\Services\BatchSheet;

use App\Ai\Agents\BatchSheetMappingSuggester;
use App\Models\BatchSheetFieldDictionary;
use App\Models\BatchSheetUpload;
use App\Models\Product;
use Illuminate\Support\Facades\Log;

/**
 * AiMappingSuggestionService
 *
 * Configuration-time AI assistant for plant onboarding (PRD §3.6 / §3.7).
 * Given an already-parsed upload, this service asks the
 * BatchSheetMappingSuggester agent to propose a field mapping and material
 * mapping for the plant, which an administrator then reviews and confirms
 * in FieldMappingEditor.vue before it is saved as a BatchSheetTemplate.
 *
 * This is NOT called on every import — only when an admin explicitly clicks
 * "Suggest with AI" while building or refreshing a plant's template.
 */
class AiMappingSuggestionService
{
    /**
     * Build suggestions for the given upload's plant.
     *
     * @return array{field_mappings: array, material_mappings: array, provider: string}
     */
    public function suggest(BatchSheetUpload $upload): array
    {
        $rawHeaderFields = $upload->parsed_json['header_fields'] ?? [];
        $rawMaterialRows = $upload->parsed_json['materials'] ?? [];

        if (empty($rawHeaderFields) && empty($rawMaterialRows)) {
            throw new \RuntimeException('This upload has no extracted fields or materials to suggest mappings for.');
        }

        $canonicalFields = BatchSheetFieldDictionary::query()
            ->where('category', 'header')
            ->get(['canonical_name', 'aliases', 'data_type'])
            ->map(fn ($f) => [
                'canonical_name' => $f->canonical_name,
                'aliases' => $f->aliases ?? [],
                'data_type' => $f->data_type,
            ])
            ->values()
            ->all();

        $plantProducts = Product::query()
            ->where('plant_id', $upload->plant_id)
            ->get(['id', 'title'])
            ->map(fn ($p) => ['id' => $p->id, 'title' => $p->title])
            ->values()
            ->all();

        $rawMaterialNames = collect($rawMaterialRows)
            ->pluck('material_name')
            ->filter()
            ->unique()
            ->values()
            ->all();

        $prompt = "Raw header fields extracted from this report (label => example value):\n"
            . json_encode($rawHeaderFields, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)
            . "\n\nRaw material row names extracted from this report:\n"
            . json_encode($rawMaterialNames, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);

        $agent = new BatchSheetMappingSuggester(
            canonicalFields: $canonicalFields,
            plantProducts: $plantProducts,
        );

        [$response, $provider] = $this->promptSafely($agent, $prompt);

        // Structured-output responses can be accessed like an array; fall back
        // to ->structured for SDK versions that expose it as a property instead.
        $fieldMappings = $response['field_mappings'] ?? ($response->structured['field_mappings'] ?? []);
        $materialMappings = $response['material_mappings'] ?? ($response->structured['material_mappings'] ?? []);

        return [
            'field_mappings' => $fieldMappings,
            'material_mappings' => $materialMappings,
            'provider' => $provider,
        ];
    }

    /**
     * Prompt the agent, falling back through the configured provider chain
     * (config('ai.chain')) if the default provider errors out. Mirrors the
     * fallback pattern already used in AssistantController::promptAgentSafely.
     *
     * @return array{0: mixed, 1: string} [response, providerUsed]
     */
    protected function promptSafely(BatchSheetMappingSuggester $agent, string $prompt): array
    {
        $originalDefault = config('ai.default');
        $chain = config('ai.chain', ['gemini', 'openai']);

        if (($key = array_search($originalDefault, $chain)) !== false) {
            unset($chain[$key]);
        }
        array_unshift($chain, $originalDefault);
        $chain = array_values(array_filter(array_unique($chain)));

        $lastException = null;

        foreach ($chain as $provider) {
            $provider = trim($provider);
            if (empty($provider)) {
                continue;
            }

            try {
                config(['ai.default' => $provider]);
                $response = $agent->prompt($prompt);
                config(['ai.default' => $originalDefault]);

                return [$response, $provider];
            } catch (\Exception $e) {
                $lastException = $e;
                Log::warning("AiMappingSuggestionService: provider [{$provider}] failed", ['error' => $e->getMessage()]);
            }
        }

        config(['ai.default' => $originalDefault]);
        throw $lastException ?: new \RuntimeException('All AI providers failed to produce mapping suggestions.');
    }
}
