<?php

namespace App\Services\Sarvam\DTO;

class TranscriptionResult
{
    public function __construct(
        public readonly string $transcript,
        public readonly string $languageCode,
        public readonly ?string $requestId = null
    ) {}
}
