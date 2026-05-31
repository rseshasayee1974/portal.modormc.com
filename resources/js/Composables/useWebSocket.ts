import { ref, onMounted, onUnmounted } from 'vue';
import Echo from 'laravel-echo';
import Pusher from 'pusher-js';

// ─────────────────────────────────────────────────────────────────────────────
// Laravel Echo singleton (shared across all composable instances on the page)
// Reverb uses the Pusher protocol, so pusher-js is the required transport.
// ─────────────────────────────────────────────────────────────────────────────
let echoInstance: Echo<'reverb'> | null = null;

function getEcho(): Echo<'reverb'> | null {
    // Read Reverb connection details injected by the Vite plugin or env vars.
    const appKey  = import.meta.env.VITE_REVERB_APP_KEY;
    const host    = import.meta.env.VITE_REVERB_HOST    || window.location.hostname;
    const port    = Number(import.meta.env.VITE_REVERB_PORT    || 8080);
    const scheme  = import.meta.env.VITE_REVERB_SCHEME  || (window.location.protocol === 'https:' ? 'https' : 'http');

    if (!appKey) {
        // VITE_REVERB_APP_KEY not set — stay in polling-only mode silently.
        return null;
    }

    if (!echoInstance) {
        // Attach Pusher to window so Laravel Echo can find it
        (window as any).Pusher = Pusher;

        echoInstance = new Echo({
            broadcaster: 'reverb',
            key:         appKey,
            wsHost:      host,
            wsPort:      port,
            wssPort:     port,
            forceTLS:    scheme === 'https',
            enabledTransports: ['ws', 'wss'],
            // Disable Pusher's own logging in production
            disableStats: true,
        });
    }
    return echoInstance;
}

// ─────────────────────────────────────────────────────────────────────────────
// Composable interface
// ─────────────────────────────────────────────────────────────────────────────
interface UseWebSocketOptions {
    /** Public channel name, e.g. 'batches' or 'gps-tracking' */
    channel: string;
    /** Called with the event payload when a message arrives via Echo */
    onMessage: (data: any) => void;
    /** Called on a regular interval when Echo is unavailable */
    fallbackPoll: () => void;
    /** Polling interval in ms (default 15 000) */
    pollIntervalMs?: number;
}

export function useWebSocket(options: UseWebSocketOptions) {
    const isConnected = ref(false);
    const isPolling   = ref(false);

    let channelSubscription: any   = null;
    let pollInterval:        any   = null;
    let connectionCheckInterval: any = null;

    // ── Polling fallback ──────────────────────────────────────────────────────
    const startPolling = () => {
        if (pollInterval) return;
        isPolling.value = true;
        options.fallbackPoll(); // run once immediately
        pollInterval = setInterval(options.fallbackPoll, options.pollIntervalMs || 15000);
    };

    const stopPolling = () => {
        if (pollInterval) { clearInterval(pollInterval); pollInterval = null; }
        isPolling.value = false;
    };

    // ── Echo / Reverb connection ──────────────────────────────────────────────
    const connect = () => {
        const echo = getEcho();

        if (!echo) {
            // Reverb not configured — fall back to polling only
            startPolling();
            return;
        }

        try {
            channelSubscription = echo
                .channel(options.channel)
                .listen('.' + options.channel, (data: any) => {
                    // Generic catch-all: forward the raw payload
                    options.onMessage(data);
                });

            // Listen for every broadcasted event on this channel by intercepting
            // the underlying Pusher subscription bind_global:
            const pusherChannel = (echo.connector as any)?.pusher?.channel(options.channel);
            if (pusherChannel) {
                pusherChannel.bind_global((eventName: string, data: any) => {
                    if (!eventName.startsWith('pusher:')) {
                        options.onMessage({ event: eventName, ...data });
                    }
                });
            }

            // Monitor connection state via Pusher's socket events
            const pusher = (echo.connector as any)?.pusher;
            if (pusher) {
                pusher.connection.bind('connected', () => {
                    isConnected.value = true;
                    stopPolling();
                });
                pusher.connection.bind('disconnected', () => {
                    isConnected.value = false;
                    startPolling();
                });
                pusher.connection.bind('failed', () => {
                    isConnected.value = false;
                    startPolling();
                });

                // Reflect current state immediately in case Echo connected before binds
                if (pusher.connection.state === 'connected') {
                    isConnected.value = true;
                } else {
                    startPolling(); // start polling until connected
                }
            } else {
                startPolling();
            }

        } catch (e) {
            console.warn(`[Echo] Failed to subscribe to channel "${options.channel}":`, e);
            startPolling();
        }
    };

    const disconnect = () => {
        const echo = getEcho();
        if (echo && channelSubscription) {
            try { echo.leaveChannel(options.channel); } catch {}
        }
        channelSubscription = null;
        stopPolling();
        if (connectionCheckInterval) {
            clearInterval(connectionCheckInterval);
            connectionCheckInterval = null;
        }
        isConnected.value = false;
    };

    onMounted(() => connect());
    onUnmounted(() => disconnect());

    return { isConnected, isPolling };
}
