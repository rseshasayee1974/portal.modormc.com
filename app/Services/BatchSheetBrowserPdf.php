<?php

/*
 * Author: ragul-onemodo
 * Created: 2026-10-31 09:48:16 Asia/Calcutta (UTC+05:30)
 */

namespace App\Services;

use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use RuntimeException;
use Symfony\Component\Process\ExecutableFinder;
use Symfony\Component\Process\Process;

class BatchSheetBrowserPdf
{
    public function render(string $html): string
    {
        $root = storage_path('app/private/batch-sheet-pdf');
        $directory = $root.'/'.Str::uuid();
        File::ensureDirectoryExists($directory, 0700);
        try {
            $input = $directory.'/report.html';
            $output = $directory.'/report.pdf';
            File::put($input, $html);
            $path = str_replace('\\', '/', $input);
            $url = 'file://'.(str_starts_with($path, '/') ? '' : '/').implode('/', array_map('rawurlencode', explode('/', $path)));
            // Preserve the drive separator in Windows file URLs.
            $url = preg_replace('#^file:///([A-Za-z])%3A/#', 'file:///$1:/', $url);
            $process = new Process([
                $this->browser(), '--headless=new', '--disable-gpu', '--disable-extensions',
                '--no-first-run', '--no-default-browser-check', '--no-pdf-header-footer',
                '--user-data-dir='.$directory.'/profile', '--print-to-pdf='.$output, $url,
            ], null, null, null, 45);
            $process->mustRun();
            $bytes = is_file($output) ? File::get($output) : '';
            if (! str_starts_with($bytes, '%PDF-')) {
                throw new RuntimeException('The browser did not produce a valid batch sheet PDF.');
            }

            return $bytes;
        } finally {
            $resolvedRoot = realpath($root);
            $resolvedDirectory = realpath($directory);
            if ($resolvedRoot && $resolvedDirectory && str_starts_with($resolvedDirectory, $resolvedRoot.DIRECTORY_SEPARATOR)) {
                File::deleteDirectory($resolvedDirectory);
            }
        }
    }

    private function browser(): string
    {
        $candidates = [
            config('batchsheet.pdf_browser_path'),
            'C:/Program Files/Google/Chrome/Application/chrome.exe',
            '/Applications/Google Chrome.app/Contents/MacOS/Google Chrome',
        ];
        foreach ($candidates as $path) {
            if ($path && is_file($path)) {
                return $path;
            }
        }
        $finder = new ExecutableFinder;
        foreach (['chromium', 'chromium-browser', 'google-chrome'] as $name) {
            if ($path = $finder->find($name)) {
                return $path;
            }
        }
        throw new RuntimeException('Install Chrome/Chromium or configure BATCH_SHEET_PDF_BROWSER for the exact batch sheet layout.');
    }
}
