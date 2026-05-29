{{-- resources/views/pdfs/partials/_common_styles.blade.php
     Shared CSS reset + common variables used by ALL templates --}}
<style>
    /* ═══ RESET & BASE ═══ */
    @page { 
        margin: 15mm; 
        @bottom-right {
            content: "Page " counter(page) " of " counter(pages);
        }
    }
    * { box-sizing: border-box; margin: 0; padding: 0; }

    :root {
        --font-base: 'Segoe UI', Roboto, Helvetica, Arial, sans-serif;
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

    body {
        font-family: 'Segoe UI', Roboto, Helvetica, Arial, sans-serif;
        font-size: 11px;
        color: #1e293b;
        background: #fff;
        line-height: 1.5;
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
    .item-sub  { font-size: 9px; color: #888; margin-top: 1px; }

    .badge-done    { color: #10b981; font-weight: 700; }
    .badge-pending { color: #f59e0b; font-weight: 700; }

    /* ═══ FOOTER SHARED ═══ */
    .powered-footer {
        display: table;
        width: 100%;
        padding: 5px 12px;
        border-top: 1px solid #ccc;
        font-size: 9px;
    }
    .powered-footer .pf-left  { display: table-cell; color: #999; text-transform: uppercase; letter-spacing: 0.04em; vertical-align: middle; }
    .powered-footer .pf-right { display: table-cell; text-align: right; color: #888; font-size: 10px; vertical-align: middle; }
    .powered-footer .pf-brand { font-weight: 700; color: #555; font-size: 10px; }
</style>
