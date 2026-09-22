<?php

namespace App\Services;

use Illuminate\Support\Facades\Storage;

class PdfLogo
{
    public static function dataUri(?string $path): ?string
    {
        if (!$path) return null;
        $path = str_replace('\\', '/', trim($path));
        $path = preg_replace('#^(?:/?storage/|/?public/)+#', '', $path);
        if (!$path || str_contains($path, '..') || str_contains($path, ':')) return null;
        $disk = Storage::disk('public');
        if (!$disk->exists($path)) return null;
        $bytes = $disk->get($path);
        if (!$bytes) return null;
        $mime = (new \finfo(FILEINFO_MIME_TYPE))->buffer($bytes);
        if (!in_array($mime, ['image/png', 'image/jpeg', 'image/gif', 'image/svg+xml', 'image/bmp'], true)) return null;
        return 'data:' . $mime . ';base64,' . base64_encode($bytes);
    }
}
