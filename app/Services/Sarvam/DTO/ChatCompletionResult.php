<?php

namespace App\Services\Sarvam\DTO;

class ChatCompletionResult
{
    public function __construct(
        public readonly string $content,
        public readonly string $role,
        public readonly ?int $promptTokens = null,
        public readonly ?int $completionTokens = null
    ) {}
}
