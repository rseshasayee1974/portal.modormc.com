<?php

namespace App\Services\Sarvam;

use App\Services\Sarvam\Exceptions\SarvamApiException;
use Illuminate\Support\Facades\Log;

class TamilChatService
{
    private const TAMIL_THRESHOLD = 0.85;
    private const FALLBACK_REPLY = 'மன்னிக்கவும், என்னால் இப்போது தமிழ் மொழியில் சரியாக பதிலளிக்க முடியவில்லை. தயவுசெய்து மீண்டும் முயற்சிக்கவும்.';

    public function __construct(
        private readonly SarvamClient $client,
        private readonly TamilNormalizer $normalizer
    ) {}

    /**
     * Run chat completions with Tamil-only validation and retry/fallback guards.
     *
     * @param array $messages
     * @param string $model
     * @return string
     * @throws SarvamApiException
     */
    public function chat(array $messages, string $model = 'sarvam-30b'): string
    {
        // 1. Normalize the latest user message in the history
        $messages = $this->normalizeLastUserMessage($messages);

        // 2. Enforce system prompt to command the AI to respond in Tamil ONLY
        $messages = $this->ensureSystemPrompt($messages);

        try {
            // 3. First attempt
            $result = $this->client->chat($messages, $model);
            $reply = $result->content;

            if ($this->isValidTamil($reply)) {
                return $reply;
            }

            Log::warning('TamilChatService: First attempt failed Tamil-only validation', [
                'reply' => $reply,
                'model' => $model,
            ]);

            // 4. Retry once with a sharpened prompt
            $retryMessages = $messages;
            $retryMessages[] = ['role' => 'assistant', 'content' => $reply];
            $retryMessages[] = [
                'role' => 'user',
                'content' => 'Your previous response contained English words or characters. Rewrite it completely in native Tamil script only. Zero English words or letters allowed. உங்களது முந்தைய பதில் ஆங்கிலக் கலப்புடன் இருந்தது. தயவுசெய்து ஆங்கில வார்த்தைகள் அல்லது எழுத்துக்கள் எதையும் பயன்படுத்தாமல், முழுமையாக தூய தமிழில் மட்டுமே மீண்டும் பதிலளிக்கவும்.'
            ];

            $retryResult = $this->client->chat($retryMessages, $model);
            $retryReply = $retryResult->content;

            if ($this->isValidTamil($retryReply)) {
                return $retryReply;
            }

            Log::error('TamilChatService: Retry attempt also failed Tamil-only validation', [
                'retryReply' => $retryReply,
                'model' => $model,
            ]);

        } catch (SarvamApiException $e) {
            Log::error('TamilChatService: Sarvam API error during chat', [
                'error' => $e->getMessage(),
                'response' => $e->responseBody,
            ]);
            // Re-throw or fall back? Since it's a hard product constraint to respond in Tamil,
            // returning fallback reply is safer than returning blank or crashed JSON.
            return self::FALLBACK_REPLY;
        } catch (\Throwable $e) {
            Log::error('TamilChatService: Unexpected error during chat', [
                'error' => $e->getMessage(),
            ]);
            return self::FALLBACK_REPLY;
        }

        // 5. Fallback if both attempts failed validation
        return self::FALLBACK_REPLY;
    }

    /**
     * Normalize the latest user message's content if present.
     */
    private function normalizeLastUserMessage(array $messages): array
    {
        for ($i = count($messages) - 1; $i >= 0; $i--) {
            if (($messages[$i]['role'] ?? '') === 'user') {
                $messages[$i]['content'] = $this->normalizer->normalize($messages[$i]['content'] ?? '');
                break;
            }
        }
        return $messages;
    }

    /**
     * Ensure a system prompt is present to instruct the LLM to respond in Tamil ONLY.
     */
    private function ensureSystemPrompt(array $messages): array
    {
        $systemInstruction = 'You are a helpful customer support assistant for ModoRMC. You MUST respond in Tamil ONLY. Do not use English words, Latin script, or English letters in your replies. Always write using the native Tamil script. If you must use technical terms, write them phonetically in native Tamil characters.';

        // Find existing system prompt
        $systemIndex = -1;
        foreach ($messages as $idx => $msg) {
            if (($msg['role'] ?? '') === 'system') {
                $systemIndex = $idx;
                break;
            }
        }

        if ($systemIndex !== -1) {
            // Append instruction to existing system prompt
            $messages[$systemIndex]['content'] .= "\n\nCRITICAL CONSTRAINT: " . $systemInstruction;
        } else {
            // Prepend new system prompt
            array_unshift($messages, [
                'role' => 'system',
                'content' => $systemInstruction
            ]);
        }

        return $messages;
    }

    /**
     * Check if the response meets the Tamil character ratio threshold (>= 0.85).
     */
    public function isValidTamil(string $text): bool
    {
        $trimmed = trim($text);
        if ($trimmed === '') {
            return true;
        }

        // Count Tamil characters (U+0B80 to U+0BFF)
        $tamilCount = preg_match_all('/[\x{0B80}-\x{0BFF}]/u', $trimmed);

        // Count Latin/English alphabetic characters (a-z, A-Z)
        $latinCount = preg_match_all('/[a-zA-Z]/u', $trimmed);

        $totalLetters = $tamilCount + $latinCount;
        if ($totalLetters === 0) {
            return true; // No alphabetic letters present (e.g. only numbers, punctuation)
        }

        $ratio = $tamilCount / $totalLetters;

        return $ratio >= self::TAMIL_THRESHOLD;
    }
}
