<?php
/*
Author: ragul-onemodo
Created: 2026-10-12 13:28:21 Asia/Calcutta (UTC+05:30)
*/

namespace App\Http\Controllers\Api\SalesMobile;

use App\Http\Controllers\Controller;

class DocumentationController extends Controller
{
    public function index()
    {
        return view('sales-mobile-docs');
    }
    public function specification()
    {
        return response()->json(json_decode(file_get_contents(base_path('docs/openapi/sales-executive-mobile.openapi.json')), true, 512, JSON_THROW_ON_ERROR));
    }
}
