<?php

namespace App\Services\BatchSheet;

use App\Models\BatchSheetUpload;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class UploadService
{
    /**
     * Validate an uploaded file.
     * Throws an exception if validation fails.
     */
    public function validate(UploadedFile $file): void
    {
        $maxSize = config('batchsheet.max_file_size', 20480);
        if ($file->getSize() > ($maxSize * 1024)) {
            throw new \InvalidArgumentException("File size exceeds the limit of " . ($maxSize / 1024) . "MB.");
        }

        $extension = Str::lower($file->getClientOriginalExtension());
        $allowedExtensions = config('batchsheet.allowed_extensions', []);
        if (!in_array($extension, $allowedExtensions, true)) {
            throw new \InvalidArgumentException("Extension '{$extension}' is not allowed.");
        }

        $blockedExtensions = config('batchsheet.blocked_extensions', []);
        if (in_array($extension, $blockedExtensions, true)) {
            throw new \InvalidArgumentException("Extension '{$extension}' is blocked for security reasons.");
        }

        // Check for double extension (e.g. file.pdf.exe)
        $originalName = $file->getClientOriginalName();
        if (preg_match('/\.[a-zA-Z0-9]+\.[a-zA-Z0-9]+$/', $originalName)) {
            // Check if any of the segments match a blocked extension
            $parts = explode('.', $originalName);
            if (count($parts) > 2) {
                array_pop($parts); // remove the last extension
                $preExtension = Str::lower(end($parts));
                if (in_array($preExtension, $blockedExtensions, true) || in_array($preExtension, ['php', 'js', 'html', 'exe', 'sh'], true)) {
                    throw new \InvalidArgumentException("Potential malicious file upload detected (double extension).");
                }
            }
        }

        // Validate MIME type
        $mime = $file->getMimeType();
        $allowedMimes = config('batchsheet.allowed_mimes', []);
        if (!in_array($mime, $allowedMimes, true)) {
            throw new \InvalidArgumentException("MIME type '{$mime}' is not allowed.");
        }

        // Check magic bytes signatures
        $this->validateMagicBytes($file);
    }

    /**
     * Validate magic bytes signatures of the file.
     */
    protected function validateMagicBytes(UploadedFile $file): void
    {
        $magicBytes = config('batchsheet.magic_bytes', []);
        if (empty($magicBytes)) {
            return;
        }

        $path = $file->getRealPath();
        $handle = fopen($path, 'rb');
        if (!$handle) {
            return;
        }

        // We read the first 16 bytes to check headers
        $header = fread($handle, 16);
        fclose($handle);

        $matchedMime = null;
        foreach ($magicBytes as $signature => $expectedMime) {
            if (str_starts_with($header, $signature)) {
                $matchedMime = $expectedMime;
                break;
            }
        }

        // If it's a PDF or Image, let's verify if the magic bytes matched the expected mime category
        $mime = $file->getMimeType();
        if ($matchedMime && !str_contains($mime, 'sheet') && !str_contains($mime, 'csv') && !str_contains($mime, 'excel')) {
            // For zip-based files (xlsx) or text/csv, magic bytes might be standard zip/text, which is fine
            // But for PDFs and Images, we expect a match
            if ($matchedMime !== $mime && !($mime === 'image/jpeg' && $matchedMime === 'image/jpeg')) {
                // If it's PDF, it must start with %PDF
                if ($mime === 'application/pdf' && $matchedMime !== 'application/pdf') {
                    throw new \InvalidArgumentException("File signature verification failed: File claims to be PDF but lacks PDF signature.");
                }
            }
        }
    }

    /**
     * Calculate SHA-256 hash of the file.
     */
    public function calculateHash(UploadedFile $file): string
    {
        return hash_file('sha256', $file->getRealPath());
    }

    /**
     * Check if a duplicate upload exists by hash.
     */
    public function checkDuplicate(string $hash, int $plantId): ?BatchSheetUpload
    {
        return BatchSheetUpload::where('sha256_hash', $hash)
            ->where('plant_id', $plantId)
            ->whereIn('status', [BatchSheetUpload::STATUS_COMPLETED, BatchSheetUpload::STATUS_REVIEW, BatchSheetUpload::STATUS_PROCESSING])
            ->first();
    }

    /**
     * Store the uploaded file and create a record in the database.
     */
    public function store(UploadedFile $file, int $plantId, int $userId): BatchSheetUpload
    {
        $this->validate($file);

        $hash = $this->calculateHash($file);
        $originalFilename = $file->getClientOriginalName();
        $extension = Str::lower($file->getClientOriginalExtension());
        $mimeType = $file->getMimeType();
        $fileSize = $file->getSize();

        // Generate unique name
        $uuid = Str::uuid()->toString();
        $storedFilename = "{$uuid}.{$extension}";
        $storagePath = config('batchsheet.storage_path', 'batch-sheets/uploads');

        $storedPath = $file->storeAs($storagePath, $storedFilename, [
            'disk' => config('batchsheet.storage_disk', 'public')
        ]);

        return BatchSheetUpload::create([
            'plant_id' => $plantId,
            'user_id' => $userId,
            'original_filename' => $originalFilename,
            'stored_filename' => $storedFilename,
            'stored_path' => $storedPath,
            'mime_type' => $mimeType,
            'file_size' => $fileSize,
            'sha256_hash' => $hash,
            'file_extension' => $extension,
            'status' => BatchSheetUpload::STATUS_UPLOADED,
            'processing_log' => [],
        ]);
    }
}
