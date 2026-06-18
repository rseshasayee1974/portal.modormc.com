<?php

namespace App\Services\AI;

use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

/**
 * Sarvam AI Service — Speech-to-Text and Text-to-Speech.
 *
 * Sarvam AI supports Indian languages: Hindi, Tamil, Telugu, Malayalam,
 * Kannada, Bengali, Gujarati, Marathi, and Odia.
 *
 * API Docs: https://docs.sarvam.ai
 */
class SarvamAIService
{
    private const BASE_URL = 'https://api.sarvam.ai';

    /** Supported BCP-47 language codes for Sarvam AI */
    public const SUPPORTED_LANGUAGES = [
        'en-IN', // English (India)
        'hi-IN', // Hindi
        'ta-IN', // Tamil
        'te-IN', // Telugu
        'ml-IN', // Malayalam
        'kn-IN', // Kannada
        'bn-IN', // Bengali
        'gu-IN', // Gujarati
        'mr-IN', // Marathi
        'od-IN', // Odia
    ];

    /** TTS voice options per language */
    public const VOICES = [
        'en-IN' => 'shubh',
        'hi-IN' => 'shubh',
        'ta-IN' => 'kavitha',
        'te-IN' => 'shubh',
        'kn-IN' => 'shubh',
        'ml-IN' => 'shubh',
    ];

    private ?string $apiKey;

    public function __construct()
    {
        $this->apiKey = config('services.sarvam.key') ?: env('SARVAM_API_KEY');
    }

    public function isAvailable(): bool
    {
        return !empty($this->apiKey);
    }

    // ── Speech-to-Text ─────────────────────────────────────────────────────

    /**
     * Convert an audio file to text using Sarvam AI STT.
     *
     * @param  string  $filePath  Absolute path to the audio file
     * @param  string  $language  BCP-47 code (e.g. 'hi-IN', 'ta-IN'). 'unknown' = auto-detect.
     * @return array{transcript: string, language: string, detected: bool}
     */
    public function speechToText(string $filePath, string $language = 'unknown'): array
    {
        if (!$this->isAvailable()) {
            throw new \RuntimeException('Sarvam AI key not configured. Set SARVAM_API_KEY in .env');
        }

        if (!file_exists($filePath)) {
            throw new \InvalidArgumentException("Audio file not found: {$filePath}");
        }

        try {
            $response = Http::withHeaders([
                'api-subscription-key' => $this->apiKey,
            ])
            ->timeout(60)
            ->attach('file', file_get_contents($filePath), basename($filePath))
            ->post(self::BASE_URL . '/speech-to-text', [
                'language_code' => $language !== 'unknown' ? $language : null,
                'model'         => 'saarika:v2.5',
                'with_timestamps' => false,
            ]);

            if ($response->failed()) {
                Log::error('Sarvam STT failed', [
                    'status' => $response->status(),
                    'body'   => $response->body(),
                ]);
                throw new \RuntimeException('Sarvam STT API error: ' . $response->body());
            }

            $data = $response->json();

            return [
                'transcript' => $data['transcript'] ?? '',
                'language'   => $data['language_code'] ?? $language,
                'detected'   => ($language === 'unknown'),
            ];

        } catch (\Illuminate\Http\Client\RequestException $e) {
            Log::error('Sarvam STT request exception', ['message' => $e->getMessage()]);
            throw new \RuntimeException('Sarvam STT network error: ' . $e->getMessage());
        }
    }

    // ── Text-to-Speech ─────────────────────────────────────────────────────

    /**
     * Convert text to speech using Sarvam AI TTS.
     *
     * @param  string  $text      Text to synthesize (max 500 chars per request)
     * @param  string  $language  BCP-47 language code
     * @param  string|null  $voice  Voice name override
     * @return array{audio_base64: string, content_type: string}
     */
    public function textToSpeech(string $text, string $language = 'en-IN', ?string $voice = null): array
    {
        if (!$this->isAvailable()) {
            throw new \RuntimeException('Sarvam AI key not configured. Set SARVAM_API_KEY in .env');
        }

        $text = mb_substr(trim($text), 0, 2000);
        if (empty($text)) {
            throw new \InvalidArgumentException('Text cannot be empty for TTS.');
        }

        // Split into chunks of 500 chars (Sarvam limit)
        $chunks   = $this->splitIntoChunks($text, 500);
        $audioData = [];

        foreach ($chunks as $chunk) {
            $data = $this->synthesizeChunk($chunk, $language, $voice);
            $audioData[] = $data;
        }

        // Return the first chunk audio or a combined base64
        // Sarvam returns base64-encoded WAV audio
        $combined = count($audioData) === 1
            ? $audioData[0]['audios'][0]
            : $this->combineAudioBase64($audioData);

        return [
            'audio_base64' => $combined,
            'content_type' => 'audio/wav',
        ];
    }

    /**
     * Save TTS audio to storage and return the file path.
     */
    public function textToSpeechFile(string $text, string $language = 'en-IN', ?string $voice = null): string
    {
        $result   = $this->textToSpeech($text, $language, $voice);
        $decoded  = base64_decode($result['audio_base64']);
        $filename = 'ai/tts/' . uniqid('tts_', true) . '.wav';

        Storage::disk('local')->put($filename, $decoded);

        return $filename;
    }

    // ── Language Detection ─────────────────────────────────────────────────

    /**
     * Detect the spoken language from an audio file.
     *
     * @param  string  $filePath  Absolute path to the audio file
     * @return string  BCP-47 language code detected
     */
    public function detectLanguage(string $filePath): string
    {
        $result = $this->speechToText($filePath, 'unknown');
        return $result['language'] ?? 'en-IN';
    }

    // ── Private Helpers ────────────────────────────────────────────────────

    private function synthesizeChunk(string $text, string $language, ?string $voice): array
    {
        $selectedVoice = $voice ?? (self::VOICES[$language] ?? 'shubh');

        $response = Http::withHeaders([
            'api-subscription-key' => $this->apiKey,
            'Content-Type'         => 'application/json',
        ])
        ->timeout(30)
        ->post(self::BASE_URL . '/text-to-speech', [
            'inputs'         => [$text],
            'target_language_code' => $language,
            'speaker'        => $selectedVoice,
            'pace'           => 1.0,
            'speech_sample_rate' => 8000,
            'enable_preprocessing' => true,
            'model'          => 'bulbul:v3',
        ]);

        if ($response->failed()) {
            Log::error('Sarvam TTS failed', [
                'status' => $response->status(),
                'body'   => $response->body(),
            ]);
            throw new \RuntimeException('Sarvam TTS API error: ' . $response->body());
        }

        return $response->json();
    }

    private function splitIntoChunks(string $text, int $maxChars): array
    {
        if (mb_strlen($text) <= $maxChars) {
            return [$text];
        }

        $chunks    = [];
        $sentences = preg_split('/(?<=[.!?।])\s+/', $text);
        $current   = '';

        foreach ($sentences as $sentence) {
            if (mb_strlen($current . ' ' . $sentence) > $maxChars) {
                if ($current !== '') {
                    $chunks[] = trim($current);
                }
                $current = $sentence;
            } else {
                $current .= ($current ? ' ' : '') . $sentence;
            }
        }

        if ($current !== '') {
            $chunks[] = trim($current);
        }

        return $chunks ?: [$text];
    }

    private function combineAudioBase64(array $audioData): string
    {
        // For simplicity, return the first chunk's audio
        // (Proper audio concatenation requires binary WAV header manipulation)
        return $audioData[0]['audios'][0] ?? '';
    }
}
