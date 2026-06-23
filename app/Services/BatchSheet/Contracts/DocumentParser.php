<?php

namespace App\Services\BatchSheet\Contracts;

use App\Services\BatchSheet\DTOs\ParsedDocument;

interface DocumentParser
{
    /**
     * Check if this parser can handle the given MIME type and file extension.
     */
    public function canHandle(string $mimeType, string $extension): bool;

    /**
     * Parse the document at the given file path and extract raw text, headers, and material rows.
     */
    public function parse(string $filePath, array $options = []): ParsedDocument;

    /**
     * Get a human-readable identifier for this parser.
     */
    public function getParserName(): string;
}
