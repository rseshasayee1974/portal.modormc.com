<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Broadcast a GPS location update over the 'gps-tracking' channel.
 *
 * Replaces WebSocketService::broadcast('gps-tracking', [...]) in GpsTelemetryController.
 * Public channel — the GPS device API endpoint doesn't have a user session.
 */
class GpsLocationUpdated implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public readonly array $vehicle // the vehicle payload array
    ) {}

    public function broadcastOn(): array
    {
        return [new Channel('gps-tracking')];
    }

    public function broadcastAs(): string
    {
        return 'GpsLocationUpdated';
    }

    public function broadcastWith(): array
    {
        return ['vehicle' => $this->vehicle];
    }
}
