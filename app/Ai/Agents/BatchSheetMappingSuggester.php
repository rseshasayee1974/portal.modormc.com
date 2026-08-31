<?php

namespace App\Ai\Agents;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Contracts\HasStructuredOutput;
use Laravel\Ai\Promptable;
use Stringable;

/**
 * BatchSheetMappingSuggester — AI onboarding assistant for the Batch Sheet
 * Reconciliation Engine.
 *
 * Given the raw header labels and raw material names extracted from a
 * plant's uploaded batch sheet, this agent suggests:
 *   1. Which canonical application field each raw header label corresponds to.
 *   2. Which existing product in the plant's catalog each raw material name
 *      corresponds to (or flags it as a new/unrecognized material).
 *
 * This agent is a configuration-time assistant only — it is never called on
 * every import. It runs once when an admin is building or refreshing a
 * plant's mapping template (see AiMappingSuggestionService).
 */
class BatchSheetMappingSuggester implements Agent, HasStructuredOutput
{
    use Promptable;

    /**
     * @param array<int, array{canonical_name:string, aliases:array, data_type:string, category:string}> $canonicalFields
     * @param array<int, array{id:int, title:string}> $plantProducts
     */
    public function __construct(
        protected array $canonicalFields = [],
        protected array $plantProducts = [],
    ) {
    }

    public function instructions(): Stringable|string
    {
        $fieldList = collect($this->canonicalFields)
            ->map(function (array $f) {
                $aliases = implode(', ', $f['aliases'] ?? []);
                return "- {$f['canonical_name']} (type: {$f['data_type']}) — known aliases: {$aliases}";
            })
            ->implode("\n");

        $productList = collect($this->plantProducts)
            ->map(fn (array $p) => "- [id={$p['id']}] {$p['title']}")
            ->implode("\n");

        return <<<INSTRUCTIONS
        You are a data-mapping assistant for a multi-plant concrete batching reconciliation
        system (ModoRMC). Every plant runs different batching software and produces Batch
        Sheet reports with different field labels and material column headers. Your job is
        to help an administrator onboard a plant by suggesting how to map that plant's raw
        report labels onto the system's canonical fields and existing product catalog.

        ## Canonical application fields available to map header labels onto
        {$fieldList}

        ## This plant's existing product/material catalog
        {$productList}

        ## Your task
        You will be given the raw header field labels (with example values) and the raw
        material row names extracted from one uploaded report belonging to this plant.

        1. For every canonical field above, decide whether one of the raw header labels
           corresponds to it. If yes, return that exact raw label, a confidence score from
           0-100, and a one-sentence reason. If no raw label matches, return raw_label as
           null and confidence 0.
        2. For every raw material name given, decide whether it corresponds to an existing
           product in the catalog above. If yes, return that product's id, title, a
           confidence score, and a reason. If nothing in the catalog is a reasonable match,
           set suggested_product_id to null, suggested_product_title to null, set
           is_new_material to true, and briefly say what kind of material it looks like
           (e.g. "appears to be a coarse aggregate").

        Only suggest a mapping when there is real textual or contextual evidence in the raw
        label itself. Never invent a raw label or a product id that was not provided to you.
        Confidence should reflect genuine uncertainty — an exact or near-exact label match
        should score 90+, a plausible semantic match should score 60-89, and a weak guess
        should score below 60.
        INSTRUCTIONS;
    }

    public function messages(): iterable
    {
        return [];
    }

    /**
     * Structured output schema — guarantees the model returns exactly this shape.
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'field_mappings' => $schema->array()->items(
                $schema->object(fn ($schema) => [
                    'canonical_key' => $schema->string()->required(),
                    'raw_label' => $schema->string()->nullable(),
                    'confidence' => $schema->integer()->required(),
                    'reasoning' => $schema->string()->required(),
                ])
            )->required(),

            'material_mappings' => $schema->array()->items(
                $schema->object(fn ($schema) => [
                    'raw_material_name' => $schema->string()->required(),
                    'suggested_product_id' => $schema->integer()->nullable(),
                    'suggested_product_title' => $schema->string()->nullable(),
                    'confidence' => $schema->integer()->required(),
                    'reasoning' => $schema->string()->required(),
                    'is_new_material' => $schema->boolean()->required(),
                ])
            )->required(),
        ];
    }
}
