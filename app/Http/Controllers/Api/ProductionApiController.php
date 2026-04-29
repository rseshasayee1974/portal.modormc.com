<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

class ProductionApiController extends Controller
{
    public function getConsumption(Request $request)
    {
        $batchNo = $request->query('batch_no', '4');
        $custId = $request->query('cust_id', 'C0001');
        $recId = $request->query('rec_id', 'M20 GRD');

        // ------------------------------------------------------------------
        // REAL THIRD-PARTY API CALL (Uncomment and replace URL when ready)
        // ------------------------------------------------------------------
        /*
        try {
            $response = Http::timeout(5)->get('http://YOUR_THIRD_PARTY_URL/api/batch', [
                'batch_no' => $batchNo,
                'cust_id' => $custId,
                'rec_id' => $recId
            ]);
            
            if ($response->successful()) {
                return $response->json();
            }
            return response()->json(['error' => 'Third-party API error'], $response->status());
        } catch (\Exception $e) {
            return response()->json(['error' => 'Connection to third-party failed'], 500);
        }
        */

        // ------------------------------------------------------------------
        // MOCK SIMULATION (For local testing)
        // ------------------------------------------------------------------
        return response()->json([
            "plant_type" => "CP 30",
            "plant_sl" => "474",
            "order_no" => "01",
            "batch_no" => $batchNo,
            "cust_id" => $custId,
            "site_id" => "PANCHSHIL",
            "truck_id" => "MH26BE8292",
            "driver" => "DATTA",
            "start" => "2023-12-16 07:41:54",
            "end" => "2023-12-16 08:01:48",
            "rec_id" => $recId,
            "rec_name" => $recId,
            "qty" => "7.0002",
            "mat" => [
                ["item" => "12 MM", "act" => 3.629],
                ["item" => "Sand", "act" => 1.622],
                ["item" => "20 MM", "act" => 1.144],
                ["item" => "RAMCO", "act" => 9.709],
            ["item" => "UltraTech", "act" => 9.509],
                ["item" => "WATER", "act" => 10.346]
            ]
        ]);
    }
}
