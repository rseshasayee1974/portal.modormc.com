import { ref, onMounted, onUnmounted } from 'vue';

// ─────────────────────────────────────────────────────────────────────────────
// Pure polling fallback (Echo and Pusher websocket client connection removed)
// ─────────────────────────────────────────────────────────────────────────────

interface UseWebSocketOptions {
    /** Public channel name, e.g. 'batches' or 'gps-tracking' */
    channel: string;
    /** Called with the event payload when a message arrives (Not used in pure polling) */
    onMessage: (data: any) => void;
    /** Called on a regular interval to fetch latest data */
    fallbackPoll: () => void;
    /** Polling interval in ms (default 15 000) */
    pollIntervalMs?: number;
}

export function useWebSocket(options: UseWebSocketOptions) {
    const isConnected = ref(false);
    const isPolling   = ref(true);

    let pollInterval: any = null;

    const startPolling = () => {
        if (pollInterval) return;
        isPolling.value = true;
        options.fallbackPoll(); // run once immediately
        pollInterval = setInterval(options.fallbackPoll, options.pollIntervalMs || 15000);
    };

    const stopPolling = () => {
        if (pollInterval) {
            clearInterval(pollInterval);
            pollInterval = null;
        }
        isPolling.value = false;
    };

    onMounted(() => {
        startPolling();
    });

    onUnmounted(() => {
        stopPolling();
    });

    return { isConnected, isPolling };
}
