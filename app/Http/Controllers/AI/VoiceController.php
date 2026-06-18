<?php

namespace App\Http\Controllers\AI;

use App\Http\Controllers\Controller;
use App\Models\AiConversation;
use App\Models\AiMessage;
use App\Models\VoiceLog;
use App\Services\AI\ChatbotService;
use App\Services\AI\SarvamAIService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class VoiceController extends Controller
{
    public function __construct(
        private readonly SarvamAIService $sarvam,
        private readonly ChatbotService  $chatbot,
    ) {}

    // ── Speech-to-Text ─────────────────────────────────────────────────────

    /**
     * POST /api/ai/speech-to-text
     *
     * Upload an audio file and receive a text transcript.
     * Supported formats: wav, mp3, ogg, webm, m4a
     */
    public function speechToText(Request $request): JsonResponse
    {
        $request->validate([
            'audio'    => 'required|file|mimes:wav,mp3,ogg,webm,m4a,flac|max:10240', // 10 MB max
            'language' => 'nullable|string|max:10',
        ]);

        $file     = $request->file('audio');
        $language = $request->input('language', 'unknown');
        $userId   = auth()->id();

        // Store the uploaded audio temporarily
        $storedPath = $file->store('ai/voice/uploads', 'local');
        $fullPath   = Storage::disk('local')->path($storedPath);

        $voiceLog = null;

        try {
            $result = $this->sarvam->speechToText($fullPath, $language);

            $voiceLog = VoiceLog::create([
                'user_id'          => $userId,
                'type'             => 'stt',
                'provider'         => 'sarvam',
                'language'         => $result['language'],
                'input_audio_path' => $storedPath,
                'transcript'       => $result['transcript'],
                'status'           => 'success',
                'duration_ms'      => null,
            ]);

            return response()->json([
                'success'    => true,
                'transcript' => $result['transcript'],
                'language'   => $result['language'],
                'detected'   => $result['detected'],
                'log_id'     => $voiceLog->id,
            ]);

        } catch (\Exception $e) {
            Log::error('STT Error', ['message' => $e->getMessage()]);

            VoiceLog::create([
                'user_id'          => $userId,
                'type'             => 'stt',
                'provider'         => 'sarvam',
                'language'         => $language,
                'input_audio_path' => $storedPath,
                'status'           => 'failed',
                'error'            => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'error'   => 'Speech recognition failed. Please try again or type your message.',
            ], 422);
        }
    }

    // ── Text-to-Speech ─────────────────────────────────────────────────────

    /**
     * POST /api/ai/text-to-speech
     *
     * Convert text to speech. Returns base64 WAV audio.
     */
    public function textToSpeech(Request $request): JsonResponse
    {
        $request->validate([
            'text'     => 'required|string|max:2000',
            'language' => 'nullable|string|max:10',
            'voice'    => 'nullable|string|max:50',
        ]);

        // Return 503 early when Sarvam API key is not configured so the
        // frontend can detect this specific condition and fall back to
        // the browser's built-in Web Speech API.
        if (!$this->sarvam->isAvailable()) {
            return response()->json([
                'success'          => false,
                'error'            => 'Text-to-speech service is not configured.',
                'fallback_to_browser' => true,
            ], 503);
        }

        $text     = $request->input('text');
        $language = $request->input('language', 'en-IN');
        $voice    = $request->input('voice');
        $userId   = auth()->id();

        try {
            $result = $this->sarvam->textToSpeech($text, $language, $voice);

            VoiceLog::create([
                'user_id'    => $userId,
                'type'       => 'tts',
                'provider'   => 'sarvam',
                'language'   => $language,
                'input_text' => $text,
                'status'     => 'success',
            ]);

            return response()->json([
                'success'      => true,
                'audio_base64' => $result['audio_base64'],
                'content_type' => $result['content_type'],
                'language'     => $language,
            ]);

        } catch (\Exception $e) {
            Log::error('TTS Error', ['message' => $e->getMessage()]);

            VoiceLog::create([
                'user_id'    => $userId,
                'type'       => 'tts',
                'provider'   => 'sarvam',
                'language'   => $language,
                'input_text' => $text,
                'status'     => 'failed',
                'error'      => $e->getMessage(),
            ]);

            return response()->json([
                'success'          => false,
                'error'            => 'Text-to-speech conversion failed.',
                'fallback_to_browser' => true,
            ], 422);
        }
    }

    // ── Voice Chat (Full Round-Trip) ───────────────────────────────────────

    /**
     * POST /api/ai/voice-chat
     *
     * Full voice round-trip: Audio → STT → AI → TTS → Audio
     * Requires an active conversation session.
     */
    public function voiceChat(Request $request): JsonResponse
    {
        $request->validate([
            'audio'         => 'required|file|mimes:wav,mp3,ogg,webm,m4a|max:10240',
            'session_token' => 'nullable|string|max:64',
            'language'      => 'nullable|string|max:10',
            'tts_enabled'   => 'nullable|boolean',
        ]);

        $file       = $request->file('audio');
        $language   = $request->input('language', 'unknown');
        $ttsEnabled = $request->boolean('tts_enabled', true);
        $userId     = auth()->id();

        // Store audio
        $storedPath = $file->store('ai/voice/uploads', 'local');
        $fullPath   = Storage::disk('local')->path($storedPath);

        try {
            // Step 1: STT
            $sttResult   = $this->sarvam->speechToText($fullPath, $language);
            $transcript  = $sttResult['transcript'];
            $detectedLang = $sttResult['language'];

            if (empty(trim($transcript))) {
                return response()->json([
                    'success' => false,
                    'error'   => 'Could not understand the audio. Please speak clearly and try again.',
                ], 422);
            }

            // Step 2: Resolve conversation
            $conversation = $this->resolveConversation($request, $detectedLang);
            $entityId     = $this->resolveEntityId();

            // Step 3: Chat
            $chatResult = $this->chatbot->chat($conversation, $transcript, $entityId);
            $replyText  = $chatResult['reply'];

            // Step 4: TTS (if enabled)
            $audioBase64 = null;
            $audioType   = null;

            if ($ttsEnabled && $this->sarvam->isAvailable()) {
                $ttsLanguage = in_array($detectedLang, SarvamAIService::SUPPORTED_LANGUAGES)
                    ? $detectedLang
                    : 'en-IN';

                try {
                    $ttsResult   = $this->sarvam->textToSpeech($replyText, $ttsLanguage);
                    $audioBase64 = $ttsResult['audio_base64'];
                    $audioType   = $ttsResult['content_type'];

                    VoiceLog::create([
                        'conversation_id' => $conversation->id,
                        'user_id'         => $userId,
                        'type'            => 'tts',
                        'provider'        => 'sarvam',
                        'language'        => $ttsLanguage,
                        'input_text'      => $replyText,
                        'status'          => 'success',
                    ]);
                } catch (\Exception $e) {
                    Log::warning('Voice chat TTS failed (non-fatal)', ['error' => $e->getMessage()]);
                }
            }

            return response()->json([
                'success'       => true,
                'session_token' => $conversation->session_token,
                'transcript'    => $transcript,
                'reply'         => $replyText,
                'audio_base64'  => $audioBase64,
                'audio_type'    => $audioType,
                'language'      => $detectedLang,
                'provider'      => $chatResult['provider'],
            ]);

        } catch (\Exception $e) {
            Log::error('Voice chat error', ['message' => $e->getMessage()]);

            return response()->json([
                'success' => false,
                'error'   => 'Voice processing failed. Please try again.',
            ], 500);
        }
    }

    // ── Voice Logs ─────────────────────────────────────────────────────────

    /**
     * GET /api/ai/voice-history
     *
     * Returns the authenticated user's voice interaction history.
     */
    public function voiceHistory(Request $request): JsonResponse
    {
        $logs = VoiceLog::where('user_id', auth()->id())
            ->orderByDesc('created_at')
            ->paginate(20);

        return response()->json($logs);
    }

    // ── Private Helpers ────────────────────────────────────────────────────

    private function resolveConversation(Request $request, string $language): AiConversation
    {
        $token = $request->input('session_token');

        if ($token) {
            $existing = AiConversation::where('session_token', $token)->first();
            if ($existing) return $existing;
        }

        return AiConversation::create([
            'session_token' => AiConversation::generateToken(),
            'channel'       => 'voice',
            'language'      => $language,
            'entity_id'     => $this->resolveEntityId(),
            'user_id'       => auth()->id(),
            'status'        => 'active',
        ]);
    }

    private function resolveEntityId(): ?int
    {
        $plantId = session('active_plant_id') ?: auth()->user()?->default_plant_id;
        if ($plantId) {
            $plant = \App\Models\Plant::find($plantId);
            return $plant?->entity_id;
        }
        return null;
    }
}
