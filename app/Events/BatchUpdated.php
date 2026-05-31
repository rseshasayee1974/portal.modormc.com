<?php

namespace App\Events;

use App\Models\Batch;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Broadcast a batch create / update / delete event over the 'batches' channel.
 *
 * Implements ShouldBroadcastNow (sync, no queue) so the event reaches
 * connected clients immediately — the same behaviour as the old
 * WebSocketService::broadcast('batches', [...]) HTTP-POST approach.
 */
class BatchUpdated implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public readonly string $event,  // e.g. 'BatchCreated', 'BatchDeleted'
        public readonly array  $payload // the batch array or ['id' => $id]
    ) {}

    /**
     * The channel this event is broadcast on.
     * 'batches' is a public channel — no auth needed, matches the old WS channel name.
     */
    public function broadcastOn(): array
    {
        return [new Channel('batches')];
    }

    /**
     * Event name sent to the client  (window.Echo.channel('batches').listen(...))
     */
    public function broadcastAs(): string
    {
        return $this->event;  // 'BatchCreated' | 'BatchUpdated' | 'BatchDeleted'
    }

    /**
     * Payload sent to the frontend — mirrors the old WebSocketService data structure.
     */
    public function broadcastWith(): array
    {
        return $this->payload;
    }
}
