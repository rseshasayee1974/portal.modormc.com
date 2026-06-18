<?php

namespace App\Http\Controllers\AI;

use App\Ai\Agents\InternalAssistant;
use App\Http\Controllers\Controller;
use App\Models\AgentChatHistory;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * AssistantController — Internal staff AI assistant endpoint.
 *
 * Authenticated endpoint only. Reuses the same promptAgentSafely
 * pattern from AgentBuilderController for provider fallback.
 */
class AssistantController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth:sanctum');
    }

    /**
     * POST /api/ai/assistant
     *
     * Send a message to the internal AI assistant.
     */
    public function chat(Request $request): JsonResponse
    {
        $request->validate([
            'message'    => 'required|string|max:4000',
            'history_id' => 'nullable|integer',   // existing session to continue
            'messages'   => 'nullable|array',      // conversation history from frontend
        ]);

        $prompt     = $request->input('message');
        $historyId  = $request->input('history_id');
        $frontMessages = $request->input('messages', []);

        try {
            $agent = new InternalAssistant();

            // Build context string with system + previous messages
            $contextPrompt = $this->buildContextualPrompt($prompt, $frontMessages);

            $result   = $this->promptAgentSafely($agent, $contextPrompt);
            $response = $result['response'];
            $provider = $result['provider'];

            $reply = $response->text ?? 'No response returned.';

            return response()->json([
                'success'  => true,
                'reply'    => $reply,
                'provider' => $provider,
            ]);

        } catch (\Exception $e) {
            Log::error('AssistantController chat error', [
                'message' => $e->getMessage(),
                'user_id' => auth()->id(),
            ]);

            return response()->json([
                'success' => false,
                'error'   => 'AI assistant is temporarily unavailable. Please try again.',
            ], 500);
        }
    }

    /**
     * POST /api/ai/assistant/save-history
     *
     * Save the current assistant conversation to history.
     */
    public function saveHistory(Request $request): JsonResponse
    {
        $request->validate([
            'messages'   => 'required|array|min:1',
            'history_id' => 'nullable|integer',
        ]);

        $messages = $request->input('messages');
        $userId   = auth()->id();
        $plantId  = session('active_plant_id') ?: auth()->user()?->default_plant_id;

        $firstUser  = collect($messages)->firstWhere('role', 'user');
        $firstAgent = collect($messages)->firstWhere('role', 'assistant');
        $summary    = trim(
            ($firstUser  ? Str::limit($firstUser['content'] ?? '', 120) . ' | ' : '') .
            ($firstAgent ? Str::limit(strip_tags($firstAgent['content'] ?? ''), 160) : '')
        );

        if ($historyId = $request->input('history_id')) {
            $history = AgentChatHistory::where('id', $historyId)->where('user_id', $userId)->first();
            if ($history) {
                $history->update([
                    'messages'      => $messages,
                    'message_count' => count($messages),
                    'summary'       => $summary ?: null,
                ]);
                return response()->json(['success' => true, 'id' => $history->id]);
            }
        }

        $history = AgentChatHistory::create([
            'user_id'          => $userId,
            'plant_id'         => $plantId,
            'agent_name'       => 'InternalAssistant',
            'agent_class'      => InternalAssistant::class,
            'session_language' => 'en',
            'messages'         => $messages,
            'message_count'    => count($messages),
            'summary'          => $summary ?: null,
        ]);

        return response()->json(['success' => true, 'id' => $history->id]);
    }

    /**
     * GET /api/ai/assistant/history
     *
     * Returns the assistant conversation history for the authenticated user.
     */
    public function history(Request $request): JsonResponse
    {
        $histories = AgentChatHistory::where('user_id', auth()->id())
            ->where('agent_name', 'InternalAssistant')
            ->orderByDesc('created_at')
            ->paginate(20);

        return response()->json($histories);
    }

    // ── Private Helpers ────────────────────────────────────────────────────

    private function buildContextualPrompt(string $prompt, array $messages): string
    {
        if (empty($messages)) {
            return $prompt;
        }

        // Include last 6 message pairs for context
        $recent  = array_slice($messages, -12);
        $context = "Previous conversation:\n";

        foreach ($recent as $msg) {
            $role    = $msg['role'] === 'assistant' ? 'Assistant' : 'User';
            $content = $msg['content'] ?? '';
            $context .= "{$role}: {$content}\n";
        }

        return $context . "\nUser: " . $prompt;
    }

    private function promptAgentSafely($agent, string $prompt): array
    {
        $originalDefault = config('ai.default');
        $chain           = config('ai.chain', ['gemini', 'openai']);

        if (($key = array_search($originalDefault, $chain)) !== false) {
            unset($chain[$key]);
        }
        array_unshift($chain, $originalDefault);
        $chain = array_values(array_filter(array_unique($chain)));

        $originalKeys    = [];
        $lastException   = null;

        foreach ($chain as $provider) {
            $originalKeys[$provider] = config("ai.providers.{$provider}.key");
        }

        foreach ($chain as $index => $provider) {
            $provider  = trim($provider);
            if (empty($provider)) continue;

            $keyString = $originalKeys[$provider] ?? '';
            $keys      = array_values(array_filter(array_map('trim', explode(',', $keyString))));

            if (empty($keys) && $provider !== 'ollama') continue;
            if (empty($keys) && $provider === 'ollama') $keys = [''];

            foreach ($keys as $keyIndex => $singleKey) {
                try {
                    config(['ai.default' => $provider]);
                    if ($provider !== 'ollama') {
                        config(["ai.providers.{$provider}.key" => $singleKey]);
                    }

                    $response = $agent->prompt($prompt);

                    config(['ai.default' => $originalDefault]);
                    foreach ($originalKeys as $p => $k) {
                        config(["ai.providers.{$p}.key" => $k]);
                    }

                    return ['response' => $response, 'provider' => $provider];

                } catch (\Exception $e) {
                    $lastException = $e;
                    Log::warning("AssistantController: provider [{$provider}] failed", ['error' => $e->getMessage()]);
                }
            }
        }

        config(['ai.default' => $originalDefault]);
        foreach ($originalKeys as $p => $k) {
            config(["ai.providers.{$p}.key" => $k]);
        }

        throw $lastException ?: new \Exception('All AI providers failed.');
    }
}
