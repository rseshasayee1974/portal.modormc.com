<?php

/*
Author: Cipher
Created: 2026-10-17 17:38:27 Asia/Calcutta (UTC+05:30)
*/

namespace App\Http\Controllers\Api\SalesMobile;

use App\Http\Controllers\Controller;
use App\Models\PushNotificationLog;
use App\Notifications\BusinessActivityNotification;
use App\Services\Mobile\MobileAccess as Access;
use App\Services\PushNotificationService;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Http\Request;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Support\Facades\Broadcast;
use Illuminate\Support\Facades\DB;

class NotificationController extends Controller
{
    private function notifications(Request $request): MorphMany
    {
        return $request->user()->notifications()
            ->where('type', BusinessActivityNotification::class)
            ->where('data->plant_id', Access::plantId());
    }

    public function index(Request $request)
    {
        $data = $request->validate(['unread' => 'sometimes|boolean', 'per_page' => 'sometimes|integer|min:1|max:100', 'page' => 'sometimes|integer|min:1']);
        $notifications = $this->notifications($request)->when($data['unread'] ?? false, fn ($query) => $query->whereNull('read_at'))
            ->orderByDesc('created_at')->orderByDesc('id')
            ->paginate($data['per_page'] ?? 20, ['id', 'data', 'read_at', 'created_at']);
        $notifications->through(fn (DatabaseNotification $entry) => [
            'id' => (int) $entry->data['push_log_id'],
            'data' => $entry->data,
            'read_at' => $entry->read_at,
            'created_at' => $entry->created_at,
        ]);

        return response()->json(['success' => true, 'data' => $notifications]);
    }

    public function unreadCount(Request $request)
    {
        return response()->json(['success' => true, 'data' => ['unread_count' => $this->notifications($request)->whereNull('read_at')->count()]]);
    }

    public function connection(Request $request)
    {
        $options = config('broadcasting.connections.reverb.options');

        return response()->json(['success' => true, 'data' => [
            'enabled' => (bool) config('push_notifications.enabled'),
            'can_receive' => (bool) app(PushNotificationService::class)->recipients(Access::plantId(), [(int) $request->user()->id]),
            'configured' => (bool) (config('broadcasting.connections.reverb.key') && config('broadcasting.connections.reverb.secret') && config('broadcasting.connections.reverb.app_id')),
            'broadcaster' => 'reverb', 'key' => config('broadcasting.connections.reverb.key'),
            'host' => config('broadcasting.connections.reverb.public_host') ?: $options['host'],
            'port' => (int) (config('broadcasting.connections.reverb.public_port') ?: $options['port']),
            'scheme' => config('broadcasting.connections.reverb.public_scheme') ?: $options['scheme'],
            'channel' => 'App.Models.User.'.$request->user()->id,
            'auth_endpoint' => route('sales-mobile.notifications.broadcast-auth'),
            'event' => 'Illuminate\\Notifications\\Events\\BroadcastNotificationCreated',
        ]]);
    }

    public function broadcastAuth(Request $request)
    {
        $data = $request->validate(['socket_id' => ['required', 'string', 'regex:/^\d+\.\d+$/'], 'channel_name' => 'required|string|max:200']);
        abort_unless($data['channel_name'] === 'private-App.Models.User.'.$request->user()->id, 403, 'You may only subscribe to your own notification channel.');
        abort_unless(app(PushNotificationService::class)->recipients(Access::plantId(), [(int) $request->user()->id]), 403, 'Required permission: '.PushNotificationService::RECEIVE_PERMISSION);
        abort_unless(config('broadcasting.connections.reverb.key') && config('broadcasting.connections.reverb.secret') && config('broadcasting.connections.reverb.app_id'), 503, 'Reverb is not configured.');

        return Broadcast::connection('reverb')->auth($request);
    }

    private function readEntry(DatabaseNotification $entry, Request $request): void
    {
        $entry->markAsRead();
        $log = PushNotificationLog::where('recipient_user_id', $request->user()->id)->where('plant_id', Access::plantId())
            ->lockForUpdate()->find($entry->data['push_log_id'] ?? null);
        if ($log && $log->status !== 'read') {
            $log->update(['status' => 'read', 'read_at' => $entry->read_at, 'delivered_at' => $log->delivered_at ?? $entry->read_at]);
        }
    }

    public function read(Request $request, string $notification)
    {
        return DB::transaction(function () use ($request, $notification) {
            $entry = $this->notifications($request)->where('data->push_log_id', (int) $notification)->lockForUpdate()->firstOrFail();
            $this->readEntry($entry, $request);

            return response()->json(['success' => true, 'data' => ['id' => (int) $entry->data['push_log_id'], 'read_at' => $entry->read_at]]);
        });
    }

    public function readAll(Request $request)
    {
        $count = DB::transaction(function () use ($request) {
            $count = 0;
            $this->notifications($request)->whereNull('read_at')->reorder()->lockForUpdate()->chunkById(100, function ($entries) use ($request, &$count) {
                foreach ($entries as $entry) {
                    $this->readEntry($entry, $request);
                    $count++;
                }
            });

            return $count;
        });

        return response()->json(['success' => true, 'data' => ['updated_count' => $count]]);
    }

    public function delivered(Request $request, string $notification)
    {
        return DB::transaction(function () use ($request, $notification) {
            $entry = $this->notifications($request)->where('data->push_log_id', (int) $notification)->lockForUpdate()->firstOrFail();
            $log = PushNotificationLog::where('recipient_user_id', $request->user()->id)->where('plant_id', Access::plantId())
                ->lockForUpdate()->find($entry->data['push_log_id'] ?? null);
            if ($log && ! $log->delivered_at) {
                $log->update(['delivered_at' => now(), 'status' => $log->status === 'read' ? 'read' : 'delivered']);
            }

            return response()->json(['success' => true, 'data' => ['id' => (int) $entry->data['push_log_id'], 'delivered_at' => $log?->delivered_at]]);
        });
    }
}
