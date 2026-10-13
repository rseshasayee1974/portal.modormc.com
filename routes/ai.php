<?php
/*
Author: ragul-onemodo
Created: 2026-10-13 10:54:58 Asia/Calcutta (UTC+05:30)
*/

use App\Mcp\Servers\ErpServer;
use App\Http\Middleware\McpPlantContext;
use Laravel\Mcp\Facades\Mcp;

Mcp::web('/mcp/erp', ErpServer::class)
    ->middleware(['api', 'auth:sanctum', McpPlantContext::class, \App\Http\Middleware\SetEntityTimezone::class, 'throttle:60,1,erp-mcp'])
    ->withoutMiddleware(\App\Http\Middleware\TitleCaseInputs::class);
