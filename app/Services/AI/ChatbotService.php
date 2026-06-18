<?php

namespace App\Services\AI;

use App\Models\AiConversation;
use App\Models\AiMessage;
use App\Models\DocumentChunk;
use App\Models\RagDocument;
use App\Services\EmbeddingService;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * ChatbotService — Orchestrates RAG retrieval + AI response generation.
 *
 * Flow:
 *   1. Retrieve relevant RAG context (documents + chunks)
 *   2. Build system prompt + context
 *   3. Call AI provider (Gemini → OpenAI → Groq fallback chain)
 *   4. Save messages to DB
 *   5. Return response text + metadata
 */
class ChatbotService
{
    /** Maximum number of RAG chunks to include in context */
    private const MAX_RAG_RESULTS = 5;

    /** Minimum cosine similarity threshold for RAG matches */
    private const RAG_THRESHOLD = 0.3;

    public function __construct(
        private readonly EmbeddingService $embeddingService,
        private readonly \App\Services\Sarvam\TamilChatService $tamilChat,
    ) {}

    // ── Main Chat Method ───────────────────────────────────────────────────

    /**
     * Generate an AI response for a user message.
     *
     * @param  AiConversation  $conversation
     * @param  string          $userMessage
     * @param  int|null        $entityId        For scoped RAG retrieval
     * @return array{reply: string, provider: string, rag_sources: int[]}
     */
    public function chat(AiConversation $conversation, string $userMessage, ?int $entityId = null): array
    {
        // 1. Embed the user query for RAG
        $queryEmbedding = $this->embeddingService->embed($userMessage);

        // 2. Retrieve relevant context
        $ragContext = $this->retrieveContext($queryEmbedding, $entityId);

        // 3. Load conversation history (last 10 turns)
        $history = $conversation->messages()
            ->orderBy('created_at')
            ->select(['role', 'content'])
            ->take(20)
            ->get();

        // 4. Build full prompt
        $systemPrompt = $this->buildSystemPrompt($ragContext, $conversation->language);
        $messages     = $this->buildMessages($systemPrompt, $history, $userMessage);

        // 5. Call AI with fallback chain
        [$reply, $provider] = $this->callAI($messages);

        // 6. Persist both messages
        $ragSourceIds = array_column($ragContext, 'id');

        $userMsg = $conversation->messages()->create([
            'role'        => 'user',
            'content'     => $userMessage,
            'language'    => $conversation->language,
            'rag_sources' => $ragSourceIds,
        ]);

        $assistantMsg = $conversation->messages()->create([
            'role'        => 'assistant',
            'content'     => $reply,
            'language'    => $conversation->language,
            'provider'    => $provider,
            'rag_sources' => $ragSourceIds,
        ]);

        $conversation->incrementMessageCount();
        $conversation->incrementMessageCount(); // user + assistant

        return [
            'reply'       => $reply,
            'provider'    => $provider,
            'rag_sources' => $ragSourceIds,
            'message_id'  => $assistantMsg->id,
        ];
    }

    // ── RAG Retrieval ──────────────────────────────────────────────────────

