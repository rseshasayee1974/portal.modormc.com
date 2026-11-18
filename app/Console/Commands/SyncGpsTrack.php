<?php
/*
Author: ragul-onemodo
Created: 2026-11-16 13:18:41 Asia/Calcutta (UTC+05:30)
*/

namespace App\Console\Commands;

use App\Http\Controllers\Api\GpsTelemetryController;
use App\Models\GpsDevice;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

class SyncGpsTrack extends Command
{
    protected $signature = 'gps:sync-gpstrack {--daemon : Run continuously in background} {--interval=900 : Seconds between polls in daemon mode (default: 900s / 15 mins)}';
    protected $description = 'Import the latest GPSTrack positions for registered devices';

    public function handle(GpsTelemetryController $telemetry): int
    {
        $isDaemon = (bool) $this->option('daemon');
        $interval = max(30, (int) $this->option('interval'));

        if ($isDaemon) {
            $mins = round($interval / 60, 1);
            $this->info("Starting continuous GPSTrack background telemetry sync (polling every {$interval}s / {$mins} mins)...");
            while (true) {
                try {
                    $this->syncAll($telemetry);
                } catch (\Throwable $e) {
                    $this->error("Sync loop error: " . $e->getMessage());
                }
                sleep($interval);
            }
        }

        return $this->syncAll($telemetry);
    }

