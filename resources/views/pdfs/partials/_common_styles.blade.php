{{-- resources/views/pdfs/partials/_common_styles.blade.php

     Shared CSS reset + common variables used by ALL templates --}}

<style>
    @import url('https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800;900&family=Outfit:wght@400;500;600;700;800;900&display=swap');

    /* ═══════════════════════════════════════════════════════════════
       RESET & 
       ═══════════════════════════════════════════════════════════════ */

    @page {
        size: A4 portrait;
        margin: 10mm;
    }

    * {
        box-sizing: border-box;
        margin: 0;
        padding: 0;
    }

    :root {
        --font-base: 'Inter', Helvetica, Arial, 'DejaVu Sans', sans-serif;
        --font-serif: Georgia, 'Times New Roman', serif;
        --font-heading: 'Outfit', 'Inter', Helvetica, Arial, sans-serif;

        --color-ink: #1e293b;
        --color-muted: #64748b;
        --color-light: #94a3b8;

        --color-accent: #4f46e5;
        --color-accent-light: #f5f3ff;

        --color-border: #cbd5e1;
        --color-border-light: #e2e8f0;

        --color-header-bg: #1e293b;
        --color-alt-bg: #f8fafc;
        --color-balance-bg: #f1f5f9;

        --color-red: #ef4444;
        --color-green: #10b981;
        --color-amber: #f59e0b;

        --size-base: 11px;
        --size-small: 10px;
        --size-xsmall: 9px;
        --size-title: 24px;
    }

    html,
    body {
        margin: 0 !important;
        padding: 0 !important;
        width: 100%;
    }

    body {
        font-family: 'Inter', Helvetica, Arial, 'DejaVu Sans', sans-serif;
        font-size: 11px;
        color: #1e293b;
        background: #fff;
        line-height: 1.5;
    }

    .inv-root {
        border: 1px solid #cbd5e1;
        background: #fff;
    }


    /* ═══════════════════════════════════════════════════════════════
       UTILITY CLASSES
       ═══════════════════════════════════════════════════════════════ */

    .text-left {
        text-align: left !important;
    }

    .text-right {
        text-align: right !important;
    }

    .text-center {
        text-align: center !important;
    }

    .bold {
        font-weight: 700;
    }

    .italic {
        font-style: italic;
    }

    .red {
        color: #ef4444;
    }

    .muted {
        color: #64748b;
    }

    .small {
        font-size: 10px;
    }

    .underline {
        text-decoration: underline;
    }


    /* ═══════════════════════════════════════════════════════════════
       ITEMS TABLE SHARED
       ═══════════════════════════════════════════════════════════════ */

    .item-name {
        font-weight: 700;
        color: #1e293b;
    }

    .item-sub {
        font-size: 9px;
        color: #888;
        margin-top: 1px;
        white-space: pre-wrap;
    }

    .badge-done {
        color: #10b981;
        font-weight: 700;
    }

    .badge-pending {
        color: #f59e0b;
        font-weight: 700;
    }


    /* ═══════════════════════════════════════════════════════════════
       FOOTER SHARED
       ═══════════════════════════════════════════════════════════════ */

    .powered-footer {
        display: table;
        width: 100%;
        border-top: 1px solid #cbd5e1;
        font-size: 9px;
    }

    .powered-footer .pf-left {
        display: table-cell;
        color: #64748b;
        font-size: 9px;
        letter-spacing: 0.02em;
        vertical-align: middle;
        padding: 6px 12px;
        text-align: left;
    }

    .powered-footer .pf-right {
        display: table-cell;
        text-align: right;
        color: #64748b;
        font-size: 9.5px;
        vertical-align: middle;
        padding: 6px 12px;
    }

    .powered-footer .pf-brand {
        font-weight: 700;
        color: #1e293b;
        font-size: 10px;
    }


    /* ═══════════════════════════════════════════════════════════════
       CENTERED A4 PREVIEW
       Browser / Screen View
       ═══════════════════════════════════════════════════════════════ */

    @media screen {

        body {
            background-color: #f8fafc !important;
            padding: 40px 15px !important;
        }

        .inv-root {
            max-width: 800px;
            margin: 0 auto !important;
            background: #fff !important;

            box-shadow:
                0 10px 25px -5px rgba(0, 0, 0, 0.1),
                0 8px 10px -6px rgba(0, 0, 0, 0.1) !important;

            border-radius: 8px !important;
            border: 1px solid #cbd5e1 !important;
        }
    }


    /* ═══════════════════════════════════════════════════════════════
       PRINT
       ═══════════════════════════════════════════════════════════════ */

    @media print {

        body {
            padding: 0 !important;
            margin: 0 !important;
            background: #fff !important;
        }

        .inv-root {
            min-height: 0 !important;
            height: auto !important;
            display: block !important;
            width: auto !important;
            margin: 0 !important;
            padding: 0 !important;
            box-shadow: none !important;
        }
    }


    /* ═══════════════════════════════════════════════════════════════
       PDF MODE
       ═══════════════════════════════════════════════════════════════ */

    @if ($is_pdf ?? false)

    body {
        padding: 0 !important;
        margin: 0 !important;
        background: #fff !important;
    }

    .inv-root {
        min-height: 0 !important;
        height: auto !important;
        display: block !important;
        width: auto !important;
        margin: 0 !important;
        padding: 0 !important;
    }

    @endif


    /* ═══════════════════════════════════════════════════════════════
       TERMS & CONDITIONS
       Quill Rich Text Editor Output
       ═══════════════════════════════════════════════════════════════ */

    .terms-text-content {
        width: 100% !important;
        max-width: 100% !important;
        min-width: 0 !important;

        display: block !important;

        margin: 0 !important;
        padding: 0 !important;

        /*
         * Normal word wrapping.
         *
         * IMPORTANT:
         * Do NOT use:
         *     word-break: break-word;
         *     word-break: break-all;
         *     overflow-wrap: anywhere;
         *
         * Those can split normal words in the PDF.
         */
        white-space: normal !important;
        word-break: {{ ($data['document_module'] ?? '') === 'quotations' ? 'normal' : 'break-word' }} !important;
        overflow-wrap: {{ ($data['document_module'] ?? '') === 'quotations' ? 'normal' : 'break-word' }} !important;
    }
    @if (($data['document_module'] ?? '') === 'quotations')
    .terms-text-content, .terms-text-content *,
    .customer-notes-content, .customer-notes-content * {
        word-break: normal !important;
        overflow-wrap: normal !important;
        word-wrap: normal !important;
        hyphens: none !important;
    }
    @endif
    .terms-text-content p {
        margin: 0 0 2px 0 !important;
        padding: 0 !important;
        line-height: 1.3 !important;
    }


    /*
     * Quill generated elements
     *
     * Force all normal text elements to keep complete words together.
     */
    .terms-text-content p,
    .terms-text-content span,
    .terms-text-content div,
    .terms-text-content li,
    .terms-text-content ol,
    .terms-text-content ul{
        white-space: normal !important;
        
        /* Safe wrapping that respects word boundaries */
        word-wrap: break-word !important; 
        overflow-wrap: break-word !important;
        
        /* Keeps standard hyphenation rules */
        word-break: normal !important; 

        text-align: left !important;
        max-width: 100% !important;
        box-sizing: border-box !important;
    }
    .terms-text-content pre {

        white-space: normal !important;

        word-break: normal !important;
        overflow-wrap: normal !important;
        word-wrap: normal !important;

        text-align: left !important;

        max-width: 100% !important;
    }


    /* ═══════════════════════════════════════════════════════════════
       TERMS PARAGRAPHS
       ═══════════════════════════════════════════════════════════════ */

    .terms-text-content p {

        margin: 0 0 2px 0 !important;
        padding: 0 !important;

        line-height: 1.3 !important;
    }

    .terms-text-content p:last-child {
        margin-bottom: 0 !important;
    }


    /* ═══════════════════════════════════════════════════════════════
       TERMS LISTS
       ═══════════════════════════════════════════════════════════════ */

    .terms-text-content ol,
    .terms-text-content ul {

        margin: 0 0 2px 16px !important;
        padding: 0 !important;

        white-space: normal !important;
        word-break: normal !important;
        overflow-wrap: normal !important;
    }

    .terms-text-content li {

        margin: 0 0 1px 0 !important;
        padding: 0 !important;

        line-height: 1.3 !important;

        white-space: normal !important;
        word-break: normal !important;
        overflow-wrap: normal !important;
    }


    /* ═══════════════════════════════════════════════════════════════
       QUILL CODE / PRE BLOCKS
       ═══════════════════════════════════════════════════════════════ */

    .terms-text-content pre {

        white-space: pre-wrap !important;

        word-break: normal !important;
        overflow-wrap: normal !important;

        max-width: 100% !important;

        margin: 0 !important;
        padding: 0 !important;
    }


    /* ═══════════════════════════════════════════════════════════════
       LINKS
       ═══════════════════════════════════════════════════════════════ */

    /*
     * Normal text keeps words intact.
     *
     * URLs are allowed to break when they are too long,
     * preventing them from overflowing the PDF.
     */
    .terms-text-content a {

        white-space: normal !important;

        word-break: normal !important;
        overflow-wrap: anywhere !important;

        max-width: 100% !important;
    }


    /* ═══════════════════════════════════════════════════════════════
       IMAGES
       ═══════════════════════════════════════════════════════════════ */

    .terms-text-content img {

        max-width: 100% !important;
        height: auto !important;
    }


    /* ═══════════════════════════════════════════════════════════════
       TABLES INSIDE TERMS
       ═══════════════════════════════════════════════════════════════ */

    .terms-text-content table {

        width: 100% !important;
        max-width: 100% !important;

        border-collapse: collapse !important;
    }

    .terms-text-content td,
    .terms-text-content th {

        max-width: 100% !important;

        white-space: normal !important;
        word-break: normal !important;
        overflow-wrap: normal !important;
    }


    /* ═══════════════════════════════════════════════════════════════
       QUILL INLINE FORMATTING OVERRIDES
       ═══════════════════════════════════════════════════════════════ */

    /*
     * Quill may generate inline styles such as:
     *
     * white-space: nowrap;
     * word-break: break-all;
     *
     * These overrides ensure the Terms section follows
     * the PDF's normal wrapping rules.
     */

    .terms-text-content [style*="white-space"] {

        white-space: normal !important;
    }

    .terms-text-content [style*="word-break"] {

        word-break: normal !important;
    }

    .terms-text-content [style*="overflow-wrap"] {

        overflow-wrap: normal !important;
    }


    /* ═══════════════════════════════════════════════════════════════
       PREVENT TERMS CONTENT FROM FORCING HORIZONTAL OVERFLOW
       ═══════════════════════════════════════════════════════════════ */

    .terms-text-content * {

        max-width: 100% !important;
    }

</style>