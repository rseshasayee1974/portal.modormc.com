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
            $css .= $this->invoiceLayoutStyles();
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
            unset($pdf, $pages, $html);
            gc_collect_cycles();
        }
        throw ValidationException::withMessages(['invoice_ids' => 'An invoice contains too much content to fit on one A4 page without making it unreadable. Export that invoice separately or reduce its content. No content has been truncated.']);
    }

    private function invoiceLayoutStyles(): string
    {
        return <<<'CSS'
            /* Scope layout adjustments to the combined export's invoice template. */
            .bulk-invoice .invoice-container {
                width: auto;
                max-width: none;
                margin: 0;
                border: 1px solid #333;
            }
            .bulk-invoice .invoice-container table {
                border-spacing: 0;
            }
            .bulk-invoice .info-grid-table,
            .bulk-invoice .items-table,
            .bulk-invoice .summary-table,
            .bulk-invoice .terms-bank-table,
            .bulk-invoice .declaration-table {
                table-layout: fixed;
            }
            .bulk-invoice .invoice-type-tag {
                padding: 6px 8px;
                margin-bottom: 0 !important;
            }
            .bulk-invoice .info-grid-table td {
                padding: 7px 8px;
                line-height: 1.35;
            }
            .bulk-invoice .box-heading {
                padding-bottom: 4px;
                margin-bottom: 5px;
                border-color: #777;
            }
            .bulk-invoice .info-line {
                margin-bottom: 2px;
                line-height: 1.3;
                overflow-wrap: break-word;
            }
            /* Dompdf includes padding outside explicit block widths. */
            .bulk-invoice .carrier-row {
                width: auto;
                padding: 6px 8px;
            }
            .bulk-invoice .items-table th {
                padding: 6px 4px;
                background-color: #f2f2f2;
                line-height: 1.3;
            }
            .bulk-invoice .items-table > tbody > tr > td {
                padding: 7px 5px;
                border-bottom: 1px solid #777;
                overflow-wrap: break-word;
            }
            .bulk-invoice .items-table > tbody > tr:last-child > td {
                border-bottom: none;
            }
            .bulk-invoice .items-table th:first-child,
            .bulk-invoice .items-table > tbody > tr > td:first-child {
                border-left: none;
            }
            .bulk-invoice .items-table th:last-child,
            .bulk-invoice .items-table > tbody > tr > td:last-child {
                border-right: none;
            }
            .bulk-invoice .tax-subtable td {
                padding: 2px 1px;
                font-size: 6.5pt;
                white-space: nowrap;
                border: none;
            }
            .bulk-invoice .summary-table > tbody > tr > td,
            .bulk-invoice .terms-bank-table td,
            .bulk-invoice .declaration-table td {
                padding: 7px 8px;
            }
            .bulk-invoice .calc-subtable td {
                padding: 6px 8px;
            }
            .bulk-invoice .calc-subtable tr:last-child td {
                background-color: #f2f2f2;
            }
            .bulk-invoice .terms-list li {
                margin-bottom: 3px;
            }
            CSS;
    }

    private function scaleLengths(string $css, float $scale): string
    {
        return preg_replace_callback('/(?<![\w.-])(\d+(?:\.\d+)?)(pt|px|mm)\b/i',
            fn($m) => round((float)$m[1] * $scale, 3).$m[2], $css);
    }
}