    private function syncAll(GpsTelemetryController $telemetry): int
    {
        $globalToken = env('GPSTRACK_TOKEN') ?: env('API_TOKEN') ?: env('GPSTRACK_API_TOKEN');
        $globalEmail = env('GPSTRACK_EMAIL', 'ragulonemodoapi@gpstrack.in');

        // Gather unique credential pairs (global credentials + any per-device overrides)
        $credentialSets = [];
        if ($globalToken && $globalEmail) {
            $credentialSets[] = ['email' => $globalEmail, 'token' => $globalToken];
        }

        $deviceCreds = GpsDevice::where('is_active', true)
            ->whereNotNull('gpstrack_email')
            ->whereNotNull('gpstrack_token')
            ->get(['gpstrack_email', 'gpstrack_token'])
            ->unique(fn($d) => $d->gpstrack_email . '::' . $d->gpstrack_token);

        foreach ($deviceCreds as $dc) {
            if (!empty($dc->gpstrack_email) && !empty($dc->gpstrack_token)) {
                $already = false;
                foreach ($credentialSets as $cs) {
                    if ($cs['email'] === $dc->gpstrack_email && $cs['token'] === $dc->gpstrack_token) {
                        $already = true;
                        break;
                    }
                }
                if (!$already) {
                    $credentialSets[] = ['email' => $dc->gpstrack_email, 'token' => $dc->gpstrack_token];
                }
            }
        }

        if (empty($credentialSets)) {
            $this->warn('No GPSTrack credentials configured in .env or devices table.');
            return self::FAILURE;
        }

        $allRows = [];
        $apiUrl = rtrim(env('GPSTRACK_BASE_URL', 'https://app.gpstrack.in/api'), '/') . '/get_current_data';

        foreach ($credentialSets as $creds) {
            try {
                $response = Http::withoutVerifying()
                    ->acceptJson()
                    ->withoutRedirecting()
                    ->timeout(20)
                    ->get($apiUrl, [
                        'token' => $creds['token'],
                        'email' => $creds['email'],
                    ]);

                $data = $response->json();
                if (is_string($data['message'] ?? null) && str_contains($data['message'], 'Rate limit reached')) {
                    $this->warn('GPSTrack rate limit reached. Waiting for next cycle.');
                    continue;
                }
                if ($response->successful() && is_array($data) && array_is_list($data)) {
                    foreach ($data as $row) {
                        if (is_array($row) && isset($row['latitude'], $row['longitude'])) {
                            $id = (string) ($row['deviceId'] ?? '');
                            $allRows[$id] = $row;
                        }
                    }
                } else {
                    $this->warn('Invalid response from GPSTrack: ' . ($data['message'] ?? 'HTTP ' . $response->status()));
                }
            } catch (ConnectionException $e) {
                $this->error('Could not connect to GPSTrack: ' . $e->getMessage());
            } catch (\Throwable $e) {
                $this->error('Error fetching GPSTrack data: ' . $e->getMessage());
            }
        }

        if (empty($allRows)) {
            $this->info('No live telemetry rows received from GPSTrack.');
            return self::SUCCESS;
        }

        // Load all active GPS devices with their machine relation
        $devices = GpsDevice::with('machine')->where('is_active', true)->get();

        $saved = 0;
        $matched = 0;

        foreach ($allRows as $rawId => $row) {
            $rawIdStr = trim((string)$rawId);
            $cleanId = strlen($rawIdStr) === 16 && str_starts_with($rawIdStr, '0') ? substr($rawIdStr, 1) : $rawIdStr;
            $regNo = trim((string)($row['regNo'] ?? $row['vehicleNo'] ?? ''));
            $normReg = strtoupper(preg_replace('/[^A-Za-z0-9]/', '', $regNo));

            // Find matching device in database
            $matchedDevice = $devices->first(function ($d) use ($rawIdStr, $cleanId, $normReg) {
                $devImei = (string)$d->imei;
                $devClean = strlen($devImei) === 16 && str_starts_with($devImei, '0') ? substr($devImei, 1) : $devImei;
                $devMachineReg = strtoupper(preg_replace('/[^A-Za-z0-9]/', '', (string)($d->machine?->registration ?? '')));

                return $devImei === $rawIdStr
                    || $devImei === $cleanId
                    || $devClean === $cleanId
                    || ($normReg !== '' && $normReg === $devMachineReg);
            });

            // If not found in DB, auto-register or link to machine in default plant
            if (!$matchedDevice) {
                try {
                    $defaultPlantId = session('active_plant_id') ?: \App\Models\Plant::where('is_active', 1)->value('id') ?: 1;
                    $machine = null;
                    if ($normReg !== '') {
                        $machine = \App\Models\Machine::where('plant_id', $defaultPlantId)
                            ->whereRaw("UPPER(REPLACE(REPLACE(registration, ' ', ''), '-', '')) = ?", [$normReg])
                            ->first();
                    }
                    if (!$machine) {
                        $machine = \App\Models\Machine::create([
                            'plant_id' => $defaultPlantId,
                            'registration' => $regNo ?: ($row['vehicleId'] ?? $cleanId),
                            'vehicle_model' => $row['vehicleType'] ?? 'GPS Tracker',
                            'vehicle_type' => $row['vehicleType'] ?? 'Fleet Vehicle',
                            'is_active' => true,
                        ]);
                    }

                    $matchedDevice = GpsDevice::create([
                        'plant_id' => $defaultPlantId,
                        'machine_id' => $machine->id,
                        'imei' => $cleanId,
                        'device_model' => $row['vehicleType'] ?? 'FleetTrack',
                        'is_active' => true,
                    ]);

                    $devices->push($matchedDevice);
                } catch (\Throwable $e) {
                    $this->warn("Auto-register failed for device {$cleanId}: {$e->getMessage()}");
                }
            }

            if (!$matchedDevice) continue;
            $matched++;

            try {
                $fix = !empty($row['isoDate']) ? Carbon::parse($row['isoDate']) : now();
                $received = isset($row['serverTime']) && is_numeric($row['serverTime'])
                    ? Carbon::createFromTimestampMs($row['serverTime']) : $fix;

                $req = Request::create('/api/gps/telemetry', 'POST', [
                    'imei' => $matchedDevice->imei,
                    'latitude' => floatval($row['latitude']),
                    'longitude' => floatval($row['longitude']),
                    'speed' => floatval($row['speed'] ?? 0),
                    'heading' => floatval($row['bearing'] ?? 0),
                    'ignition' => strtoupper(trim((string)($row['ignitionStatus'] ?? ''))) === 'ON',
                    'odometer' => floatval($row['odoDistance'] ?? 0),
                    'recorded_at' => $fix->toIso8601String(),
                ]);

                $req->attributes->set('gps_provider_received_at', $received->copy()->setTimezone(config('app.timezone')));
                $req->attributes->set('gps_provider_device_id', $matchedDevice->id);
                $req->attributes->set('gps_provider_plant_id', $matchedDevice->plant_id);

                $result = $telemetry->ingest($req);
                if ($result->isSuccessful() && empty($result->getData(true)['skipped'])) {
                    $saved++;
                }
            } catch (\Throwable $e) {
                $this->error("Error saving telemetry for device {$matchedDevice->imei}: {$e->getMessage()}");
            }
        }

        $nowStr = now()->toDateTimeString();
        $this->info("[{$nowStr}] GPSTrack sync completed: {$matched} matched, {$saved} new timeline positions saved.");
        return self::SUCCESS;
    }
}
