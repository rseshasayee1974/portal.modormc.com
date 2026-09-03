<?php

namespace App\Services\BatchSheet;

use App\Services\BatchSheet\Contracts\DocumentParser;
use App\Services\BatchSheet\Parsers\CsvParser;
use App\Services\BatchSheet\Parsers\ExcelParser;
use App\Services\BatchSheet\Parsers\ImageAiParser;
use App\Services\BatchSheet\Parsers\PdfTextParser;
use App\Services\BatchSheet\Parsers\PythonOcrParser;
use Illuminate\Support\Facades\App;
use Smalot\PdfParser\Parser as SmalotPdfParser;

class ParserRegistry
{
    protected PdfTextParser $pdfParser;
    protected ImageAiParser $imageAiParser;
    protected ExcelParser $excelParser;
    protected CsvParser $csvParser;
    protected PythonOcrParser $pythonParser;

    public function __construct()
    {
        $this->pdfParser = App::make(PdfTextParser::class);
        $this->imageAiParser = App::make(ImageAiParser::class);
        $this->excelParser = App::make(ExcelParser::class);
        $this->csvParser = App::make(CsvParser::class);
        $this->pythonParser = App::make(PythonOcrParser::class);
    }

    /**
     * Resolve the primary parser for the given MIME type and extension.
     * Strategy:
     * - Images: AI Vision Parser (ImageAiParser) as major OCR
     * - PDF (Digital): Native PHP Multi-Plant PDF Parser (PdfTextParser)
     * - PDF (Scanned): AI Vision Parser (ImageAiParser)
     * - Excel (.xlsx/.xls): Native Excel Spreadsheet Parser (ExcelParser)
     * - CSV: Native CSV Parser (CsvParser)
     */
    public function resolve(string $mimeType, string $extension, ?string $filePath = null): DocumentParser
    {
        $ext = strtolower($extension);

        // 1. Excel files
        if (in_array($ext, ['xlsx', 'xls'], true) || str_contains($mimeType, 'spreadsheet') || str_contains($mimeType, 'excel')) {
            return $this->excelParser;
        }

        // 2. CSV files
        if ($ext === 'csv' || str_contains($mimeType, 'csv')) {
            return $this->csvParser;
        }

        // 3. PDF files: Check if scanned or readable text
        if ($ext === 'pdf' || $mimeType === 'application/pdf') {
            if ($filePath && $this->detectOcrRequired($filePath)) {
                return $this->imageAiParser; // Scanned PDF -> AI Vision
            }
            return $this->pdfParser; // Digital PDF -> Native PHP PDF parser
        }

        // 4. Image files -> Major OCR via AI Vision
        if (in_array($ext, ['jpg', 'jpeg', 'png', 'webp', 'bmp', 'tiff', 'tif'], true) || str_starts_with($mimeType, 'image/')) {
            return $this->imageAiParser;
        }

        return $this->imageAiParser;
    }

    /**
     * Get the secondary / fallback parser if the primary parser fails.
     */
    public function getFallbackParser(): DocumentParser
    {
        return $this->pythonParser;
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
