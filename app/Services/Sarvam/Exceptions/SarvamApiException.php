<?php

namespace App\Services\Sarvam\Exceptions;

class SarvamApiException extends \Exception
{
    public function __construct(
        string $message,
        public readonly int $statusCode,
        public readonly string $responseBody,
        \Throwable $previous = null
    ) {
        parent::__construct($message, $statusCode, $previous);
    }
}
