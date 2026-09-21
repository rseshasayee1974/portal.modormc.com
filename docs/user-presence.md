# Browser presence

`mm_users.login_status` reflects active browser tabs. Each document sends an authenticated, CSRF-protected heartbeat once per minute. Closing the last tab sends a best-effort `pagehide` beacon and starts a fifteen-second grace period. Reloads register a new tab; back/forward cache restores renew the existing tab. HTML navigation reserves a lease while the page loads. Explicit logout closes the current session's tabs, preserving other active sessions.

If no tab returns during the grace period, cleanup deletes the server-side login session and marks the user offline (unless another browser session is active). Normal closure is processed in approximately fifteen to twenty seconds. Expired session hashes are revoked to reject delayed requests; session references are encrypted at rest. The remember token is rotated so retained cookies cannot silently log the user back in. Other already-authenticated sessions remain usable, but their next remembered login also requires authentication.

If a browser crashes, loses connectivity or cannot send its closing beacon, presence expires after five minutes. The Laravel scheduler runs `users:expire-presence` every five seconds, so crash cleanup normally occurs within five minutes and five seconds of the last heartbeat. The production host must run the application's normal `php artisan schedule:run` cron every minute; Laravel keeps each invocation running for its sub-minute tasks. Request middleware also enforces expiry before allowing a session to resume.

For local development without the scheduler, run `php artisan users:expire-presence --watch`. This worker checks every five seconds. Do not run both a watcher and a scheduler unnecessarily.

Heartbeats stop at the application's twenty-minute inactivity limit; the existing idle logout remains in place. All expiry timestamps are Unix seconds, independent of entity timezone. Deploy the session-reference migration before starting the updated workers, and restart existing workers after updating code.
