<?php

namespace App\Services;

use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Validation\ValidationException;

class BulkInvoicePdfService
{
    /** All documents must use the same stylesheet. Never clip overflowing content. */
    public function render(array $documents): string
    {
        if (!$documents) {
            throw ValidationException::withMessages(['invoice_ids' => 'Select at least one invoice.']);
        }
        $bodies = [];
        $styles = '';
        foreach ($documents as $html) {
            preg_match_all('/<style\b[^>]*>(.*?)<\/style>/is', $html, $matches);
            $styles = implode("\n", $matches[1]);
            if (!preg_match('/<body\b[^>]*>(.*?)<\/body>/is', $html, $body)) {
                throw new \RuntimeException('The invoice template must contain a complete HTML document.');
            }
            $bodies[] = preg_replace('/<script\b[^>]*>.*?<\/script>/is', '', $body[1]);
        }
        // Keep one common scale so all invoices in the export have consistent typography.
        // The final page count is checked rather than trusting CSS page-break hints alone.
        foreach ([1.0, 0.9, 0.8, 0.7] as $scale) {
            $css = preg_replace('/@page\s*\{[^}]*\}/is', '', $styles);
            $css = $this->scaleLengths($css, $scale);
            $css .= ' @page { size: A4 portrait; margin: 8mm !important; }
                .print-actions-bar { display: none !important; }
                .bulk-invoice { page-break-before: always; }
                .bulk-invoice.first { page-break-before: auto; }';
            $pages = [];
            foreach ($bodies as $i => $body) {
                $body = preg_replace_callback('/style="([^"]*)"/i', fn($m) => 'style="'.$this->scaleLengths($m[1], $scale).'"', $body);
                $pages[] = '<div class="bulk-invoice'.($i === 0 ? ' first' : '').'">'.$body.'</div>';
            }
            $html = '<!doctype html><html><head><meta charset="UTF-8"><style>'.$css.'</style></head><body>'.implode('', $pages).'</body></html>';
            $pdf = Pdf::loadHTML($html)->setPaper('a4', 'portrait');
            $pdf->render();
            if ($pdf->getDomPDF()->getCanvas()->get_page_count() === count($documents)) {
                return $pdf->output();
            }
            unset($pdf);
        }
        throw ValidationException::withMessages(['invoice_ids' => 'An invoice contains too much content to fit on one A4 page without making it unreadable. Export that invoice separately or reduce its content. No content has been truncated.']);
    }

    private function scaleLengths(string $css, float $scale): string
    {
        return preg_replace_callback('/(?<![\w.-])(\d+(?:\.\d+)?)(pt|px|mm)\b/i',
            fn($m) => round((float)$m[1] * $scale, 3).$m[2], $css);
    }
}
