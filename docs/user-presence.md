# Browser presence

`mm_users.login_status` reflects active browser tabs. Each document sends an authenticated, CSRF-protected heartbeat once per minute. Closing the last tab sends a best-effort `pagehide` beacon and marks the user offline. Reloads register a new tab; back/forward cache restores renew the existing tab. Explicit logout closes the current session's tabs, preserving other active sessions.

If a browser crashes, loses connectivity or cannot send its closing beacon, presence expires after five minutes. The Laravel scheduler runs `users:expire-presence` every minute, so cleanup normally occurs within five to six minutes. The production host must run the application's normal `php artisan schedule:run` cron every minute.

For local development without the scheduler, run `php artisan users:expire-presence --watch`. This worker only cleans presence records. Do not run both a watcher and a scheduler unnecessarily.

Presence is not an authentication control: closing a tab marks it offline without destroying other tabs' sessions. Heartbeats stop at the application's twenty-minute inactivity limit; the existing idle logout remains in place. All expiry timestamps are Unix seconds, independent of entity timezone.
