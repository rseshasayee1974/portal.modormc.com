<?php

/*
Author: ragul-onemodo
Created: 2026-10-29 14:21:49 Asia/Calcutta (UTC+05:30)
*/

return [
    'enabled' => env('MACHINE_DOCUMENT_ALERTS_ENABLED', true),
    'type' => 'Machine Document Expiry',
    'send_hour' => (int) env('MACHINE_DOCUMENT_ALERT_HOUR', 9),
    'reminder_days' => [30, 15, 7, 1, 0],
    'overdue_interval_days' => 7,
    'lock_seconds' => 3600,
];
