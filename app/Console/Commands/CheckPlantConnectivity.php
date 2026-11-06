<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class CheckPlantConnectivity extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'plants:monitor';
    protected $description = 'Monitor network connectivity for all active plant PLCs';

    public function handle(\App\Services\ConnectivityService $connectivityService)
    {
        $plants = \App\Models\Plant::where('is_active', true)
            ->whereNotNull('plc_ip')
            ->get();

        $this->info("Checking connectivity for " . $plants->count() . " plants...");

        foreach ($plants as $plant) {
            $isAlive = $connectivityService->monitorPlant($plant);
            $statusText = $isAlive ? 'ONLINE' : 'OFFLINE';
            
            $cacheKey = "plant_status_" . $plant->id;
            $lastStatus = \Illuminate\Support\Facades\Cache::get($cacheKey);

            $statusFormatted = $isAlive ? '<info>ONLINE</info>' : '<error>OFFLINE</error>';
            $this->line("Plant: {$plant->name} ({$plant->plc_ip}) - Status: {$statusFormatted}");

            // Only send email if status has changed
            if ($lastStatus !== $statusText) {
                $this->info("   -> Status changed from " . ($lastStatus ?? 'UNKNOWN') . " to {$statusText}. Sending alert...");
                
                \Illuminate\Support\Facades\Mail::to('ragul@onemodo.com')
                    ->send(new \App\Mail\PlantOfflineAlert($plant, $statusText));

                \Illuminate\Support\Facades\Cache::put($cacheKey, $statusText);
            }
        }

        $this->info("Monitoring complete.");
    }
}
