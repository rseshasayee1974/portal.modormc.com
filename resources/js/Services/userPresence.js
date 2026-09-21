// One lease per document, so duplicate tabs never share an identity.
export function installUserPresence(initialPresence, router) {
    let presence = null;
    let tabId = crypto.randomUUID();
    let sequence = 0;
    let inFlight = false;
    let paused = false;
    let lastActivity = Date.now();
    let lastSent = 0;
    const idleLimit = 20 * 60 * 1000; // Match the application's idle logout.

    function active() {
        let activity = lastActivity;
        try { activity = Math.max(activity, Number(localStorage.getItem('portal_last_activity')) || 0); } catch {}
        return Date.now() - activity < idleLimit;
    }

    function body() {
        return new URLSearchParams({ _token: presence.csrf_token, tab_id: tabId, sequence: String(++sequence) });
    }

    function close() {
        if (!presence || paused) return;
        paused = true;
        const data = body();
        if (!navigator.sendBeacon?.(presence.close_url, data)) {
            fetch(presence.close_url, { method: 'POST', body: data, credentials: 'same-origin', keepalive: true }).catch(() => {});
        }
    }

    async function heartbeat() {
        if (!presence || paused || inFlight) return;
        if (!active()) { close(); return; }
        inFlight = true;
        lastSent = Date.now();
        const currentPresence = presence;
        try {
            const response = await fetch(presence.heartbeat_url, {
                method: 'POST', body: body(), credentials: 'same-origin',
                headers: { Accept: 'application/json' }, signal: AbortSignal.timeout(15000),
            });
            if (presence === currentPresence && [401, 403, 419].includes(response.status)) {
                presence = null;
                window.location.assign(currentPresence.login_url || '/login');
            }
        } catch {
            // The server expires the lease if the browser cannot reach it.
        } finally { inFlight = false; }
    }

    function update(next) {
        if (next?.user_id !== presence?.user_id) {
            close();
            tabId = crypto.randomUUID();
            sequence = 0;
            paused = false;
            lastActivity = Date.now();
            presence = next;
            heartbeat();
        } else presence = next; // Refresh CSRF token after navigation/session rotation.
    }

    function activity() {
        lastActivity = Date.now();
        if (paused && presence) paused = false;
        if (Date.now() - lastSent >= 60000) heartbeat();
    }

    const events = ['pointerdown', 'keydown', 'scroll', 'touchstart'];
    events.forEach(event => window.addEventListener(event, activity, { passive: true }));
    window.addEventListener('pagehide', close);
    window.addEventListener('pageshow', () => { paused = false; heartbeat(); });
    window.addEventListener('online', () => heartbeat());
    setInterval(heartbeat, 60000);
    router.on('navigate', event => update(event.detail.page.props.presence));
    update(initialPresence);
}
