<?php

/*
Author: ragul-onemodo
Created: 2026-10-25 19:52:48 Asia/Calcutta (UTC+05:30)
*/

return [
    'enabled' => env('PRODUCTION_READ_CACHE_ENABLED', env('APP_ENV') !== 'testing'),
    'store' => env('PRODUCTION_READ_CACHE_STORE', 'redis'),
    'ttl' => 15,
    'schedule_ttl' => 10,
    // Changes invalidate production reads only, including query-builder and job writes.
    'tables' => [
        'mm_batches', 'mm_batch_materials', 'mm_dispatches', 'mm_dispatch_statuses',
        'mm_dispatch_payments', 'mm_dispatch_financials', 'mm_concrete_batching_schedules',
        'mm_sales_orders', 'mm_custom_settings', 'mm_invoices', 'mm_einvoice_invoice_rel',
        'mm_ewaybill_details', 'mm_plants', 'mm_patrons', 'mm_sites', 'mm_machines',
        'mm_personnels', 'mm_users', 'mm_mix_designs', 'mm_mix_design_items',
        'mm_concrete_grades', 'mm_products', 'mm_product_units', 'mm_quotations',
        'mm_quotation_items', 'mm_customer_pos', 'mm_customer_po_items',
    ],
];
