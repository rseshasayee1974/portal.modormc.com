<?php
/*
Author: ragul-onemodo
Created: 2026-10-13 10:54:58 Asia/Calcutta (UTC+05:30)
*/

return [
    'allowed_origins' => array_filter(array_map('trim', explode(',', (string)env('MCP_ALLOWED_ORIGINS', '')))),
];
