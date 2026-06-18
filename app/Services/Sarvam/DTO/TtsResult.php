<?php

namespace App\Services\Sarvam\DTO;

class TtsResult
{
    public function __construct(
        public readonly string $audioBase64,
        public readonly string $contentType
    ) {}
}