    /**
     * Retrieve the most semantically relevant document chunks.
     *
     * @param  float[]    $queryEmbedding
     * @param  int|null   $entityId
     * @return array<array{id: int, title: string, content: string, score: float}>
     */
    public function retrieveContext(array $queryEmbedding, ?int $entityId = null): array
    {
        if (empty($queryEmbedding)) {
            // Fall back to full-text search on RagDocument
            return $this->fallbackTextContext($entityId);
        }

        $results = [];

        // Search document chunks first (more granular)
        $chunksQuery = DocumentChunk::active()
            ->whereNotNull('embedding')
            ->select(['id', 'rag_document_id', 'content', 'embedding']);

        if ($entityId) {
            $chunksQuery->forEntity($entityId);
        }

        $chunks = $chunksQuery->get();

        foreach ($chunks as $chunk) {
            $embedding = $chunk->getEmbeddingArray();
            if (empty($embedding)) continue;

            $score = $this->embeddingService->cosineSimilarity($queryEmbedding, $embedding);

            if ($score >= self::RAG_THRESHOLD) {
                $results[] = [
                    'id'      => $chunk->rag_document_id,
                    'title'   => $chunk->ragDocument?->title ?? 'Document',
                    'content' => $chunk->content,
                    'score'   => $score,
                    'source'  => 'chunk',
                ];
            }
        }

        // Also search full RagDocuments for smaller docs that aren't chunked
        $docsQuery = RagDocument::where('is_active', true)
            ->whereNotNull('embedding')
            ->select(['id', 'title', 'content', 'embedding']);

        if ($entityId) {
            $docsQuery->forEntity($entityId);
        }

        $docs = $docsQuery->get();

        foreach ($docs as $doc) {
            $embedding = json_decode($doc->embedding, true) ?: [];
            if (empty($embedding)) continue;

            $score = $this->embeddingService->cosineSimilarity($queryEmbedding, $embedding);

            if ($score >= self::RAG_THRESHOLD) {
                $results[] = [
                    'id'      => $doc->id,
                    'title'   => $doc->title,
                    'content' => mb_substr($doc->content, 0, 1000),
                    'score'   => $score,
                    'source'  => 'document',
                ];
            }
        }

        // Sort by score descending, take top N
        usort($results, fn ($a, $b) => $b['score'] <=> $a['score']);

        return array_slice($results, 0, self::MAX_RAG_RESULTS);
    }

    // ── Prompt Building ────────────────────────────────────────────────────

    private function buildSystemPrompt(array $ragContext, string $language): string
    {
        $languageInstruction = match ($language) {
            'ta', 'ta-IN' => 'Always respond in Tamil.',
            'hi', 'hi-IN' => 'Always respond in Hindi.',
            'te', 'te-IN' => 'Always respond in Telugu.',
            'ml', 'ml-IN' => 'Always respond in Malayalam.',
            'kn', 'kn-IN' => 'Always respond in Kannada.',
            'bn', 'bn-IN' => 'Always respond in Bengali.',
            'gu', 'gu-IN' => 'Always respond in Gujarati.',
            default        => 'Respond in the same language the user writes in.',
        };

        $contextText = '';
        if (!empty($ragContext)) {
            $contextText = "\n\n## Knowledge Base Context\nUse the following information to answer the user's question. If the answer is not in the context, say you don't have that information.\n\n";
            foreach ($ragContext as $i => $ctx) {
                $contextText .= "### Source " . ($i + 1) . ": {$ctx['title']}\n{$ctx['content']}\n\n";
            }
        }

        return <<<PROMPT
You are a helpful customer support assistant for ModoRMC, a concrete batching plant management system.

{$languageInstruction}

## Your Capabilities
- Answer questions about our products, services, and operations
- Help customers with their orders, invoices, and dispatches
- Provide technical guidance on concrete mix designs
- Escalate complex issues to human agents when needed

## Guidelines
- Be professional, concise, and helpful
- If you don't know something, say so clearly — do NOT make up information
- Always format important terms, quantities, metrics, concrete grades, weights, and currency amounts in **bold** using markdown (e.g., **₹45,000**, **M20 Grade**, **15.5 MT**, **3 dispatches**)
- If the customer wants to speak to a human, acknowledge and say "I'll connect you with a support agent"
- Keep responses under 300 words unless detail is specifically requested
- **Charts**: When presenting numerical comparisons, shares, or trends over time (e.g., comparing product/mix shares, monthly dispatch counts, cash vs credit sales), append a structured chart syntax at the end of your message. Format it exactly like this:
  [Chart: type=bar | title=Sales Trend | labels=Jan,Feb,Mar | data=12,19,15]
  Supported types: bar, line, pie. Keep it compact.
- **Suggestions**: At the very end of your response (after any chart), always provide 2 to 3 short and relevant follow-up questions the user might ask next. Format them exactly like this:
  [Suggestions: Question 1 | Question 2 | Question 3]
  If Knowledge Base Context is present, base these suggested questions directly on the retrieved context or matching documents. Keep suggested questions extremely short (under 6 words each) and respond in the same language.
{$contextText}
PROMPT;
    }

    private function buildMessages(string $systemPrompt, $history, string $userMessage): array
    {
        $messages = [
            ['role' => 'system', 'content' => $systemPrompt],
        ];

        foreach ($history as $msg) {
            $messages[] = [
                'role'    => $msg->role === 'assistant' ? 'assistant' : 'user',
                'content' => $msg->content,
            ];
        }

        $messages[] = ['role' => 'user', 'content' => $userMessage];

        return $messages;
    }

