<?php

namespace App\Http\Controllers\AI;

use App\Http\Controllers\Controller;
use App\Models\AiConversation;
use App\Services\AI\ChatbotService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ChatbotController extends Controller
{
    public function __construct(
        private readonly ChatbotService $chatbotService,
    ) {}

    // ── Start or Resume Conversation ───────────────────────────────────────

    /**
     * POST /api/ai/chat
     *
     * Creates a new conversation or continues an existing one.
     * Rate limited to 30 req/min per IP.
     */
    public function chat(Request $request): JsonResponse
    {
        $request->validate([
            'message'        => 'required|string|max:2000',
            'session_token'  => 'nullable|string|max:64',
            'language'       => 'nullable|string|max:10',
            'customer_name'  => 'nullable|string|max:100',
            'customer_email' => 'nullable|email|max:150',
        ]);

        try {
            // Resolve or create conversation
            $conversation = $this->resolveConversation($request);

            // Determine entity ID for RAG scoping
            $entityId = $this->resolveEntityId($request);

            // Generate response
            $result = $this->chatbotService->chat(
                conversation: $conversation,
                userMessage:  $request->input('message'),
                entityId:     $entityId,
            );

            return response()->json([
                'success'       => true,
                'session_token' => $conversation->session_token,
                'reply'         => $result['reply'],
                'provider'      => $result['provider'],
                'message_id'    => $result['message_id'],
                'rag_sources'   => $result['rag_sources'],
                'is_escalated'  => $conversation->is_escalated,
            ]);

        } catch (\Exception $e) {
            Log::error('Chatbot error', ['message' => $e->getMessage(), 'trace' => $e->getTraceAsString()]);

            return response()->json([
                'success' => false,
                'error'   => 'I\'m having trouble connecting to my AI services. Please try again.',
            ], 500);
        }
    }

    // ── Get Conversation History ───────────────────────────────────────────

    /**
     * GET /api/ai/history?session_token=xxx
     */
    public function history(Request $request): JsonResponse
    {
        $request->validate([
            'session_token' => 'required|string|max:64',
        ]);

        $conversation = AiConversation::where('session_token', $request->session_token)
            ->with(['messages' => fn ($q) => $q->orderBy('created_at')->select([
                'id', 'conversation_id', 'role', 'content', 'created_at', 'provider',
            ])])
            ->first();

        if (!$conversation) {
            return response()->json(['success' => false, 'error' => 'Conversation not found'], 404);
        }

        return response()->json([
            'success'      => true,
            'conversation' => [
                'id'           => $conversation->id,
                'language'     => $conversation->language,
                'is_escalated' => $conversation->is_escalated,
                'status'       => $conversation->status,
            ],
            'messages' => $conversation->messages,
        ]);
    }

    // ── Escalation ─────────────────────────────────────────────────────────

    /**
     * POST /api/ai/chat/escalate
     */
    public function escalate(Request $request): JsonResponse
    {
        $request->validate([
            'session_token' => 'required|string|max:64',
            'reason'        => 'nullable|string|max:500',
        ]);

        $conversation = AiConversation::where('session_token', $request->session_token)->first();

        if (!$conversation) {
            return response()->json(['success' => false, 'error' => 'Conversation not found'], 404);
        }

        $conversation->escalate();

        // Notify support via email
        $supportEmail = config('mail.support_address', config('mail.from.address'));
        $reason       = $request->input('reason', 'Customer requested human support');

        try {
            Mail::raw(
                "A customer has requested human support.\n\n" .
                "Customer: " . ($conversation->customer_name ?? 'Unknown') . "\n" .
                "Email: " . ($conversation->customer_email ?? 'N/A') . "\n" .
                "Session: {$conversation->session_token}\n" .
                "Reason: {$reason}\n" .
                "Conversation ID: {$conversation->id}",
                fn ($m) => $m->to($supportEmail)->subject('[ModoRMC] Customer Support Escalation')
            );
        } catch (\Exception $e) {
            Log::error('Escalation email failed', ['error' => $e->getMessage()]);
        }

        return response()->json([
            'success' => true,
            'message' => 'Your request has been escalated. A support agent will reach out shortly.',
        ]);
    }

    /**
     * GET /api/ai/admin/conversations
     *
     * List all public/voice chatbot sessions for admins.
     */
    public function adminConversations(Request $request): JsonResponse
    {
        $query = AiConversation::orderByDesc('updated_at');

        if ($request->has('channel')) {
            $query->where('channel', $request->channel);
        }

        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        if ($request->boolean('escalated')) {
            $query->where('is_escalated', true);
        }

        $conversations = $query->paginate(20);

        return response()->json($conversations);
    }
    /**
     * GET /api/ai/chat/frequent-questions
     *
     * Get the most frequently asked questions from users.
     */
    public function frequentQuestions(): JsonResponse
    {
        try {
            $questions = \App\Models\AiMessage::where('role', 'user')
                ->select('content', \DB::raw('count(*) as qty'))
                ->groupBy('content')
                ->orderByDesc('qty')
                ->take(6)
                ->pluck('content')
                ->toArray();

            // Filter out empty or extremely long queries (more than 60 chars) or suggestions bracket markup
            $questions = array_filter($questions, function ($q) {
                $q = trim($q);
                return !empty($q) && mb_strlen($q) <= 60 && strpos($q, '[') === false && strpos($q, ']') === false;
            });

            // Use default fallbacks if not enough dynamic questions
            if (count($questions) < 2) {
                $questions = array_unique(array_merge($questions, [
                    'Track my order',
                    'Invoice query',
                    'Contact support',
                    'Product info'
                ]));
            }

            return response()->json([
                'success'   => true,
                'questions' => array_slice(array_values($questions), 0, 4),
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success'   => false,
                'questions' => [
                    'Track my order',
                    'Invoice query',
                    'Contact support',
                    'Product info'
                ]
            ]);
        }
    }

    // ── Private Helpers ────────────────────────────────────────────────────

    private function resolveConversation(Request $request): AiConversation
    {
        $token = $request->input('session_token');

        if ($token) {
            $conversation = AiConversation::where('session_token', $token)->first();
            if ($conversation) {
                // Update language if provided
                if ($lang = $request->input('language')) {
                    $conversation->update(['language' => $lang]);
                }
                return $conversation;
            }
        }

        // Create new conversation
        return AiConversation::create([
            'session_token'  => AiConversation::generateToken(),
            'channel'        => 'chatbot',
            'language'       => $request->input('language', 'en'),
            'customer_name'  => $request->input('customer_name'),
            'customer_email' => $request->input('customer_email'),
            'entity_id'      => $this->resolveEntityId($request),
            'status'         => 'active',
        ]);
    }

    private function resolveEntityId(Request $request): ?int
    {
        // For authenticated users, get from session
        if (auth()->check()) {
            $plantId = session('active_plant_id') ?: auth()->user()?->default_plant_id;
            if ($plantId) {
                $plant = \App\Models\Plant::find($plantId);
                return $plant?->entity_id;
            }
        }

        return null;
    }
}
