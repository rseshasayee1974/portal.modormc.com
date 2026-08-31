<?php

namespace App\Jobs;

use App\Models\BatchSheetUpload;
use App\Services\BatchSheet\DataNormalizer;
use App\Services\BatchSheet\FieldExtractor;
use App\Services\BatchSheet\ParserRegistry;
use App\Services\BatchSheet\TemplateMatcher;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class ProcessBatchSheetJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected int $uploadId;

    /**
     * Create a new job instance.
     */
    public function __construct(int $uploadId)
    {
        $this->uploadId = $uploadId;
    }

    /**
     * Execute the job.
     */
    public function handle(
        ParserRegistry $parserRegistry,
        TemplateMatcher $templateMatcher,
        FieldExtractor $fieldExtractor,
        DataNormalizer $dataNormalizer
    ): void {
        $upload = BatchSheetUpload::find($this->uploadId);
        if (!$upload) {
            Log::error("ProcessBatchSheetJob: Upload ID {$this->uploadId} not found.");
            return;
        }

        try {
            $upload->transitionTo(BatchSheetUpload::STATUS_VALIDATING, "Validating file and detecting OCR requirements...");

            $filePath = storage_path('app/' . ($upload->stored_path));
            if (!file_exists($filePath)) {
                // Try public disk path
                $filePath = Storage::disk(config('batchsheet.storage_disk', 'public'))->path($upload->stored_path);
            }

            if (!file_exists($filePath)) {
                throw new \RuntimeException("File not found on disk: {$upload->stored_path}");
            }

            // 1. Detect if OCR is required
            $ocrRequired = false;
            if ($upload->file_extension === 'pdf') {
                $ocrRequired = $parserRegistry->detectOcrRequired($filePath);
            } else {
                $ocrRequired = true; // Images always require OCR/AI Vision
            }

            $upload->update([
                'ocr_required' => $ocrRequired
            ]);

            if ($ocrRequired) {
                $upload->transitionTo(BatchSheetUpload::STATUS_OCR_RUNNING, "Scanned document or image detected. Routing to AI Vision parser...");
            } else {
                $upload->transitionTo(BatchSheetUpload::STATUS_PROCESSING, "Readable document detected. Routing to text parser...");
            }

            // 2. Resolve parser and parse
            $parser = $parserRegistry->resolve($upload->mime_type, $upload->file_extension, $filePath);
            $upload->update(['parser_used' => $parser->getParserName()]);

            $parsedDoc = $parser->parse($filePath, [
                'mime_type' => $upload->mime_type
            ]);

            $upload->transitionTo(BatchSheetUpload::STATUS_EXTRACTING, "Extracting and mapping key-value fields...");

            // 3. Match template with high-speed pattern recognizer
            $template = $templateMatcher->match(
                $parsedDoc->headerFields,
                $upload->plant_id,
                $parsedDoc->materialRows,
                $parsedDoc->rawText
            );
            if ($template) {
                $upload->update(['template_id' => $template->id]);
            }

            // 4. Extract fields using template or dictionary
            $extracted = $fieldExtractor->extract($parsedDoc->headerFields, $template);
            
            // 5. Normalize extracted fields and resolve database foreign keys
            $normalized = $dataNormalizer->normalize($extracted['header'], $parsedDoc->materialRows, $upload->plant_id, $template);

            // 6. Update database record with JSON values
            $upload->raw_text = $parsedDoc->rawText;
            $upload->parsed_json = [
                'header_fields' => $parsedDoc->headerFields,
                'materials' => $parsedDoc->materialRows,
            ];
            $upload->normalized_json = $normalized;
            $upload->confidence_score = $parsedDoc->confidence;
            $upload->field_scores = $extracted['field_scores'];
            $upload->save();

            // Transition to review status for user approval
            $upload->transitionTo(BatchSheetUpload::STATUS_REVIEW, "Document parsed successfully! Ready for verification.");

        } catch (\Exception $e) {
            Log::error("ProcessBatchSheetJob: Error processing upload {$this->uploadId}: " . $e->getMessage());
            $upload->error_message = $e->getMessage();
            $upload->transitionTo(BatchSheetUpload::STATUS_FAILED, "Processing failed: " . $e->getMessage());
        }
    }
}
