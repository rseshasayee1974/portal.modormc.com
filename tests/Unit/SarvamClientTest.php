<?php

namespace Tests\Unit;

use App\Services\Sarvam\SarvamClient;
use App\Services\Sarvam\Exceptions\SarvamApiException;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class SarvamClientTest extends TestCase
{
    private SarvamClient $client;
    private string $tempFilePath;

    protected function setUp(): void
    {
        parent::setUp();
        Config::set('services.sarvam.api_key', 'test_key_123');
        $this->client = new SarvamClient();

        // Create a temporary mock audio file for testing transcribe
        $this->tempFilePath = tempnam(sys_get_temp_dir(), 'test_audio');
        file_put_contents($this->tempFilePath, 'mock_audio_data');
    }

    protected function tearDown(): void
    {
        if (file_exists($this->tempFilePath)) {
            unlink($this->tempFilePath);
        }
        parent::tearDown();
    }

    // ── Transcribe Tests ───────────────────────────────────────────────────

    public function test_transcribe_success(): void
    {
        Http::fake([
            'api.sarvam.ai/speech-to-text' => Http::response([
                'transcript' => 'வணக்கம்',
                'language_code' => 'ta-IN',
                'request_id' => 'req_001'
            ], 200)
        ]);

        $result = $this->client->transcribe($this->tempFilePath);

        $this->assertEquals('வணக்கம்', $result->transcript);
        $this->assertEquals('ta-IN', $result->languageCode);
        $this->assertEquals('req_001', $result->requestId);
    }

    public function test_transcribe_429_rate_limit(): void
    {
        Http::fake([
            'api.sarvam.ai/speech-to-text' => Http::response('Rate limit exceeded', 429)
        ]);

        $this->expectException(SarvamApiException::class);
        $this->expectExceptionCode(429);

        try {
            $this->client->transcribe($this->tempFilePath);
        } catch (SarvamApiException $e) {
            $this->assertEquals('Rate limit exceeded', $e->responseBody);
            throw $e;
        }
    }

    public function test_transcribe_4xx_validation_error(): void
    {
        Http::fake([
            'api.sarvam.ai/speech-to-text' => Http::response([
                'error' => 'Invalid model name'
            ], 422)
        ]);

        $this->expectException(SarvamApiException::class);
        $this->expectExceptionCode(422);

        $this->client->transcribe($this->tempFilePath);
    }

    public function test_transcribe_empty_or_malformed_response(): void
    {
        Http::fake([
            'api.sarvam.ai/speech-to-text' => Http::response('', 200)
        ]);

        $result = $this->client->transcribe($this->tempFilePath);

        $this->assertEquals('', $result->transcript);
        $this->assertEquals('ta-IN', $result->languageCode);
    }

    // ── Chat Tests ─────────────────────────────────────────────────────────

    public function test_chat_success(): void
    {
        Http::fake([
            'api.sarvam.ai/v1/chat/completions' => Http::response([
                'choices' => [
                    [
                        'message' => [
                            'role' => 'assistant',
                            'content' => 'வணக்கம், நான் ModoRMC AI.'
                        ]
                    ]
                ],
                'usage' => [
                    'prompt_tokens' => 15,
                    'completion_tokens' => 20
                ]
            ], 200)
        ]);

        $result = $this->client->chat([
            ['role' => 'user', 'content' => 'Hello']
        ]);

        $this->assertEquals('வணக்கம், நான் ModoRMC AI.', $result->content);
        $this->assertEquals('assistant', $result->role);
        $this->assertEquals(15, $result->promptTokens);
        $this->assertEquals(20, $result->completionTokens);
    }

    public function test_chat_429_rate_limit(): void
    {
        Http::fake([
            'api.sarvam.ai/v1/chat/completions' => Http::response('Quota exceeded', 429)
        ]);

        $this->expectException(SarvamApiException::class);
        $this->expectExceptionCode(429);

        $this->client->chat([['role' => 'user', 'content' => 'Hello']]);
    }

    public function test_chat_4xx_validation_error(): void
    {
        Http::fake([
            'api.sarvam.ai/v1/chat/completions' => Http::response([
                'error' => 'Messages format is invalid'
            ], 400)
        ]);

        $this->expectException(SarvamApiException::class);
        $this->expectExceptionCode(400);

        $this->client->chat([['role' => 'user', 'content' => '']]);
    }

    // ── Text-to-Speech Tests ───────────────────────────────────────────────

    public function test_text_to_speech_success(): void
    {
        Http::fake([
            'api.sarvam.ai/text-to-speech' => Http::response([
                'audios' => ['base64_audio_content']
            ], 200)
        ]);

        $result = $this->client->textToSpeech('வணக்கம்');

        $this->assertEquals('base64_audio_content', $result->audioBase64);
        $this->assertEquals('audio/wav', $result->contentType);
    }

    public function test_text_to_speech_429_rate_limit(): void
    {
        Http::fake([
            'api.sarvam.ai/text-to-speech' => Http::response('Too Many Requests', 429)
        ]);

        $this->expectException(SarvamApiException::class);
        $this->expectExceptionCode(429);

        $this->client->textToSpeech('வணக்கம்');
    }

    public function test_text_to_speech_validation_error(): void
    {
        Http::fake([
            'api.sarvam.ai/text-to-speech' => Http::response([
                'error' => 'Unsupported target language'
            ], 400)
        ]);

        $this->expectException(SarvamApiException::class);
        $this->expectExceptionCode(400);

        $this->client->textToSpeech('வணக்கம்', 'invalid-lang');
    }

    // ── Transliterate Tests ────────────────────────────────────────────────

    public function test_transliterate_success(): void
    {
        Http::fake([
            'api.sarvam.ai/transliterate' => Http::response([
                'transliterated_text' => 'வணக்கம்',
                'source_language_code' => 'en-IN',
                'request_id' => 'req_trans_01'
            ], 200)
        ]);

        $result = $this->client->transliterate('vanakkam');

        $this->assertEquals('வணக்கம்', $result->transliteratedText);
        $this->assertEquals('en-IN', $result->sourceLanguageCode);
        $this->assertEquals('req_trans_01', $result->requestId);
    }

    public function test_transliterate_429_rate_limit(): void
    {
        Http::fake([
            'api.sarvam.ai/transliterate' => Http::response('Rate Limit Exceeded', 429)
        ]);

        $this->expectException(SarvamApiException::class);
        $this->expectExceptionCode(429);

        $this->client->transliterate('vanakkam');
    }

    public function test_transliterate_validation_error(): void
    {
        Http::fake([
            'api.sarvam.ai/transliterate' => Http::response([
                'error' => 'Input text is too long'
            ], 422)
        ]);

        $this->expectException(SarvamApiException::class);
        $this->expectExceptionCode(422);

        $this->client->transliterate(str_repeat('a', 2000));
    }
}
