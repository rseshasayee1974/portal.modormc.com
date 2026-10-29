<?php

/*
Author: ragul-onemodo
Created: 2026-10-17 14:14:27 Asia/Calcutta (UTC+05:30)
*/

return [
    'tally_url' => env('GST_TALLY_URL'),
    // Override individual web ledger IDs only after verifying exact target-company master names.
    'tally_ledger_map' => [],
    // Set once for this installation and preserve it when changing domains or restoring backups.
    'tally_namespace' => env('GST_TALLY_NAMESPACE') ?: env('APP_URL', 'http://localhost'),
    'rule_template' => [
        'engine' => 'reviewed-gst-v1',
        'verified_on' => '2026-10-17',
        'from_period' => '2025-10',
        'to_period' => '2026-10',
        'sources' => [
            'https://cbic-gst.gov.in/pdf/Circular-170-02-2022-GST.pdf',
            'https://tutorial.gst.gov.in/userguide/returns/GSTR3B.htm',
            'https://tutorial.gst.gov.in/downloads/news/creative_faq_on_gstr9_for_24_25_dt_15_oct_25_v6_final.pdf',
            'https://gstcouncil.gov.in/sites/default/files/2026-02/newsletter_january_issue.pdf',
            'https://gstcouncil.gov.in/sites/default/files/2026-04/newsletter-march-2026.pdf',
        ],
    ],
];
