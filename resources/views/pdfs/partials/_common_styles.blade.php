{{-- resources/views/pdfs/partials/_common_styles.blade.php
     Shared CSS reset + common variables used by ALL templates --}}
<style>
    /* ═══ RESET & BASE ═══ */
    @page { 
        size: a4 portrait;
        margin: 10mm; 
    }
    * { box-sizing: border-box; margin: 0; padding: 0; }

    :root {
        --font-base: 'DejaVu Sans', 'Segoe UI', Roboto, Helvetica, Arial, sans-serif;
        --font-serif: Georgia, 'Times New Roman', serif;
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

    html, body {
        margin: 0 !important;
        padding: 0 !important;
        width: 100%;
    }
    body {
        font-family: 'DejaVu Sans', 'Segoe UI', Roboto, Helvetica, Arial, sans-serif;
        font-size: 11px;
        color: #1e293b;
        background: #fff;
        line-height: 1.5;
    }

    .inv-root {
        border: 1px solid #cbd5e1;
        background: #fff;
    }

    /* ═══ UTILITY CLASSES (all templates) ═══ */
    .text-left   { text-align: left !important; }
    .text-right  { text-align: right !important; }
    .text-center { text-align: center !important; }
    .bold        { font-weight: 700; }
    .italic      { font-style: italic; }
    .red         { color: #ef4444; }
    .muted       { color: #64748b; }
    .small       { font-size: 10px; }
    .underline   { text-decoration: underline; }

    /* ═══ ITEMS TABLE SHARED ═══ */
    .item-name { font-weight: 700; color: #1e293b; }
    .item-sub  { font-size: 9px; color: #888; margin-top: 1px; white-space: pre-wrap; }

    .badge-done    { color: #10b981; font-weight: 700; }
    .badge-pending { color: #f59e0b; font-weight: 700; }

    /* ═══ FOOTER SHARED ═══ */
    .powered-footer {
        display: table;
        width: 100%;
        border-top: 1px solid #ccc;
        font-size: 9px;
    }
    .powered-footer .pf-left  { display: table-cell; color: #999; text-transform: uppercase; letter-spacing: 0.04em; vertical-align: middle;}
    .powered-footer .pf-right { display: table-cell; text-align: right; color: #888; font-size: 10px; vertical-align: middle; padding: 5px 12px; }
    .powered-footer .pf-brand { font-weight: 700; color: #555; font-size: 10px; }

    /* Centered A4 preview styling for browser/screen view */
    @media screen {
        body {
            background-color: #f8fafc !important;
            padding: 40px 15px !important;
        }
        .inv-root {
            max-width: 800px;
            margin: 0 auto !important;
            background: #fff !important;
            box-shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.1), 0 8px 10px -6px rgba(0, 0, 0, 0.1) !important;
            border-radius: 8px !important;
            border: 1px solid #cbd5e1 !important;
        }
    }
    
    @media print {
        body {
            padding: 0 !important;
            margin: 0 !important;
            background: #fff !important;
        }
        .inv-root {
            min-height: 0 !important;
            height: auto !important;
            border: none !important;
            display: block !important;
            width: auto !important;
            margin: 0 !important;
            padding: 0 !important;
            box-shadow: none !important;
        }
    }
    
    @if ($is_pdf ?? false)
    body {
        padding: 0 !important;
        margin: 0 !important;
        background: #fff !important;
    }
    .inv-root {
        min-height: 0 !important;
        height: auto !important;
        border: none !important;
        display: block !important;
        width: auto !important;
        margin: 0 !important;
        padding: 0 !important;
    }
    @endif

    /* Terms text formatting overrides (for Quill rich text editor output) */
    .terms-text-content {
        width: 100% !important;
        min-width: 0 !important;
        display: block !important;
        margin: 0 !important;
        padding: 0 !important;
    }
    .terms-text-content,
    .terms-text-content p,
    .terms-text-content span,
    .terms-text-content li,
    .terms-text-content ol,
    .terms-text-content ul,
    .terms-text-content pre,
    .terms-text-content div {
        white-space: normal !important;
        word-break: break-word !important;
        overflow-wrap: break-word !important;
    }
    .terms-text-content p {
        margin: 0 0 2px 0 !important;
        padding: 0 !important;
        line-height: 1.3 !important;
    }
    .terms-text-content p:last-child {
        margin-bottom: 0 !important;
    }
    .terms-text-content ol, .terms-text-content ul {
        margin: 0 0 2px 16px !important;
        padding: 0 !important;
    }
    .terms-text-content li {
        margin-bottom: 1px !important;
        padding: 0 !important;
        line-height: 1.3 !important;
    }
</style>
