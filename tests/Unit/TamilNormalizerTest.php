<?php

namespace Tests\Unit;

use App\Services\Sarvam\SarvamClient;
use App\Services\Sarvam\TamilNormalizer;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class TamilNormalizerTest extends TestCase
{
    private TamilNormalizer $normalizer;

    protected function setUp(): void
    {
        parent::setUp();
        Config::set('services.sarvam.api_key', 'test_api_key');
        
        $client = new SarvamClient();
        $this->normalizer = new TamilNormalizer($client);
    }

    public function test_normalize_tamil_does_not_change_tamil(): void
    {
        // No HTTP requests should be made since it's already in Tamil
        Http::fake();

        $input = 'வணக்கம், எப்படி இருக்கிறீர்கள்?';
        $result = $this->normalizer->normalize($input);

        $this->assertEquals($input, $result);
        Http::assertNothingSent();
    }

    public function test_normalize_tanglish_triggers_transliteration(): void
    {
        Http::fake([
            'api.sarvam.ai/transliterate' => Http::response([
                'transliterated_text' => 'வணக்கம், எப்படி இருக்கிறீர்கள்',
                'source_language_code' => 'en-IN',
                'request_id' => 'req_normalizer_01'
            ], 200)
        ]);

        $input = 'vanakkam, epdi irukinga';
        $result = $this->normalizer->normalize($input);

        $this->assertEquals('வணக்கம், எப்படி இருக்கிறீர்கள்', $result);

        Http::assertSent(function ($request) {
            return $request->url() === 'https://api.sarvam.ai/transliterate' &&
                   $request['input'] === 'vanakkam, epdi irukinga' &&
                   $request['source_language_code'] === 'en-IN' &&
                   $request['target_language_code'] === 'ta-IN';
        });
    }

    public function test_normalize_tanglish_graceful_fallback_on_api_error(): void
    {
        // Simulate a 429 rate limit error
        Http::fake([
            'api.sarvam.ai/transliterate' => Http::response('Rate Limit Exceeded', 429)
        ]);

        $input = 'vanakkam, epdi irukinga';
        $result = $this->normalizer->normalize($input);

        // Should return original text on failure instead of throwing exception
        $this->assertEquals($input, $result);
    }

    public function test_normalize_non_alphabetic_input(): void
    {
        Http::fake();

        $input = '12345 !!! :)';
        $result = $this->normalizer->normalize($input);

        $this->assertEquals($input, $result);
        Http::assertNothingSent();
    }

    public function test_normalize_mixed_input_tanglish_dominant(): void
    {
        // 13 Latin chars ('hello vanakkam') vs 8 Tamil chars ('வணக்கம்')
        // Latin is dominant -> triggers transliteration
        Http::fake([
            'api.sarvam.ai/transliterate' => Http::response([
                'transliterated_text' => 'ஹலோ வணக்கம் வணக்கம்',
                'source_language_code' => 'en-IN'
            ], 200)
        ]);

        $input = 'hello vanakkam வணக்கம்';
        $result = $this->normalizer->normalize($input);

        $this->assertEquals('ஹலோ வணக்கம் வணக்கம்', $result);
    }

    public function test_normalize_mixed_input_tamil_dominant(): void
    {
        // 5 Latin chars ('hello') vs 16 Tamil chars ('வணக்கம் அண்ணா எப்படி')
        // Tamil is dominant -> does NOT trigger transliteration
        Http::fake();

        $input = 'hello வணக்கம் அண்ணா எப்படி';
        $result = $this->normalizer->normalize($input);

        $this->assertEquals($input, $result);
        Http::assertNothingSent();
    }
}
