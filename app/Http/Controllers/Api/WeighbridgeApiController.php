<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class WeighbridgeApiController extends Controller
{
    public function sendAlert(Request $request, \App\Services\AiService $aiService)
    {
        $errorDetails = $request->input('errors', []);
        $recipient = 'ragul@onemodo.com';

        // ── AI DIAGNOSIS ──
        $diagnosis = $aiService->diagnoseError($errorDetails);

        // ── CONTEXT ──
        $user = auth()->user();
        $plant = $user->defaultPlant;

        try {
            \Illuminate\Support\Facades\Mail::to($recipient)->send(new \App\Mail\WeighbridgeAlert($errorDetails, $diagnosis, $user, $plant));
            
            return response()->json([
                'status' => true,
                'diagnosis' => $diagnosis,
                'message' => 'AI-diagnosed weighbridge alert sent successfully.'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => false,
                'message' => 'Failed to send alert: ' . $e->getMessage()
            ], 500);
        }
    }
}
