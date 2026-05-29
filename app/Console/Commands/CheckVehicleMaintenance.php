<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\Logistics\MaintenanceSchedulerService;

class CheckVehicleMaintenance extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'fleet:check-maintenance';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Sync vehicle running status and trigger preventive maintenance scheduler based on latest GPS odometer telemetry.';

    /**
     * Execute the console command.
     */
    public function handle(MaintenanceSchedulerService $service)
    {
        $this->info("Scanning active vehicle service schedules...");
        
        $updated = $service->syncAllActiveServices();
        
        $this->info("Synchronization complete. Processed {$updated} active vehicle service records.");
        return 0;
    }
}
