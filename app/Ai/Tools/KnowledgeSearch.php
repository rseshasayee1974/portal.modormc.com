<?php

namespace App\Ai\Tools;

use App\Models\RagDocument;
use App\Services\EmbeddingService;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use Illuminate\Support\Facades\Auth;
use Stringable;

class KnowledgeSearch implements Tool
{
    /**
     * How many top matching documents to include in the response.
     */
    private int $topK = 4;

    /**
     * Minimum similarity score to include a result (0.0–1.0).
     */
    private float $minScore = 0.30;

    /**
     * Get the description of the tool's purpose.
     */
    public function description(): Stringable|string
    {
        return 'Search the company knowledge base including FAQs, Standard Operating Procedures (SOPs), product/service information, company policies, and documentation. Use this tool when the user asks about company rules, procedures, how something works, product specs, policies, or any question that cannot be answered from the live CRM database records. Always search here before saying "I don\'t know" about company-specific information.';
    }

    /**
     * Execute the search.
     */
    public function handle(Request $request): Stringable|string
    {
        $query = trim($request['query'] ?? '');

        if (empty($query)) {
            return 'No search query provided.';
        }

        // Resolve entity context for the logged-in user
        $entityId = null;
        $user = Auth::user();
        if ($user) {
            $entityUser = \App\Models\EntityUser::where('user_id', $user->id)->first();
            $entityId = $entityUser?->entity_id;
        }

        /** @var EmbeddingService $embeddingService */
        $embeddingService = app(EmbeddingService::class);

        // Generate embedding for the search query
        $queryEmbedding = $embeddingService->embed($query);

        if (empty($queryEmbedding)) {
            // Fallback to full-text search if embedding fails
            return $this->fullTextSearch($query, $entityId);
        }

        // Load active documents with embeddings (scoped to entity)
        $docs = RagDocument::active()
            ->forEntity($entityId)
            ->whereNotNull('embedding')
            ->select('id', 'title', 'content', 'embedding', 'source_type')
            ->get();

        if ($docs->isEmpty()) {
            return 'The knowledge base is empty. No documents have been indexed yet.';
        }

        // Rank by cosine similarity
        $scored = $docs->map(function ($doc) use ($embeddingService, $queryEmbedding) {
            $docEmbedding = json_decode($doc->embedding, true);
            $score = empty($docEmbedding)
                ? 0.0
                : $embeddingService->cosineSimilarity($queryEmbedding, $docEmbedding);

            return ['doc' => $doc, 'score' => $score];
        })
        ->filter(fn($r) => $r['score'] >= $this->minScore)
        ->sortByDesc('score')
        ->take($this->topK)
        ->values();

        if ($scored->isEmpty()) {
            return 'No relevant documentation found in the knowledge base for: "' . $query . '"';
        }

        // Format as context for the AI
        $context  = "**Relevant Knowledge Base Results** (for query: \"{$query}\"):\n\n";
        foreach ($scored as $rank => $result) {
            $doc   = $result['doc'];
            $score = round($result['score'] * 100, 1);
            $num   = $rank + 1;
            $context .= "### {$num}. [{$doc->source_type}] {$doc->title} (Relevance: {$score}%)\n\n";
            // Truncate each chunk to ~600 chars to fit in context
            $excerpt = mb_strlen($doc->content) > 600
                ? mb_substr($doc->content, 0, 600) . '…'
                : $doc->content;
            $context .= $excerpt . "\n\n---\n\n";
        }

        return $context;
    }

    /**
     * Simple LIKE-based fallback when embedding generation fails.
     */
    private function fullTextSearch(string $query, ?int $entityId): string
    {
        $words = array_filter(explode(' ', $query), fn($w) => mb_strlen($w) > 3);

        $docs = RagDocument::active()
            ->forEntity($entityId)
            ->where(function ($q) use ($words) {
                foreach ($words as $word) {
                    $q->orWhere('content', 'like', '%' . $word . '%')
                      ->orWhere('title', 'like', '%' . $word . '%');
                }
            })
            ->select('id', 'title', 'content', 'source_type')
            ->limit($this->topK)
            ->get();

        if ($docs->isEmpty()) {
            return 'No relevant documentation found for: "' . $query . '"';
        }

        $context = "**Knowledge Base Results** (keyword search for: \"{$query}\"):\n\n";
        foreach ($docs as $i => $doc) {
            $num     = $i + 1;
            $excerpt = mb_strlen($doc->content) > 500
                ? mb_substr($doc->content, 0, 500) . '…'
                : $doc->content;
            $context .= "### {$num}. [{$doc->source_type}] {$doc->title}\n\n{$excerpt}\n\n---\n\n";
        }

        return $context;
    }

    /**
     * Get the tool's input schema.
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'query' => $schema->string()
                ->description('The search query to look up in the knowledge base. Be specific — include relevant keywords about the topic, product, policy, or procedure.')
                ->required(),
        ];
    }
}
