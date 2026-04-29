{{-- resources/views/pdfs/partials/_common_styles.blade.php
     Shared CSS reset + common variables used by ALL templates --}}
<style>
    /* ═══ RESET & BASE ═══ */
    @page { margin: 0; size: A4; }
    * { box-sizing: border-box; margin: 0; padding: 0; }

    :root {
        --font-base: Arial, Helvetica, sans-serif;
        --font-serif: Georgia, 'Times New Roman', serif;
        --color-ink: #111;
        --color-muted: #666;
        --color-light: #444;
        --color-border: #aaa;
        --color-border-light: #e0e0e0;
        --color-header-bg: #3a3a3a;
        --color-alt-bg: #f0f0f0;
        --color-balance-bg: #f2f2f2;
        --color-red: #cc0000;
        --color-green: #16a34a;
        --color-amber: #d97706;
        --size-base: 11px;
        --size-small: 10px;
        --size-xsmall: 9.5px;
        --size-title: 26px;
    }

    body {
        font-family: var(--font-base);
        font-size: var(--size-base);
        color: var(--color-ink);
        background: #fff;
        line-height: 1.5;
    }

    /* ═══ UTILITY CLASSES (all templates) ═══ */
    .text-left   { text-align: left !important; }
    .text-right  { text-align: right !important; }
    .text-center { text-align: center !important; }
    .bold        { font-weight: 700; }
    .italic      { font-style: italic; }
    .red         { color: var(--color-red); }
    .muted       { color: var(--color-muted); }
    .small       { font-size: var(--size-small); }
    .underline   { text-decoration: underline; }

    /* ═══ ITEMS TABLE SHARED ═══ */
    .item-name { font-weight: 700; color: var(--color-ink); }
    .item-sub  { font-size: var(--size-xsmall); color: #888; margin-top: 1px; }

    .badge-done    { color: var(--color-green); font-weight: 700; }
    .badge-pending { color: var(--color-amber); font-weight: 700; }

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
