<?php

namespace App\Services\BatchSheet\Parsers;

use App\Services\BatchSheet\Contracts\DocumentParser;
use App\Services\BatchSheet\DTOs\ParsedDocument;
use Illuminate\Support\Facades\Log;
use Symfony\Component\Process\Process;

class PythonOcrParser implements DocumentParser
{
    public function canHandle(string $mimeType, string $extension): bool
    {
        $supportedMimes = ['image/jpeg', 'image/png', 'image/tiff', 'image/bmp', 'image/webp'];
        return in_array($mimeType, $supportedMimes, true) || in_array($extension, ['jpg', 'jpeg', 'png', 'webp', 'bmp', 'pdf'], true);
    }

    public function getParserName(): string
    {
        return 'Python Vision & EasyOCR Engine';
    }

    public function parse(string $filePath, array $options = []): ParsedDocument
    {
        Log::info("PythonOcrParser: Processing file {$filePath}");

        $scriptPath = base_path('python/batch_sheet_ocr.py');
        if (!file_exists($scriptPath)) {
            throw new \RuntimeException("Python OCR script not found at: {$scriptPath}");
        }

        $pythonExecutable = env('PYTHON_BINARY', 'python');

        $process = new Process([
            $pythonExecutable,
            $scriptPath,
            $filePath
        ]);

        $process->setEnv([
            'PYTHONIOENCODING' => 'utf-8',
            'PYTHONUTF8' => '1',
            'SYSTEMROOT' => getenv('SystemRoot') ?: 'C:\Windows',
            'PATH' => getenv('PATH') ?: '',
        ]);

        $process->setTimeout(120);
        $process->run();

        if (!$process->isSuccessful()) {
            $errOutput = $process->getErrorOutput() ?: $process->getOutput();
            Log::error("PythonOcrParser execution error: {$errOutput}");
            throw new \RuntimeException("Python OCR processing failed: " . substr($errOutput, 0, 500));
        }

        $rawOutput = $process->getOutput();
        
        // Find JSON block in output (ignoring any torch/easyocr stderr warnings in stdout)
        $jsonStart = strpos($rawOutput, '{');
        $jsonEnd = strrpos($rawOutput, '}');

        if ($jsonStart === false || $jsonEnd === false) {
            Log::error("PythonOcrParser: Invalid output received", ['output' => $rawOutput]);
            throw new \RuntimeException("Python OCR did not return valid JSON output.");
        }

        $jsonStr = substr($rawOutput, $jsonStart, ($jsonEnd - $jsonStart + 1));
        $data = json_decode($jsonStr, true);

        if (!$data || !($data['success'] ?? false)) {
            $msg = $data['error'] ?? 'Unknown parsing error in Python OCR.';
            throw new \RuntimeException("Python OCR parser failed: " . $msg);
        }

        return new ParsedDocument([
            'rawText' => $data['raw_text'] ?? '',
            'headerFields' => $data['header'] ?? [],
            'materialRows' => $data['materials'] ?? [],
            'confidence' => (float)($data['confidence'] ?? 90.0),
            'fieldScores' => $data['field_scores'] ?? [],
            'parserUsed' => $this->getParserName(),
        ]);
    }
}
