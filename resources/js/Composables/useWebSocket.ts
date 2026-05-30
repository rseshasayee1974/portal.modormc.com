import { ref, onMounted, onUnmounted } from 'vue';

interface UseWebSocketOptions {
    channel: string;
    onMessage: (data: any) => void;
    fallbackPoll: () => void;
    pollIntervalMs?: number;
}

export function useWebSocket(options: UseWebSocketOptions) {
    const isConnected = ref(false);
    const isPolling = ref(false);
    let ws: WebSocket | null = null;
    let reconnectTimeout: any = null;
    let pollInterval: any = null;
    let reconnectAttempts = 0;
    const maxReconnectDelay = 30000;
    let isManuallyClosed = false;

    const startPolling = () => {
        if (pollInterval) return;
        isPolling.value = true;
        console.log(`[WebSocket] Starting fallback polling for ${options.channel}`);
        // Run once immediately
        options.fallbackPoll();
        pollInterval = setInterval(() => {
            options.fallbackPoll();
        }, options.pollIntervalMs || 15000);
    };

    const stopPolling = () => {
        if (pollInterval) {
            clearInterval(pollInterval);
            pollInterval = null;
        }
        isPolling.value = false;
        console.log(`[WebSocket] Stopped fallback polling for ${options.channel}`);
    };

    const connect = () => {
        if (isManuallyClosed) return;

        // Determine protocol and host
        const protocol = window.location.protocol === 'https:' ? 'wss:' : 'ws:';
        const hostname = window.location.hostname;
        const wsPort = 6001; // default port matching our websocket-server.js
        const wsUrl = `${protocol}//${hostname}:${wsPort}`;

        try {
            ws = new WebSocket(wsUrl);

            ws.onopen = () => {
                isConnected.value = true;
                reconnectAttempts = 0;
                stopPolling();
                console.log(`[WebSocket] Connected to channel: ${options.channel}`);
            };

            ws.onmessage = (event) => {
                try {
                    const message = JSON.parse(event.data);
                    if (message.channel === options.channel) {
                        options.onMessage(message.data);
                    }
                } catch (e) {
                    console.error('[WebSocket] Failed to parse message:', e);
                }
            };

            ws.onclose = () => {
                isConnected.value = false;
                if (!isManuallyClosed) {
                    console.warn(`[WebSocket] Connection closed for channel: ${options.channel}. Starting fallback polling.`);
                    startPolling();
                    scheduleReconnect();
                }
            };

            ws.onerror = (error) => {
                console.error('[WebSocket] Socket encountered error for channel:', options.channel, error);
                // Close event is automatically triggered after error, which handles reconnect/polling
            };
        } catch (e) {
            console.error('[WebSocket] Failed to instantiate WebSocket for channel:', options.channel, e);
            startPolling();
            scheduleReconnect();
        }
    };

    const scheduleReconnect = () => {
        if (reconnectTimeout || isManuallyClosed) return;
        
        // Exponential backoff
        const delay = Math.min(1000 * Math.pow(2, reconnectAttempts), maxReconnectDelay);
        reconnectAttempts++;
        
        console.log(`[WebSocket] Scheduling reconnect attempt #${reconnectAttempts} in ${delay}ms for channel ${options.channel}`);
        reconnectTimeout = setTimeout(() => {
            reconnectTimeout = null;
            connect();
        }, delay);
    };

    onMounted(() => {
        isManuallyClosed = false;
        connect();
    });

    onUnmounted(() => {
        isManuallyClosed = true;
        if (ws) {
            ws.close();
            ws = null;
        }
        if (reconnectTimeout) {
            clearTimeout(reconnectTimeout);
            reconnectTimeout = null;
        }
        stopPolling();
    });

    return {
        isConnected,
        isPolling
    };
}
