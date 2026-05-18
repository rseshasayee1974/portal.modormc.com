<?php

namespace App\Services;

use App\Models\Plant;
use Illuminate\Support\Facades\Log;

class ConnectivityService
{
    /**
     * Check if a given IP is reachable via Ping.
     * 
     * @param string $ip
     * @return bool
     */
    public function ping(string $ip): bool
    {
        if (empty($ip)) {
            return false;
        }

        // Determine OS and appropriate ping command
        $os = strtoupper(substr(PHP_OS, 0, 3));
        
        if ($os === 'WIN') {
            // Windows: -n 1 (number of packets), -w 1000 (timeout in ms)
            $command = "ping -n 1 -w 1000 " . escapeshellarg($ip);
        } else {
            // Linux/Unix: -c 1 (count), -W 1 (timeout in seconds)
            $command = "ping -c 1 -W 1 " . escapeshellarg($ip);
        }

        exec($command, $output, $resultCode);

        // result code 0 means success
        return $resultCode === 0;
    }

    /**
     * Update the heartbeat for a plant if reachable.
     * 
     * @param Plant $plant
     * @return bool
     */
    public function monitorPlant(Plant $plant): bool
    {
        if (!$plant->plc_ip) {
            return false;
        }

        $isAlive = $this->ping($plant->plc_ip);

        if ($isAlive) {
            $plant->update(['last_heartbeat_at' => now()]);
        }

        return $isAlive;
    }
}
