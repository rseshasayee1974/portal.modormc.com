import { ref, watch, onMounted, onUnmounted } from 'vue';
import axios from 'axios';
import Swal from 'sweetalert2';

/**
 * Composable – useOfflineBatchSync
 *
 * Handles:
 *  - Loading the initial batch list (server props + offline localStorage queue)
 *  - Syncing locally-queued offline batches to the server when online
 *  - Listening for the browser `online` event to trigger a sync automatically
 *
 * Usage:
 *   const { localBatches, isSyncing, handleOfflineBatchAdded } =
 *       useOfflineBatchSync(props, fetchBatchesFallback);
 */
export function useOfflineBatchSync(
    props: { batches: any[] },
    fetchBatchesFallback: () => void
) {
    // ── State ──────────────────────────────────────────────────────────────
    const localBatches = ref<any[]>([]);
    const isSyncing = ref(false);

    // ── Helpers ────────────────────────────────────────────────────────────

    /** Merge server props with any locally-persisted offline records. */
    const loadInitialBatches = () => {
        const offline = JSON.parse(localStorage.getItem('offline_batches') || '[]');
        localBatches.value = [...offline, ...props.batches];
    };

    /**
     * Prepend a freshly-created offline batch to the local list so the
     * user sees it immediately without waiting for a server response.
     */
    const handleOfflineBatchAdded = (batch: any) => {
        localBatches.value.unshift(batch);
    };

    /** Upload every queued offline batch to the server in order. */
    const syncOfflineBatches = async () => {
        if (!navigator.onLine || isSyncing.value) return;

        const offline = JSON.parse(localStorage.getItem('offline_batches') || '[]');
        if (offline.length === 0) return;

        isSyncing.value = true;
        let syncedCount = 0;

        // Work on a snapshot so the loop is stable even if storage changes
        const queue = [...offline];

        for (const batch of queue) {
            try {
                // Strip temporary front-end-only fields before posting
                const payload = { ...batch };
                delete payload.id;
                delete payload.is_offline_pending;
                delete payload.truck_registration;
                delete payload.created_at;

                await axios.post(route('batches.store'), payload);
                syncedCount++;

                // Remove the successfully-synced item from localStorage
                const currentQueue = JSON.parse(localStorage.getItem('offline_batches') || '[]');
                const updatedQueue = currentQueue.filter((b: any) => b.id !== batch.id);
                localStorage.setItem('offline_batches', JSON.stringify(updatedQueue));

                // Remove the temporary record from the local reactive list
                localBatches.value = localBatches.value.filter((b: any) => b.id !== batch.id);
            } catch (err) {
                console.error('Failed to sync offline batch:', err);
                // Stop on first error (server may be unreachable)
                break;
            }
        }

        isSyncing.value = false;

        if (syncedCount > 0) {
            Swal.fire({
                toast: true,
                position: 'top-end',
                icon: 'success',
                title: `Synchronized ${syncedCount} offline batch${syncedCount > 1 ? 'es' : ''} successfully.`,
                showConfirmButton: false,
                timer: 2500,
            });

            // Reload from server so IDs and server-side data are fresh
            fetchBatchesFallback();
        }
    };

    // ── Lifecycle ──────────────────────────────────────────────────────────

    onMounted(() => {
        loadInitialBatches();
        syncOfflineBatches();
        window.addEventListener('online', syncOfflineBatches);
    });

    onUnmounted(() => {
        window.removeEventListener('online', syncOfflineBatches);
    });

    // ── Watch: keep local list in sync when server props update ───────────

    watch(
        () => props.batches,
        (newBatches) => {
            const offline = JSON.parse(localStorage.getItem('offline_batches') || '[]');
            localBatches.value = [...offline, ...newBatches];
        },
        { deep: true }
    );

    // ── Public API ─────────────────────────────────────────────────────────
    return {
        localBatches,
        isSyncing,
        handleOfflineBatchAdded,
        syncOfflineBatches,
    };
}
