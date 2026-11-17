<?php

namespace App\Http\Controllers;

use App\Models\Machine;
use App\Models\GpsPosition;
use App\Models\GpsLatestPosition;
use App\Models\Geofence;
use Illuminate\Http\Request;
use Inertia\Inertia;
use App\Http\Controllers\Concerns\AuthorizesModule;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;

class GpsTrackingController extends Controller
{
    use AuthorizesModule;
    protected string $module = 'gps_device'; // group tracking actions under the GPS Device permission scope

    /**
     * Show the live GPS tracking map.
     */
    public function live()
    {
        $this->authorizeModule('menu');

        $activePlantId = session('active_plant_id');

        // Fetch live GPS data directly from GPSTrack API
        $apiToken = env('GPSTRACK_TOKEN') ?: env('API_TOKEN') ?: env('GPSTRACK_API_TOKEN');
        $apiEmail = env('GPSTRACK_EMAIL', 'ragulonemodoapi@gpstrack.in');
        $apiUrl = rtrim(env('GPSTRACK_BASE_URL', 'https://app.gpstrack.in/api'), '/') . '/get_current_data';

        $cacheKey = 'gpstrack_live_payload_' . md5($apiUrl . $apiToken . $apiEmail);
        $apiData = Cache::get($cacheKey);

        if (!$apiData) {
            Log::info('GPSTrack API Request', [
                'url' => $apiUrl,
                'email' => $apiEmail,
                'token_preview' => $apiToken ? (substr($apiToken, 0, 4) . '***' . substr($apiToken, -4)) : 'EMPTY',
            ]);

            try {
                $response = Http::withoutVerifying()
                    ->timeout((int) env('GPSTRACK_TIMEOUT', 15))
                    ->get($apiUrl, [
                        'token' => $apiToken,
                        'email' => $apiEmail,
                    ]);

                $json = $response->json();

                Log::info('GPSTrack API Response', [
                    'status' => $response->status(),
                    'successful' => $response->successful(),
                    'rows_count' => is_array($json) && array_is_list($json) ? count($json) : 0,
                    'data' => $json ?? $response->body(),
                ]);

                if (is_array($json) && array_is_list($json)) {
                    $apiData = $json;
                    // Cache for 30 seconds to respect GPSTrack rate limit
                    Cache::put($cacheKey, $apiData, now()->addSeconds(30));
                    Cache::put($cacheKey . '_last_good', $apiData, now()->addHours(24));
                } elseif (isset($json['response']) && (int)$json['response'] === 429) {
                    $apiData = Cache::get($cacheKey . '_last_good');
                }
            } catch (\Throwable $e) {
                Log::error('GPSTrack API Error', [
                    'message' => $e->getMessage(),
                    'file' => $e->getFile(),
                    'line' => $e->getLine(),
                ]);
                $apiData = Cache::get($cacheKey . '_last_good');
            }
        }

        $liveByImei = [];
        $liveByReg = [];
        $liveByVehicleId = [];
        if (is_array($apiData) && array_is_list($apiData)) {
            foreach ($apiData as $row) {
                if (!is_array($row)) continue;

                if (isset($row['deviceId'])) {
                    $rawId = trim((string) $row['deviceId']);
                    $cleanId = strlen($rawId) === 16 && str_starts_with($rawId, '0') ? substr($rawId, 1) : $rawId;
                    $liveByImei[$rawId] = $row;
                    $liveByImei[$cleanId] = $row;
                    Cache::put('gpstrack_device_meta_' . $cleanId, $row, now()->addHours(24));
                    Cache::put('gpstrack_device_meta_' . $rawId, $row, now()->addHours(24));
                }

                if (!empty($row['vehicleId']) && is_string($row['vehicleId'])) {
                    $liveByVehicleId[trim($row['vehicleId'])] = $row;
                }

                foreach (['regNo', 'vehicleNo', 'vehicle_no', 'vehicle_number', 'plateNo', 'plate_number', 'deviceName', 'device_name', 'name'] as $field) {
                    if (!empty($row[$field]) && is_string($row[$field])) {
                        $norm = strtoupper(preg_replace('/[^A-Za-z0-9]/', '', $row[$field]));
                        if ($norm !== '') {
                            $liveByReg[$norm] = $row;
                            Cache::put('gpstrack_reg_meta_' . $norm, $row, now()->addHours(24));
                        }
                    }
                }
            }
        }

        // Fetch machines with registered and active GPS devices and their latest positions
        $vehicles = Machine::where('plant_id', $activePlantId)
            ->whereHas('gpsDevice', function($q) {
                $q->where('is_active', true);
            })
            ->with(['gpsDevice.latestPosition'])
            ->get()
            ->map(function($m) use ($liveByImei, $liveByReg, $liveByVehicleId) {
                $pos = $m->gpsDevice->latestPosition ?? null;
                $imei = trim((string) $m->gpsDevice->imei);
                $cleanImei = strlen($imei) === 16 && str_starts_with($imei, '0') ? substr($imei, 1) : $imei;
                $normReg = strtoupper(preg_replace('/[^A-Za-z0-9]/', '', (string) $m->registration));

                $live = $liveByImei[$imei]
                    ?? ($liveByImei[$cleanImei]
                    ?? ($liveByReg[$normReg]
                    ?? ($liveByVehicleId[$imei]
                    ?? (Cache::get('gpstrack_device_meta_' . $cleanImei)
                    ?? (Cache::get('gpstrack_reg_meta_' . $normReg) ?? null)))));

                $latitude = isset($live['latitude']) && is_numeric($live['latitude']) ? floatval($live['latitude']) : ($pos ? floatval($pos->latitude) : null);
                $longitude = isset($live['longitude']) && is_numeric($live['longitude']) ? floatval($live['longitude']) : ($pos ? floatval($pos->longitude) : null);
                $speed = isset($live['speed']) && is_numeric($live['speed']) ? floatval($live['speed']) : ($pos ? floatval($pos->speed) : 0);
                $heading = isset($live['bearing']) && is_numeric($live['bearing']) ? floatval($live['bearing']) : ($pos ? floatval($pos->heading) : 0);
                $ignition = isset($live['ignitionStatus']) ? (strtoupper(trim((string)$live['ignitionStatus'])) === 'ON') : ($pos ? (bool)$pos->ignition : false);
                $odometer = isset($live['odoDistance']) && is_numeric($live['odoDistance']) ? floatval($live['odoDistance']) : ($pos ? floatval($pos->odometer) : 0);
                $lastPing = $live['isoDate'] ?? ($m->gpsDevice->last_activity?->toIso8601String() ?? 'Never');

                $vehicleStatus = isset($live['vehicleStatus'])
                    ? strtoupper(trim((string)$live['vehicleStatus']))
                    : ($speed > 0 ? 'RUNNING' : ($ignition ? 'IDLE' : 'PARKED'));

                $address = !empty($live['address']) ? trim((string)$live['address']) : null;
                $fuelLitre = isset($live['fuelLitre']) && is_numeric($live['fuelLitre']) ? floatval($live['fuelLitre']) : null;
                $insideGeoFence = isset($live['insideGeoFence']) ? (strtoupper(trim((string)$live['insideGeoFence'])) === 'Y') : false;

                // If live coordinates exist, update latest position and device last_activity in DB
                if ($live && $latitude !== null && $longitude !== null) {
                    try {
                        $recordedAt = isset($live['isoDate']) ? date('Y-m-d H:i:s', strtotime($live['isoDate'])) : now();
                        GpsLatestPosition::updateOrCreate(
                            ['device_id' => $m->gpsDevice->id],
                            [
                                'machine_id' => $m->id,
                                'latitude' => $latitude,
                                'longitude' => $longitude,
                                'speed' => $speed,
                                'heading' => $heading,
                                'ignition' => $ignition,
                                'odometer' => $odometer,
                                'recorded_at' => $recordedAt,
                            ]
                        );
                        $m->gpsDevice->update(['last_activity' => now()]);

                        if (!GpsPosition::where('device_id', $m->gpsDevice->id)->where('recorded_at', $recordedAt)->exists()) {
                            GpsPosition::create([
                                'device_id' => $m->gpsDevice->id,
                                'machine_id' => $m->id,
                                'latitude' => $latitude,
                                'longitude' => $longitude,
                                'speed' => $speed,
                                'heading' => $heading,
                                'ignition' => $ignition,
                                'odometer' => $odometer,
                                'recorded_at' => $recordedAt,
                            ]);
                        }
                    } catch (\Throwable $ignored) {}
                }

                $isOnline = ($latitude !== null && $longitude !== null) && ($vehicleStatus !== 'OFFLINE')
                    || ($m->gpsDevice->last_activity?->gt(now()->subMinutes(10)) ?? false);

                return [
                    'id' => $m->id,
                    'registration' => $m->registration,
                    'vehicle_model' => $m->vehicle_model,
                    'vehicle_type' => $live['vehicleType'] ?? $m->vehicle_type ?? 'Fleet Vehicle',
                    'imei' => $m->gpsDevice->imei,
                    'device_id' => $live['deviceId'] ?? $m->gpsDevice->imei,
                    'vehicle_id' => $live['vehicleId'] ?? null,
                    'is_online' => $isOnline,
                    'last_ping' => $lastPing,
                    'latitude' => $latitude,
                    'longitude' => $longitude,
                    'speed' => $speed,
                    'heading' => $heading,
                    'ignition' => $ignition,
                    'odometer' => $odometer,
                    'vehicle_status' => $vehicleStatus,
                    'address' => $address,
                    'fuel_litre' => $fuelLitre,
                    'inside_geofence' => $insideGeoFence,
                    'raw_status' => $live['status'] ?? null,
                    'expiry_date' => $live['expiryDate'] ?? null,
                    'onboard_date' => $live['onboardDate'] ?? null,
                    'server_time' => isset($live['serverTime']) && is_numeric($live['serverTime']) ? intval($live['serverTime']) : null,
                ];
            });

        // Also include any live devices from GPSTrack that are not yet matched to existing machines in this plant
        if (is_array($apiData) && array_is_list($apiData)) {
            foreach ($apiData as $row) {
                if (!is_array($row) || !isset($row['latitude']) || !isset($row['longitude'])) continue;

                $id = (string) ($row['deviceId'] ?? '');
                $cleanId = strlen($id) === 16 && str_starts_with($id, '0') ? substr($id, 1) : $id;
                $reg = trim((string) ($row['regNo'] ?? $row['vehicleNo'] ?? ''));
                $normReg = strtoupper(preg_replace('/[^A-Za-z0-9]/', '', $reg));

                $alreadyIncluded = $vehicles->contains(function ($v) use ($id, $cleanId, $normReg) {
                    return (string)$v['imei'] === $id
                        || (string)$v['imei'] === $cleanId
                        || ($normReg !== '' && strtoupper(preg_replace('/[^A-Za-z0-9]/', '', (string)$v['registration'])) === $normReg);
                });

                if (!$alreadyIncluded) {
                    $speed = floatval($row['speed'] ?? 0);
                    $ignition = strtoupper(trim((string)($row['ignitionStatus'] ?? ''))) === 'ON';
                    $vehicleStatus = strtoupper(trim((string)($row['vehicleStatus'] ?? ($speed > 0 ? 'RUNNING' : ($ignition ? 'IDLE' : 'PARKED')))));

                    $vehicles->push([
                        'id' => -abs(crc32($id ?: $reg)),
                        'registration' => $reg ?: ($row['vehicleId'] ?? $cleanId),
                        'vehicle_model' => $row['vehicleType'] ?? 'GPS Tracker',
                        'vehicle_type' => $row['vehicleType'] ?? 'Fleet Vehicle',
                        'imei' => $cleanId,
                        'device_id' => $id,
                        'vehicle_id' => $row['vehicleId'] ?? null,
                        'is_online' => $vehicleStatus !== 'OFFLINE',
                        'last_ping' => $row['isoDate'] ?? now()->toIso8601String(),
                        'latitude' => floatval($row['latitude']),
                        'longitude' => floatval($row['longitude']),
                        'speed' => $speed,
                        'heading' => floatval($row['bearing'] ?? 0),
                        'ignition' => $ignition,
                        'odometer' => floatval($row['odoDistance'] ?? 0),
                        'vehicle_status' => $vehicleStatus,
                        'address' => !empty($row['address']) ? trim((string)$row['address']) : null,
                        'fuel_litre' => isset($row['fuelLitre']) && is_numeric($row['fuelLitre']) ? floatval($row['fuelLitre']) : null,
                        'inside_geofence' => isset($row['insideGeoFence']) ? (strtoupper(trim((string)$row['insideGeoFence'])) === 'Y') : false,
                        'raw_status' => $row['status'] ?? null,
                        'expiry_date' => $row['expiryDate'] ?? null,
                        'onboard_date' => $row['onboardDate'] ?? null,
                        'server_time' => isset($row['serverTime']) && is_numeric($row['serverTime']) ? intval($row['serverTime']) : null,
                    ]);
                }
            }
        }

        // Support direct JSON responses for REST clients or polling without full Inertia payload
        if (request()->wantsJson() && !request()->header('X-Inertia')) {
            return response()->json([
                'success' => true,
                'vehicles' => $vehicles,
                'geofences' => Geofence::where('plant_id', $activePlantId)->where('is_active', true)->get(),
            ]);
        }

        // Load active geofences to display as layers on the map
        $geofences = Geofence::where('plant_id', $activePlantId)
            ->where('is_active', true)
            ->get();

        return Inertia::render('GpsTracking/Live', [
            'vehicles' => $vehicles,
            'geofences' => $geofences,
        ]);
    }

