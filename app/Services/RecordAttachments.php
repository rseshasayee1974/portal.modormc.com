<?php
/*
Author: ragul-onemodo
Created: 2026-11-05 12:35:13 Asia/Calcutta (UTC+05:30)
*/

namespace App\Services;

use Closure;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

class RecordAttachments
{
    public const RULES = [
        'attachments' => 'sometimes|array|max:5',
        'attachments.*' => 'required|file|max:2048|mimes:xls,xlsx,pdf,jpg,jpeg,png,webp,gif,bmp,tif,tiff|extensions:xls,xlsx,pdf,jpg,jpeg,png,webp,gif,bmp,tif,tiff',
    ];

    public static function transaction(int $plantId, array $files, Closure $save): mixed
    {
        $attachments = [];
        $paths = [];
        try {
            foreach ($files as $file) {
                $id = (string) Str::uuid();
                $paths[] = self::path($plantId, $id);
                if (!$file->storeAs(dirname(self::path($plantId, $id)), $id, 'local')) {
                    throw ValidationException::withMessages(['attachments' => 'An attachment could not be saved. Please try again.']);
                }
                $name = trim(preg_replace('/[\x00-\x1F\x7F]/u', '', $file->getClientOriginalName()) ?? '');
                $attachments[] = ['id' => $id, 'name' => mb_substr($name ?: 'Attachment', 0, 255)];
            }

            return DB::transaction(fn () => $save($attachments));
        } catch (Throwable $exception) {
            if ($paths) {
                Storage::disk('local')->delete($paths);
            }
            throw $exception;
        }
    }

    public static function download(int $plantId, array $attachments, string $id): StreamedResponse
    {
        abort_unless(Str::isUuid($id), 404);
        $file = collect($attachments)->firstWhere('id', $id);
        $path = self::path($plantId, $id);
        abort_unless($file && Storage::disk('local')->exists($path), 404);

        return Storage::disk('local')->download($path, $file['name'], [
            'Content-Type' => 'application/octet-stream',
            'X-Content-Type-Options' => 'nosniff',
            'Cache-Control' => 'private, no-store',
        ]);
    }

    private static function path(int $plantId, string $id): string
    {
        return "record-attachments/{$plantId}/{$id}";
    }
}
