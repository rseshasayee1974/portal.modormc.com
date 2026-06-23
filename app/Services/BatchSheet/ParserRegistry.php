<?php

namespace App\Services\BatchSheet;

use App\Services\BatchSheet\Contracts\DocumentParser;
use App\Services\BatchSheet\Parsers\CsvParser;
use App\Services\BatchSheet\Parsers\ExcelParser;
use App\Services\BatchSheet\Parsers\ImageAiParser;
use App\Services\BatchSheet\Parsers\PdfTextParser;
use Illuminate\Support\Facades\App;
use Smalot\PdfParser\Parser as SmalotPdfParser;

class ParserRegistry
{
    protected array $parsers = [];

    public function __construct()
    {
        // Register default parsers
        $this->register(App::make(PdfTextParser::class));
        $this->register(App::make(ImageAiParser::class));
        $this->register(App::make(ExcelParser::class));
        $this->register(App::make(CsvParser::class));
    }

    /**
     * Register a new document parser.
     */
    public function register(DocumentParser $parser): void
    {
        $this->parsers[] = $parser;
    }

    /**
     * Resolve the appropriate parser for the given MIME type and extension.
     */
    public function resolve(string $mimeType, string $extension, ?string $filePath = null): DocumentParser
    {
        // Special check: If PDF is scanned, route to ImageAiParser
        if ($extension === 'pdf' && $filePath && $this->detectOcrRequired($filePath)) {
            foreach ($this->parsers as $parser) {
                if ($parser instanceof ImageAiParser) {
                    return $parser;
                }
            }
        }

        foreach ($this->parsers as $parser) {
            if ($parser->canHandle($mimeType, $extension)) {
                return $parser;
            }
        }

        throw new \RuntimeException("No registered parser can handle file type: {$mimeType} (.{$extension})");
    }

    /**
     * Detect if a PDF lacks selectable text and requires OCR/Vision extraction.
     */
    public function detectOcrRequired(string $filePath): bool
    {
        try {
            $pdfParser = new SmalotPdfParser();
            $pdf = $pdfParser->parseFile($filePath);
            $text = trim($pdf->getText());

            // If text is very short or empty, OCR is required
            return strlen($text) < 20;
        } catch (\Exception $e) {
            // If pdf parsing fails, assume OCR is required or it's scanned
            return true;
        }
    }
}
