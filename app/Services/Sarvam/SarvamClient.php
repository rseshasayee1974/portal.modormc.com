<?php

namespace App\Services\Sarvam;

use App\Services\Sarvam\DTO\ChatCompletionResult;
use App\Services\Sarvam\DTO\TtsResult;
use App\Services\Sarvam\DTO\TranscriptionResult;
use App\Services\Sarvam\DTO\TransliterationResult;
use App\Services\Sarvam\Exceptions\SarvamApiException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;

class SarvamClient
{
    private const BASE_URL = 'https://api.sarvam.ai';

    private ?string $apiKey;

    public function __construct()
    {
        $this->apiKey = config('services.sarvam.api_key') ?: config('services.sarvam.key');
    }

    /**
     * Transcribe batch audio using saarika:v2.5.
     *
     * @param string $filePath
     * @param string $languageCode
     * @return TranscriptionResult
     * @throws SarvamApiException
     */
    public function transcribe(string $filePath, string $languageCode = 'ta-IN'): TranscriptionResult
    {
        $this->ensureApiKey();

        if (!file_exists($filePath)) {
            throw new \InvalidArgumentException("Audio file not found: {$filePath}");
        }

        $response = Http::withHeaders([
            'api-subscription-key' => $this->apiKey,
        ])
        ->attach('file', file_get_contents($filePath), basename($filePath))
        ->post(self::BASE_URL . '/speech-to-text', [
            'language_code' => $languageCode,
            'model' => 'saarika:v2.5',
            'with_timestamps' => false,
        ]);

        $this->checkResponse($response, 'Transcription failed');

        $data = $response->json();

        return new TranscriptionResult(
            transcript: $data['transcript'] ?? '',
            languageCode: $data['language_code'] ?? $languageCode,
            requestId: $data['request_id'] ?? null
        );
    }

    /**
     * Chat completions using sarvam-30b or sarvam-105b.
     *
     * @param array $messages
     * @param string $model
     * @return ChatCompletionResult
     * @throws SarvamApiException
     */
    public function chat(array $messages, string $model = 'sarvam-30b'): ChatCompletionResult
    {
        $this->ensureApiKey();

        $response = Http::withHeaders([
            'api-subscription-key' => $this->apiKey,
            'Content-Type' => 'application/json',
        ])
        ->post(self::BASE_URL . '/v1/chat/completions', [
            'model' => $model,
            'messages' => $messages,
            'stream' => false,
        ]);

        $this->checkResponse($response, 'Chat completion failed');

        $data = $response->json();
        $choice = $data['choices'][0] ?? [];
        $message = $choice['message'] ?? [];
        $usage = $data['usage'] ?? [];

        return new ChatCompletionResult(
            content: $message['content'] ?? '',
            role: $message['role'] ?? 'assistant',
            promptTokens: $usage['prompt_tokens'] ?? null,
            completionTokens: $usage['completion_tokens'] ?? null
        );
    }

    /**
     * Text to Speech using bulbul:v3.
     *
     * @param string $text
     * @param string $languageCode
     * @param string|null $voice
     * @return TtsResult
     * @throws SarvamApiException
     */
    public function textToSpeech(string $text, string $languageCode = 'ta-IN', ?string $voice = null): TtsResult
    {
        $this->ensureApiKey();

        $selectedVoice = $voice ?? ($languageCode === 'ta-IN' ? 'kavitha' : 'shubh');

        $response = Http::withHeaders([
            'api-subscription-key' => $this->apiKey,
            'Content-Type' => 'application/json',
        ])
        ->post(self::BASE_URL . '/text-to-speech', [
            'inputs' => [$text],
            'target_language_code' => $languageCode,
            'speaker' => $selectedVoice,
            'pace' => 1.0,
            'speech_sample_rate' => 8000,
            'enable_preprocessing' => true,
            'model' => 'bulbul:v3',
        ]);

        $this->checkResponse($response, 'Text-to-speech synthesis failed');

        $data = $response->json();
        $audioBase64 = $data['audios'][0] ?? '';

        return new TtsResult(
            audioBase64: $audioBase64,
            contentType: 'audio/wav'
        );
    }

    /**
     * Transliterate text (e.g. Tanglish → native Tamil).
     *
     * @param string $text
     * @param string $sourceLanguageCode
     * @param string $targetLanguageCode
     * @return TransliterationResult
     * @throws SarvamApiException
     */
    public function transliterate(string $text, string $sourceLanguageCode = 'en-IN', string $targetLanguageCode = 'ta-IN'): TransliterationResult
    {
        $this->ensureApiKey();

        $response = Http::withHeaders([
            'api-subscription-key' => $this->apiKey,
            'Content-Type' => 'application/json',
        ])
        ->post(self::BASE_URL . '/transliterate', [
            'input' => $text,
            'source_language_code' => $sourceLanguageCode,
            'target_language_code' => $targetLanguageCode,
        ]);

        $this->checkResponse($response, 'Transliteration failed');

        $data = $response->json();

        return new TransliterationResult(
            transliteratedText: $data['transliterated_text'] ?? '',
            sourceLanguageCode: $data['source_language_code'] ?? $sourceLanguageCode,
            requestId: $data['request_id'] ?? null
        );
    }

    private function ensureApiKey(): void
    {
        if (empty($this->apiKey)) {
            throw new \RuntimeException('Sarvam AI Key is not configured. Set SARVAM_API_KEY in .env');
        }
    }

    private function checkResponse(Response $response, string $errorMessage): void
    {
        if ($response->failed()) {
            throw new SarvamApiException(
                $errorMessage . ': HTTP status ' . $response->status(),
                $response->status(),
                $response->body()
            );
        }
    }
}
