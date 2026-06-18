<?php

namespace App\Services\Sarvam\DTO;

class TransliterationResult
{
    public function __construct(
        public readonly string $transliteratedText,
        public readonly string $sourceLanguageCode,
        public readonly ?string $requestId = null
    ) {}
}
