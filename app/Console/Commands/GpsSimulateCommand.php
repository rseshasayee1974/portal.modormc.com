<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\GpsDevice;
use App\Models\Machine;
use App\Models\GpsLatestPosition;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class GpsSimulateCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'gps:simulate {--imei= : Specific IMEI to simulate} {--steps=100 : Number of coordinates to simulate}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Simulate vehicle GPS movements and post telemetry coordinates to the API.';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $imei = $this->option('imei');
        $steps = intval($this->option('steps'));

        // Resolve or create a device to simulate
        if ($imei) {
            $device = GpsDevice::where('imei', $imei)->first();
            if (!$device) {
                $this->error("Device with IMEI {$imei} not found.");
                return 1;
            }
        } else {
            // Find any active device mapped to a machine
            $device = GpsDevice::whereNotNull('machine_id')->where('is_active', true)->first();
            
            if (!$device) {
                $this->info("No GPS device mapped to a vehicle was found. Creating a mock device...");
                
                $machine = Machine::first();
                if (!$machine) {
                    $this->error("No machines found in the database. Please seed/create a machine first.");
                    return 1;
                }

                $device = GpsDevice::create([
                    'plant_id' => $machine->plant_id,
                    'machine_id' => $machine->id,
                    'imei' => 'SIMULATED999999',
                    'device_model' => 'Teltonika FMB920',
                    'sim_number' => '899100099999999999F',
                    'phone_number' => '+919999999999',
                    'is_active' => true,
                    'notes' => 'Auto-generated for GPS simulation.'
                ]);
                $this->info("Created device: IMEI SIMULATED999999 linked to vehicle {$machine->registration}");
            }
        }

        $this->info("Starting GPS simulation for IMEI: {$device->imei} (Vehicle: {$device->machine->registration})");
        $this->info("Simulating {$steps} steps. Press Ctrl+C to stop.");

        // Define a starting route point (Mumbai region if not set, or last known coordinate)
        $latest = GpsLatestPosition::where('device_id', $device->id)->first();
        
        $lat = $latest ? floatval($latest->latitude) : 19.0760;
        $lng = $latest ? floatval($latest->longitude) : 72.8777;
        $odometer = $latest ? floatval($latest->odometer) : 12450.00;
        $speed = 0.00;
        $heading = 0.00;
        
        // Mumbai standard pathway bounds (simulating small driving movements)
        $dLat = 0.0003; // directional latitude shift
        $dLng = 0.0004; // directional longitude shift

        $bar = $this->output->createProgressBar($steps);
        $bar->start();

        for ($i = 0; $i < $steps; $i++) {
            // Add some realistic driving variance (sin curves to simulate roads, speed variations)
            $ignition = true;
            
            if ($i < 5) {
                // Starting up
                $speed = $i * 10;
            } elseif ($i > $steps - 5) {
                // Decelerating to stop
                $speed = max(0, $speed - 15);
                if ($speed == 0) $ignition = false;
            } else {
                // Cruising with speed variance
                $speed = round(50 + sin($i / 5) * 15 + rand(-5, 5), 2);
            }

            // Curve coordinate shifts
            $lat += $dLat + sin($i / 8) * 0.0001;
            $lng += $dLng + cos($i / 6) * 0.0001;

            // Calculate bearing/heading
            $heading = round(rad2deg(atan2($dLng, $dLat)));
            if ($heading < 0) $heading += 360;

            // Increment odometer (speed in km/h converted to km per 3-second step)
            // 3 seconds is 3/3600 hour = 1/1200 hour. distance = speed * time
            $distance = $speed * (3 / 3600);
            $odometer += $distance;

            $payload = [
                'imei' => $device->imei,
                'latitude' => round($lat, 8),
                'longitude' => round($lng, 8),
                'speed' => $speed,
                'heading' => $heading,
                'altitude' => 24.50,
                'ignition' => $ignition,
                'odometer' => round($odometer, 2),
                'recorded_at' => now()->toIso8601String()
            ];

            // Post to endpoint (try HTTP request, fallback to internal call or logging)
            try {
                // Local absolute URL
                $url = route('api.gps.telemetry');
                if (str_contains($url, 'localhost') || str_contains($url, '127.0.0.1')) {
                    $response = Http::post($url, $payload);
                } else {
                    // Fallback to local server path
                    $response = Http::post("http://127.0.0.1:8000/api/gps/telemetry", $payload);
                }
            } catch (\Exception $e) {
                // If API server is not running locally, directly call controller ingest method for simulation
                $controller = app()->make(\App\Http\Controllers\Api\GpsTelemetryController::class);
                $requestObj = new \Illuminate\Http\Request();
                $requestObj->replace($payload);
                $controller->ingest($requestObj);
            }

            $bar->advance();
            sleep(3); // sleep for 3 seconds per step
        }

        $bar->finish();
        $this->newLine();
        $this->info("Simulation complete!");
        return 0;
    }
}
