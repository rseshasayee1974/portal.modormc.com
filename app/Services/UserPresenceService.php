<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;

class UserPresenceService
{
    public const TTL = 300;

    public function record(int $userId, string $sessionId, string $tabId, int $sequence, bool $closed = false, ?string $loginSessionId = null): void
    {
        DB::transaction(function () use ($userId, $sessionId, $tabId, $sequence, $closed, $loginSessionId) {
            if (!DB::table('mm_users')->where('id', $userId)->lockForUpdate()->first(['id'])) return;
            $sessionHash = hash('sha256', $sessionId);
            if (DB::table('mm_user_presence')->where('user_id', $userId)
                ->where('session_hash', $sessionHash)->where('tab_id', 'logout')
                ->where('expires_at', '>', now()->timestamp)->exists()) {
                $this->refreshStatus($userId);
                return;
            }
            $key = ['user_id' => $userId, 'session_hash' => $sessionHash, 'tab_id' => $tabId];
            $previous = DB::table('mm_user_presence')->where($key)->first();
            // Ignore heartbeat requests that arrive after a newer close notification.
            if (!$previous || $sequence > $previous->sequence) {
                DB::table('mm_user_presence')->updateOrInsert($key, [
                    'sequence' => $sequence,
                    'closed' => $closed,
                    'expires_at' => now()->timestamp + self::TTL,
                ]);
            }
            if ($tabId !== 'login') {
                DB::table('mm_user_presence')->where('user_id', $userId)
                    ->whereIn('session_hash', [$sessionHash, hash('sha256', $loginSessionId ?? $sessionId)])
                    ->where('tab_id', 'login')->delete();
            }
            $this->refreshStatus($userId);
        });
    }

    public function logout(int $userId, string $sessionId, ?string $loginSessionId = null): void
    {
        DB::transaction(function () use ($userId, $sessionId, $loginSessionId) {
            DB::table('mm_users')->where('id', $userId)->lockForUpdate()->first(['id']);
            DB::table('mm_user_presence')->where('user_id', $userId)
                ->whereIn('session_hash', [hash('sha256', $sessionId), hash('sha256', $loginSessionId ?? $sessionId)])
                ->update(['closed' => true, 'sequence' => 2147483647, 'expires_at' => now()->timestamp + self::TTL]);
            DB::table('mm_user_presence')->updateOrInsert([
                'user_id' => $userId, 'session_hash' => hash('sha256', $sessionId), 'tab_id' => 'logout',
            ], ['closed' => true, 'sequence' => 2147483647, 'expires_at' => now()->timestamp + self::TTL]);
            $this->refreshStatus($userId);
        });
    }

    public function expire(): int
    {
        $users = DB::table('mm_user_presence')->where('expires_at', '<=', now()->timestamp)
            ->distinct()->pluck('user_id');
        foreach ($users as $userId) {
            DB::transaction(function () use ($userId) {
                DB::table('mm_users')->where('id', $userId)->lockForUpdate()->first(['id']);
                DB::table('mm_user_presence')->where('user_id', $userId)
                    ->where('expires_at', '<=', now()->timestamp)->delete();
                $this->refreshStatus((int) $userId);
            });
        }
        return $users->count();
    }

    private function refreshStatus(int $userId): void
    {
        $online = DB::table('mm_user_presence')->where('user_id', $userId)
            ->where('closed', false)->where('expires_at', '>', now()->timestamp)->exists();
        DB::table('mm_users')->where('id', $userId)->update(['login_status' => $online]);
    }
}
