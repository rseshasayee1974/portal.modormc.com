<?php

namespace App\Http\Controllers;

use App\Models\Machine;
use App\Models\GpsPosition;
use App\Models\Geofence;
use Illuminate\Http\Request;
use Inertia\Inertia;
use App\Http\Controllers\Concerns\AuthorizesModule;

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

        // Fetch machines with registered and active GPS devices and their latest positions
        $vehicles = Machine::where('plant_id', $activePlantId)
            ->whereHas('gpsDevice', function($q) {
                $q->where('is_active', true);
            })
            ->with(['gpsDevice.latestPosition'])
            ->get()
            ->map(function($m) {
                $pos = $m->gpsDevice->latestPosition ?? null;
                return [
                    'id' => $m->id,
                    'registration' => $m->registration,
                    'vehicle_model' => $m->vehicle_model,
                    'vehicle_type' => $m->vehicle_type,
                    'imei' => $m->gpsDevice->imei,
                    'is_online' => $pos ? $pos->recorded_at->gt(now()->subMinutes(10)) : false,
                    'last_ping' => $pos ? $pos->recorded_at->toDateTimeString() : 'Never',
                    'latitude' => $pos ? floatval($pos->latitude) : null,
                    'longitude' => $pos ? floatval($pos->longitude) : null,
                    'speed' => $pos ? floatval($pos->speed) : 0,
                    'heading' => $pos ? floatval($pos->heading) : 0,
                    'ignition' => $pos ? (bool)$pos->ignition : false,
                    'odometer' => $pos ? floatval($pos->odometer) : 0,
                ];
            });

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