    /**
     * Show the route playback selector page.
     */
    public function playback()
    {
        $this->authorizeModule('menu');

        $activePlantId = session('active_plant_id');

        // Load vehicles that have GPS devices assigned
        $vehicles = Machine::where('plant_id', $activePlantId)
            ->whereHas('gpsDevice')
            ->get(['id', 'registration', 'vehicle_model']);

        return Inertia::render('GpsTracking/Playback', [
            'vehicles' => $vehicles
        ]);
    }

    /**
     * Fetch historical GPS positions for route playback.
     */
    public function playbackData(Request $request)
    {
        $this->authorizeModule('listing');

        $request->validate([
            'machine_id' => 'required|exists:mm_machines,id',
            'start_time' => 'required|date',
            'end_time' => 'required|date|after:start_time',
        ]);

        $machineId = $request->input('machine_id');
        $startTime = date('Y-m-d H:i:s', strtotime($request->input('start_time')));
        $endTime = date('Y-m-d H:i:s', strtotime($request->input('end_time')));

        // Fetch logs sorted chronologically
        $positions = GpsPosition::where('machine_id', $machineId)
            ->whereBetween('recorded_at', [$startTime, $endTime])
            ->orderBy('recorded_at', 'asc')
            ->get()
            ->map(fn($p) => [
                'lat' => floatval($p->latitude),
                'lng' => floatval($p->longitude),
                'speed' => floatval($p->speed),
                'heading' => floatval($p->heading),
                'ignition' => (bool)$p->ignition,
                'odometer' => floatval($p->odometer),
                'time' => $p->recorded_at->toDateTimeString(),
            ]);

        // Calculate statistics
        $totalDistance = 0.00;
        $maxSpeed = 0.00;
        $avgSpeed = 0.00;
        $stops = 0;
        $inStop = false;

        $count = count($positions);
        if ($count > 0) {
            $speeds = [];
            for ($i = 0; $i < $count; $i++) {
                $pos = $positions[$i];
                $speeds[] = $pos['speed'];
                
                // Track max speed
                if ($pos['speed'] > $maxSpeed) {
                    $maxSpeed = $pos['speed'];
                }

                // Track stops (speed < 2 km/h count as stopped)
                if ($pos['speed'] < 2.0 && !$pos['ignition']) {
                    if (!$inStop) {
                        $stops++;
                        $inStop = true;
                    }
                } else {
                    $inStop = false;
                }

                // Calculate cumulative distance using coordinates
                if ($i > 0) {
                    $prev = $positions[$i - 1];
                    $totalDistance += $this->haversineDistance($prev['lat'], $prev['lng'], $pos['lat'], $pos['lng']);
                }
            }

            $avgSpeed = count($speeds) > 0 ? array_sum($speeds) / count($speeds) : 0;
        }

        return response()->json([
            'success' => true,
            'positions' => $positions,
            'stats' => [
                'total_distance_km' => round($totalDistance / 1000, 2), // convert meters to km
                'max_speed_kmh' => round($maxSpeed, 2),
                'avg_speed_kmh' => round($avgSpeed, 2),
                'stops_count' => $stops
            ]
        ]);
    }

    /**
     * Distance in meters between coordinates.
     */
    protected function haversineDistance($lat1, $lng1, $lat2, $lng2)
    {
        $earthRadius = 6371000;
        $dLat = deg2rad($lat2 - $lat1);
        $dLng = deg2rad($lng2 - $lng1);

        $a = sin($dLat / 2) * sin($dLat / 2) +
             cos(deg2rad($lat1)) * cos(deg2rad($lat2)) *
             sin($dLng / 2) * sin($dLng / 2);
        
        $c = 2 * atan2(sqrt($a), sqrt(1 - $a));
        return $earthRadius * $c;
    }
}