    // ── AI Provider Call ───────────────────────────────────────────────────

    /**
     * Call AI providers with automatic fallback (Gemini → OpenAI → Groq).
     *
     * @return array{0: string, 1: string}  [reply, provider]
     */
    private function callAI(array $messages): array
    {
        $chain = explode(',', env('AI_PROVIDER_CHAIN', 'gemini,openai,groq'));

        foreach ($chain as $provider) {
            $provider = trim($provider);
            try {
                $reply = match ($provider) {
                    'gemini' => $this->callGemini($messages),
                    'openai' => $this->callOpenAI($messages),
                    'groq'   => $this->callGroq($messages),
                    'sarvam' => $this->tamilChat->chat($messages),
                    default  => null,
                };

                if ($reply) {
                    return [$reply, $provider];
                }
            } catch (\Exception $e) {
                Log::warning("ChatbotService: provider [{$provider}] failed", ['error' => $e->getMessage()]);
            }
        }

        return ["I'm sorry, I'm having trouble connecting to my AI systems. Please try again later.", 'none'];
    }

    private function callGemini(array $messages): string
    {
        $key = env('GEMINI_API_KEY');
        if (empty($key)) throw new \RuntimeException('No Gemini key');

        // Convert OpenAI format to Gemini format
        $contents = [];
        $systemText = '';
        foreach ($messages as $msg) {
            if ($msg['role'] === 'system') {
                $systemText = $msg['content'];
                continue;
            }
            $contents[] = [
                'role'  => $msg['role'] === 'assistant' ? 'model' : 'user',
                'parts' => [['text' => $msg['content']]],
            ];
        }

        $body = [
            'contents' => $contents,
            'generationConfig' => ['maxOutputTokens' => 800, 'temperature' => 0.7],
        ];

        if ($systemText) {
            $body['systemInstruction'] = ['parts' => [['text' => $systemText]]];
        }

        $response = Http::withHeaders(['Content-Type' => 'application/json'])
            ->timeout(30)
            ->post("https://generativelanguage.googleapis.com/v1beta/models/gemini-1.5-flash:generateContent?key={$key}", $body);

        if ($response->failed()) throw new \RuntimeException('Gemini API error: ' . $response->body());

        return $response->json('candidates.0.content.parts.0.text') ?? '';
    }

    private function callOpenAI(array $messages): string
    {
        $key = env('OPENAI_API_KEY');
        if (empty($key)) throw new \RuntimeException('No OpenAI key');

        $response = Http::withToken($key)
            ->timeout(30)
            ->post('https://api.openai.com/v1/chat/completions', [
                'model'       => 'gpt-4o-mini',
                'messages'    => $messages,
                'max_tokens'  => 800,
                'temperature' => 0.7,
            ]);

        if ($response->failed()) throw new \RuntimeException('OpenAI API error: ' . $response->body());

        return $response->json('choices.0.message.content') ?? '';
    }

    private function callGroq(array $messages): string
    {
        $key = env('GROQ_API_KEY');
        if (empty($key)) throw new \RuntimeException('No Groq key');

        $response = Http::withToken($key)
            ->timeout(30)
            ->post('https://api.groq.com/openai/v1/chat/completions', [
                'model'       => 'llama3-8b-8192',
                'messages'    => $messages,
                'max_tokens'  => 800,
                'temperature' => 0.7,
            ]);

        if ($response->failed()) throw new \RuntimeException('Groq API error: ' . $response->body());

        return $response->json('choices.0.message.content') ?? '';
    }

    // ── Fallback Text Search ───────────────────────────────────────────────

    private function fallbackTextContext(?int $entityId): array
    {
        $query = RagDocument::where('is_active', true)
            ->select(['id', 'title', 'content']);

        if ($entityId) {
            $query->forEntity($entityId);
        }

        return $query->take(3)->get()
            ->map(fn ($doc) => [
                'id'      => $doc->id,
                'title'   => $doc->title,
                'content' => mb_substr($doc->content, 0, 500),
                'score'   => 0.0,
                'source'  => 'fallback',
            ])
            ->toArray();
    }
}
