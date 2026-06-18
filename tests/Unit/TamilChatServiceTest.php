<?php

namespace Tests\Unit;

use App\Services\Sarvam\SarvamClient;
use App\Services\Sarvam\TamilNormalizer;
use App\Services\Sarvam\TamilChatService;
use App\Services\Sarvam\Exceptions\SarvamApiException;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class TamilChatServiceTest extends TestCase
{
    private TamilChatService $chatService;

    protected function setUp(): void
    {
        parent::setUp();
        Config::set('services.sarvam.api_key', 'test_api_key');

        $client = new SarvamClient();
        $normalizer = new TamilNormalizer($client);
        $this->chatService = new TamilChatService($client, $normalizer);
    }

    public function test_chat_passes_validation_on_first_try(): void
    {
        Http::fake([
            'api.sarvam.ai/v1/chat/completions' => Http::response([
                'choices' => [
                    [
                        'message' => [
                            'role' => 'assistant',
                            'content' => 'வணக்கம், நான் உங்களுக்கு எவ்வாறு உதவ முடியும்?'
                        ]
                    ]
                ]
            ], 200)
        ]);

        $messages = [
            ['role' => 'user', 'content' => 'வணக்கம்']
        ];

        $reply = $this->chatService->chat($messages);

        $this->assertEquals('வணக்கம், நான் உங்களுக்கு எவ்வாறு உதவ முடியும்?', $reply);

        Http::assertSentCount(1);
    }

    public function test_chat_normalizes_user_input_before_sending(): void
    {
        Http::fake([
            'api.sarvam.ai/transliterate' => Http::response([
                'transliterated_text' => 'வணக்கம்',
                'source_language_code' => 'en-IN'
            ], 200),
            'api.sarvam.ai/v1/chat/completions' => Http::response([
                'choices' => [
                    [
                        'message' => [
                            'role' => 'assistant',
                            'content' => 'வணக்கம், நான் உங்களுக்கு எவ்வாறு உதவ முடியும்?'
                        ]
                    ]
                ]
            ], 200)
        ]);

        $messages = [
            ['role' => 'user', 'content' => 'vanakkam']
        ];

        $reply = $this->chatService->chat($messages);

        $this->assertEquals('வணக்கம், நான் உங்களுக்கு எவ்வாறு உதவ முடியும்?', $reply);

        // Verify transliteration was called first
        Http::assertSent(function ($request) {
            return $request->url() === 'https://api.sarvam.ai/transliterate' &&
                   $request['input'] === 'vanakkam';
        });

        // Verify chat completion was called with the normalized user content
        Http::assertSent(function ($request) {
            if ($request->url() !== 'https://api.sarvam.ai/v1/chat/completions') {
                return false;
            }
            $messages = $request['messages'];
            // Find user message in history
            foreach ($messages as $msg) {
                if ($msg['role'] === 'user' && $msg['content'] === 'வணக்கம்') {
                    return true;
                }
            }
            return false;
        });
    }

    public function test_chat_fails_validation_first_time_succeeds_on_retry(): void
    {
        Http::fake([
            // Return two different completions in sequence
            'api.sarvam.ai/v1/chat/completions' => Http::sequence()
                ->push([
                    'choices' => [
                        [
                            'message' => [
                                'role' => 'assistant',
                                'content' => 'Hello, நான் உங்களுக்கு உதவ முடியும்?' // Mix of English and Tamil: failing validation
                            ]
                        ]
                    ]
                ], 200)
                ->push([
                    'choices' => [
                        [
                            'message' => [
                                'role' => 'assistant',
                                'content' => 'வணக்கம், நான் உங்களுக்கு எவ்வாறு உதவ முடியும்?' // Pure Tamil: passing validation
                            ]
                        ]
                    ]
                ], 200)
        ]);

        $messages = [
            ['role' => 'user', 'content' => 'வணக்கம்']
        ];

        $reply = $this->chatService->chat($messages);

        $this->assertEquals('வணக்கம், நான் உங்களுக்கு எவ்வாறு உதவ முடியும்?', $reply);

        // Verify it was called twice
        Http::assertSentCount(2);

        // Verify the second request had the sharpened retry prompts in history
        Http::assertSent(function ($request) {
            static $callCount = 0;
            $callCount++;
            if ($callCount === 2) {
                $messages = $request['messages'];
                // Check if the history has 4 messages: system, user, assistant (invalid), user (retry instruction)
                $hasInvalidReply = false;
                $hasRetryInstruction = false;
                foreach ($messages as $msg) {
                    if ($msg['role'] === 'assistant' && strpos($msg['content'], 'Hello, நான் உங்களுக்கு உதவ முடியும்?') !== false) {
                        $hasInvalidReply = true;
                    }
                    if ($msg['role'] === 'user' && strpos($msg['content'], 'Your previous response contained English') !== false) {
                        $hasRetryInstruction = true;
                    }
                }
                return $hasInvalidReply && $hasRetryInstruction;
            }
            return true;
        });
    }

    public function test_chat_fails_validation_both_times_falls_back_to_hardcoded(): void
    {
        Http::fake([
            'api.sarvam.ai/v1/chat/completions' => Http::sequence()
                ->push([
                    'choices' => [
                        [
                            'message' => [
                                'role' => 'assistant',
                                'content' => 'Hello, how can I help you today?'
                            ]
                        ]
                    ]
                ], 200)
                ->push([
                    'choices' => [
                        [
                            'message' => [
                                'role' => 'assistant',
                                'content' => 'Sorry, I still cannot answer in Tamil.'
                            ]
                        ]
                    ]
                ], 200)
        ]);

        $messages = [
            ['role' => 'user', 'content' => 'வணக்கம்']
        ];

        $reply = $this->chatService->chat($messages);

        // Should fall back to predefined hardcoded Tamil response
        $this->assertEquals('மன்னிக்கவும், என்னால் இப்போது தமிழ் மொழியில் சரியாக பதிலளிக்க முடியவில்லை. தயவுசெய்து மீண்டும் முயற்சிக்கவும்.', $reply);
        Http::assertSentCount(2);
    }

    public function test_chat_graceful_handling_of_api_exceptions(): void
    {
        Http::fake([
            'api.sarvam.ai/v1/chat/completions' => Http::response('API Internal Error', 500)
        ]);

        $messages = [
            ['role' => 'user', 'content' => 'வணக்கம்']
        ];

        $reply = $this->chatService->chat($messages);

        // Should return fallback response instead of failing request
        $this->assertEquals('மன்னிக்கவும், என்னால் இப்போது தமிழ் மொழியில் சரியாக பதிலளிக்க முடியவில்லை. தயவுசெய்து மீண்டும் முயற்சிக்கவும்.', $reply);
    }
}
