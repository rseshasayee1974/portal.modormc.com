<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class EmbeddingService
{
    private string $model = 'text-embedding-3-small';

    /**
     * Generate an embedding vector for text using OpenAI.
     *
     * @param  string  $text
     * @return float[]   Empty array on failure.
     */
    public function embed(string $text): array
    {
        $text = substr(trim($text), 0, 8000); // OpenAI token limit safety
        if (empty($text)) {
            return [];
        }

        try {
            $response = Http::withToken(config('services.openai.key'))
                ->timeout(30)
                ->post('https://api.openai.com/v1/embeddings', [
                    'model' => $this->model,
                    'input' => $text,
                ]);

            if ($response->failed()) {
                Log::warning('Embedding API failed', [
                    'status' => $response->status(),
                    'body'   => $response->body(),
                ]);
                return [];
            }

            return $response->json('data.0.embedding', []);
        } catch (\Throwable $e) {
            Log::error('EmbeddingService error: ' . $e->getMessage());
            return [];
        }
    }

    /**
     * Compute cosine similarity between two embedding vectors.
     *
     * @param  float[]  $a
     * @param  float[]  $b
     * @return float  0.0–1.0 (1.0 = identical)
     */
    public function cosineSimilarity(array $a, array $b): float
    {
        if (empty($a) || empty($b) || count($a) !== count($b)) {
            return 0.0;
        }

        $dot  = 0.0;
        $magA = 0.0;
        $magB = 0.0;

        foreach ($a as $i => $val) {
            $dot  += $val * $b[$i];
            $magA += $val * $val;
            $magB += $b[$i] * $b[$i];
        }

        $mag = sqrt($magA) * sqrt($magB);
        return $mag > 0 ? $dot / $mag : 0.0;
    }

    /**
     * Rough token count estimate (1 token ≈ 4 chars).
     */
    public function estimateTokens(string $text): int
    {
        return (int) ceil(mb_strlen($text) / 4);
    }
}
