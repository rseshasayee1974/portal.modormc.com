<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class WebSocketService
{
    /**
     * Broadcast an event to a WebSocket channel.
     *
     * @param string $channel The channel name (e.g., 'gps-tracking', 'batches')
     * @param array $data The payload to broadcast
     * @return bool True if successful, false otherwise
     */
    public static function broadcast(string $channel, array $data): bool
    {
        try {
            $wsPort = env('WS_PORT', 6001);
            $response = Http::timeout(2)->post("http://127.0.0.1:{$wsPort}/broadcast", [
                'channel' => $channel,
                'data' => $data,
            ]);
            return $response->successful();
        } catch (\Exception $e) {
            Log::warning("WebSocket broadcast failed for channel {$channel}: " . $e->getMessage());
            return false;
        }
    }
}
