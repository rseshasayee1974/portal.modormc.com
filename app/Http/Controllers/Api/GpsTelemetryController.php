<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\GpsDevice;
use App\Models\GpsPosition;
use App\Models\GpsLatestPosition;
use App\Models\Geofence;
use App\Models\GeofenceLog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class GpsTelemetryController extends Controller
{
    /**
     * Ingest telemetry data sent from GPS tracking hardware or external gateway.
     */
    public function ingest(Request $request)
    {
        $validated = $request->validate([
            'imei' => 'required|string|max:50',
            'latitude' => 'required|numeric|between:-90,90',
            'longitude' => 'required|numeric|between:-180,180',
            'speed' => 'nullable|numeric|min:0',
            'heading' => 'nullable|numeric|between:0,360',
            'altitude' => 'nullable|numeric',
            'ignition' => 'nullable|boolean',
            'odometer' => 'nullable|numeric|min:0',
            'recorded_at' => 'nullable|date',
        ]);

        $imei = $validated['imei'];
        
        // Find the device
        $device = GpsDevice::query()->where('imei', $imei)->where('is_active', true)->first();
        if (!$device) {
            return response()->json([
                'success' => false,
                'message' => 'Device not found or inactive.'
            ], 404);
        }

        $machineId = $device->machine_id;
        $plantId = $device->plant_id;
        $recordedAt = $validated['recorded_at'] ? date('Y-m-d H:i:s', strtotime($validated['recorded_at'])) : now()->toDateTimeString();
        $latitude = $validated['latitude'];
        $longitude = $validated['longitude'];
        $speed = $validated['speed'] ?? 0.00;
        $heading = $validated['heading'] ?? 0.00;
        $altitude = $validated['altitude'] ?? null;
        $ignition = (bool)($validated['ignition'] ?? false);
        $odometer = $validated['odometer'] ?? null;

        DB::beginTransaction();
        try {
            // Update device heartbeat/activity
            $device->update(['last_activity' => now()]);

            // Save historical coordinates log
            GpsPosition::create([
                'device_id' => $device->id,
                'machine_id' => $machineId ?? 0, // Fallback if device is unassigned
                'latitude' => $latitude,
                'longitude' => $longitude,
                'speed' => $speed,
                'heading' => $heading,
                'altitude' => $altitude,
                'ignition' => $ignition,
                'odometer' => $odometer,
                'recorded_at' => $recordedAt,
            ]);

            // Fetch previous latest position to check geofence enter/exit transitions
            $previousLatest = GpsLatestPosition::query()->where('device_id', $device->id)->first();

            // Create or update the latest cache
            GpsLatestPosition::updateOrCreate(
                ['device_id' => $device->id],
                [
                    'machine_id' => $machineId ?? 0,
                    'latitude' => $latitude,
                    'longitude' => $longitude,
                    'speed' => $speed,
                    'heading' => $heading,
                    'altitude' => $altitude,
                    'ignition' => $ignition,
                    'odometer' => $odometer,
                    'recorded_at' => $recordedAt,
                ]
            );

            // Handle Geofences if machine is assigned
            if ($machineId) {
                $this->processGeofenceChecks($machineId, $plantId, $latitude, $longitude, $recordedAt, $previousLatest);
            }

            DB::commit();
            return response()->json([
                'success' => true,
                'message' => 'Telemetry ingested successfully.'
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('GPS Telemetry Ingest Error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to ingest telemetry: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Compute geofence entries & exits based on the incoming coordinate.
     */
    protected function processGeofenceChecks($machineId, $plantId, $lat, $lng, $recordedAt, $previousLatest)
    {
        // Get all active geofences for this plant
        $geofences = Geofence::query()->where('plant_id', $plantId)->where('is_active', true)->get();

        foreach ($geofences as $geofence) {
            $isCurrentlyInside = $this->isPointInGeofence($lat, $lng, $geofence);
            
            $wasPreviouslyInside = false;
            if ($previousLatest) {
                $wasPreviouslyInside = $this->isPointInGeofence(
                    $previousLatest->latitude,
                    $previousLatest->longitude,
                    $geofence
                );
            }

            // Transition: Enter
            if ($isCurrentlyInside && !$wasPreviouslyInside) {
                GeofenceLog::create([
                    'machine_id' => $machineId,
                    'geofence_id' => $geofence->id,
                    'event_type' => 'enter',
                    'latitude' => $lat,
                    'longitude' => $lng,
                    'recorded_at' => $recordedAt
                ]);
            }
            // Transition: Exit
            elseif (!$isCurrentlyInside && $wasPreviouslyInside) {
                GeofenceLog::create([
                    'machine_id' => $machineId,
                    'geofence_id' => $geofence->id,
                    'event_type' => 'exit',
                    'latitude' => $lat,
                    'longitude' => $lng,
                    'recorded_at' => $recordedAt
                ]);
            }
        }
    }

    /**
     * Determine if a latitude/longitude point is inside a geofence.
     */
    protected function isPointInGeofence($lat, $lng, Geofence $geofence)
    {
        $coords = $geofence->coordinates;
        if (!$coords) return false;

        if ($geofence->shape === 'circle') {
            // Circle check (Haversine formula)
            $center = $coords['center'] ?? null;
            $radius = $coords['radius'] ?? 0; // in meters
            if (!$center || !isset($center['lat']) || !isset($center['lng'])) return false;

            $distance = $this->calculateDistance($lat, $lng, $center['lat'], $center['lng']);
            return $distance <= $radius;
        } 
        elseif ($geofence->shape === 'polygon') {
            // Polygon check (Ray casting algorithm)
            $points = $coords['points'] ?? $coords; // support direct array of coords or points key
            if (!is_array($points) || count($points) < 3) return false;

            return $this->isPointInPolygon($lat, $lng, $points);
        }

        return false;
    }

    /**
     * Calculate distance between two lat/lng coordinates in meters using Haversine formula.
     */
    protected function calculateDistance($lat1, $lng1, $lat2, $lng2)
    {
        $earthRadius = 6371000; // meters
        $dLat = deg2rad($lat2 - $lat1);
        $dLng = deg2rad($lng2 - $lng1);

        $a = sin($dLat / 2) * sin($dLat / 2) +
             cos(deg2rad($lat1)) * cos(deg2rad($lat2)) *
             sin($dLng / 2) * sin($dLng / 2);
        
        $c = 2 * atan2(sqrt($a), sqrt(1 - $a));
        return $earthRadius * $c;
    }

    /**
     * Ray-casting algorithm to detect if a point is inside a polygon.
     */
    protected function isPointInPolygon($lat, $lng, array $polygon)
    {
        $inside = false;
        $numVertices = count($polygon);
        
        // Clean points to ensure consistency
        $vertices = [];
        foreach ($polygon as $p) {
            $vertices[] = [
                'lat' => floatval($p['lat'] ?? $p[0] ?? 0),
                'lng' => floatval($p['lng'] ?? $p[1] ?? 0)
            ];
        }

        for ($i = 0, $j = $numVertices - 1; $i < $numVertices; $j = $i++) {
            $vi = $vertices[$i];
            $vj = $vertices[$j];

            $intersect = (($vi['lat'] > $lat) !== ($vj['lat'] > $lat))
                && ($lng < ($vj['lng'] - $vi['lng']) * ($lat - $vi['lat']) / ($vj['lat'] - $vi['lat'] + 0.000000001) + $vi['lng']);
            
            if ($intersect) {
                $inside = !$inside;
            }
        }

        return $inside;
    }
}