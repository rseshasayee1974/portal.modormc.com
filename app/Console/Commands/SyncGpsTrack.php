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
    protected $signature = 'gps:sync-gpstrack';
    protected $description = 'Import the latest GPSTrack positions for registered devices';

    public function handle(GpsTelemetryController $telemetry): int
    {
        $failed = false;
        $devices = GpsDevice::where('is_active', true)->whereNotNull('machine_id')
            ->whereNotNull('gpstrack_email')->whereNotNull('gpstrack_token')->lazyById();
        foreach ($devices as $device) {
            try {
                if ($this->syncDevice($device, $telemetry) !== self::SUCCESS) {
                    $failed = true;
                }
            } catch (\Exception) {
                $this->error("Sync failed for device {$device->id} in plant {$device->plant_id}.");
                $failed = true;
            }
        }
        return $failed ? self::FAILURE : self::SUCCESS;
    }

    private function syncDevice(GpsDevice $device, GpsTelemetryController $telemetry): int
    {
        $credentials = ['email' => $device->gpstrack_email, 'token' => $device->gpstrack_token];
        try {
            $response = Http::acceptJson()->withoutRedirecting()->timeout(20)
                ->get('https://app.gpstrack.in/api/get_current_data', $credentials);
        } catch (ConnectionException) {
            $this->error('Could not connect to GPSTrack.');
            return self::FAILURE;
        }
        $rows = $response->json();
        if (is_string($rows['message'] ?? null) && str_contains($rows['message'], 'Rate limit reached')) {
            $this->warn('GPSTrack rate limit reached. The next scheduled poll will retry.');
            return self::FAILURE;
        }
        if (!$response->successful() || !is_array($rows) || !array_is_list($rows)) {
            $this->error('Invalid GPSTrack response (HTTP '.$response->status().').');
            return self::FAILURE;
        }
        $saved = $matched = $failed = 0;
        foreach ($rows as $row) {
            $id = (string) ($row['deviceId'] ?? '');
            if ($id === '') continue;
            $imei = strlen($id) === 16 && str_starts_with($id, '0') ? substr($id, 1) : $id;
            if (!in_array((string) $device->imei, [$id, $imei], true)) continue;
            $matched++;
            try {
                if (empty($row['isoDate'])) {
                    throw new \UnexpectedValueException();
                }
                $fix = Carbon::parse($row['isoDate']);
                $received = isset($row['serverTime']) && is_numeric($row['serverTime'])
                    ? Carbon::createFromTimestampMs($row['serverTime']) : $fix;
                $request = Request::create('/api/gps/telemetry', 'POST', [
                    'imei' => $device->imei,
                    'latitude' => $row['latitude'] ?? null, 'longitude' => $row['longitude'] ?? null,
                    'speed' => $row['speed'] ?? null, 'heading' => $row['bearing'] ?? null,
                    'ignition' => strtoupper($row['ignitionStatus'] ?? '') === 'ON',
                    'odometer' => $row['odoDistance'] ?? null, 'recorded_at' => $fix->toIso8601String(),
                ]);
                $request->attributes->set('gps_provider_received_at', $received->copy()->setTimezone(config('app.timezone')));
                $request->attributes->set('gps_provider_device_id', $device->id);
                $request->attributes->set('gps_provider_plant_id', $device->plant_id);
                $result = $telemetry->ingest($request);
                if (!$result->isSuccessful()) {
                    $failed++;
                } elseif (empty($result->getData(true)['skipped'])) {
                    $saved++;
                }
            } catch (\Exception) {
                $failed++;
            }
        }
        $this->info("Plant {$device->plant_id}, device {$device->id}: matched {$matched}, saved {$saved}, failed {$failed}.");
        return $failed ? self::FAILURE : self::SUCCESS;
    }
}
