<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Str;

class UserPresenceService
{
    public const TTL = 300;
    public const CLOSE_GRACE = 15;

    public function record(int $userId, string $sessionId, string $tabId, int $sequence, bool $closed = false, ?string $loginSessionId = null): bool
    {
        return DB::transaction(function () use ($userId, $sessionId, $tabId, $sequence, $closed, $loginSessionId) {
            if (!DB::table('mm_users')->where('id', $userId)->lockForUpdate()->first(['id'])) return false;
            $sessionHash = hash('sha256', $sessionId);
            if ($this->rejectExpiredLocked($userId, $sessionHash)) return false;
            if (DB::table('mm_user_presence')->where('user_id', $userId)
                ->where('session_hash', $sessionHash)->where('tab_id', 'logout')
                ->where('expires_at', '>', now()->timestamp)->exists()) {
                $this->refreshStatus($userId);
                return false;
            }
            $key = ['user_id' => $userId, 'session_hash' => $sessionHash, 'tab_id' => $tabId];
            $previous = DB::table('mm_user_presence')->where($key)->first();
            // Ignore heartbeat requests that arrive after a newer close notification.
            if (!$previous || $sequence > $previous->sequence) {
                DB::table('mm_user_presence')->updateOrInsert($key, [
                    'sequence' => $sequence,
                    'closed' => $closed,
                    'expires_at' => now()->timestamp + ($closed ? self::CLOSE_GRACE : self::TTL),
                    'encrypted_session_id' => Crypt::encryptString($sessionId),
                ]);
            }
            if (!in_array($tabId, ['login', 'navigation'], true)) {
                DB::table('mm_user_presence')->where('user_id', $userId)
                    ->whereIn('session_hash', [$sessionHash, hash('sha256', $loginSessionId ?? $sessionId)])
                    ->whereIn('tab_id', ['login', 'navigation'])->delete();
            }
            $this->refreshStatus($userId);
            return true;
        });
    }

    public function logout(int $userId, string $sessionId, ?string $loginSessionId = null): void
    {
        DB::transaction(function () use ($userId, $sessionId, $loginSessionId) {
            DB::table('mm_users')->where('id', $userId)->lockForUpdate()->first(['id']);
            $this->revoke(hash('sha256', $sessionId));
            if ($loginSessionId) $this->revoke(hash('sha256', $loginSessionId));
            DB::table('mm_user_presence')->where('user_id', $userId)
                ->whereIn('session_hash', [hash('sha256', $sessionId), hash('sha256', $loginSessionId ?? $sessionId)])
                ->update(['closed' => true, 'sequence' => 2147483647, 'expires_at' => now()->timestamp + self::TTL]);
            DB::table('mm_user_presence')->updateOrInsert([
                'user_id' => $userId, 'session_hash' => hash('sha256', $sessionId), 'tab_id' => 'logout',
            ], ['closed' => true, 'sequence' => 2147483647, 'expires_at' => now()->timestamp + self::TTL]);
            $this->refreshStatus($userId);
        });
    }

    public function sessionExpired(int $userId, string $sessionId): bool
    {
        return DB::transaction(function () use ($userId, $sessionId) {
            DB::table('mm_users')->where('id', $userId)->lockForUpdate()->first(['id']);
            return $this->rejectExpiredLocked($userId, hash('sha256', $sessionId));
        });
    }

    private function rejectExpiredLocked(int $userId, string $sessionHash): bool
    {
        if (Cache::has('revoked_browser_session:'.$sessionHash)) return true;
        $rows = DB::table('mm_user_presence')->where('user_id', $userId)
            ->where('session_hash', $sessionHash)->where('tab_id', '!=', 'logout')->get();
        if ($rows->isEmpty() || $rows->max('expires_at') > now()->timestamp) return false;

        $reference = $rows->first(fn ($row) => !empty($row->encrypted_session_id));
        if ($reference) {
            $sessionId = Crypt::decryptString($reference->encrypted_session_id);
            if (!hash_equals($sessionHash, hash('sha256', $sessionId))) {
                throw new \RuntimeException('Invalid browser session reference.');
            }
            Session::getHandler()->destroy($sessionId);
        }
        $this->revoke($sessionHash);
        // A retained remember-me cookie must not silently reopen the expired login.
        DB::table('mm_users')->where('id', $userId)->update(['remember_token' => Str::random(60)]);
        DB::table('mm_user_presence')->where('user_id', $userId)->where('session_hash', $sessionHash)->delete();
        $this->refreshStatus($userId);
        return true;
    }

    private function revoke(string $sessionHash): void
    {
        Cache::put('revoked_browser_session:'.$sessionHash, true, max(300, (int) config('session.lifetime') * 60));
    }

    public function expire(): int
    {
        $users = DB::table('mm_user_presence')->where('expires_at', '<=', now()->timestamp)
            ->distinct()->pluck('user_id');
        foreach ($users as $userId) {
            DB::transaction(function () use ($userId) {
                DB::table('mm_users')->where('id', $userId)->lockForUpdate()->first(['id']);
                $hashes = DB::table('mm_user_presence')->where('user_id', $userId)->distinct()->pluck('session_hash');
                foreach ($hashes as $hash) {
                    $this->rejectExpiredLocked((int) $userId, $hash);
                }
                DB::table('mm_user_presence')->where('user_id', $userId)
                    ->where('sequence', 2147483647)->where('expires_at', '<=', now()->timestamp)->delete();
                $this->refreshStatus((int) $userId);
            });
        }
        return $users->count();
    }

    private function refreshStatus(int $userId): void
    {
        $online = DB::table('mm_user_presence')->where('user_id', $userId)
            ->where('tab_id', '!=', 'logout')->where('sequence', '<', 2147483647)
            ->where('expires_at', '>', now()->timestamp)->exists();
        DB::table('mm_users')->where('id', $userId)->update(['login_status' => $online]);
    }
}
