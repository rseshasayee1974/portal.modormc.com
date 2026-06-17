import { ref, onMounted, onUnmounted } from 'vue';

/**
 * Composable – useBatchTokenPreview
 *
 * Manages the batch/dispatch/delivery token preview dialog:
 *  - Reactive state for dialog visibility, URL, dimensions
 *  - viewToken          : Open dialog for a given token type
 *  - adjustIframeHeight : Auto-resize iframe to its content height
 *  - printTokenIframe   : Trigger the browser print dialog for the iframe
 *  - handleShowTokenEvent : Handle the custom DOM 'show-batch-token' event
 *  - Registers / removes window event listeners on mount / unmount
 *
 * Also manages the actions dropdown menu:
 *  - activeMenuId  : Which row menu is currently open
 *  - toggleMenu    : Toggle a specific row's menu
 *  - closeAllMenus : Close every open menu (bound to global click)
 */
export function useBatchTokenPreview(closeAllMenus: () => void) {

    // ── Token Preview State ───────────────────────────────────────────────────
    const tokenPreviewVisible = ref(false);
    const tokenPreviewUrl     = ref('');
    const iframeHeight        = ref('300px');
    const previewTitle        = ref('Batching Token Preview');
    const previewWidth        = ref('380px');
    const previewIframeWidth  = ref('340px');

    // ── Open Token Dialog ────────────────────────────────────────────────────
    const viewToken = (id: number, type: string = 'batching') => {
        if (type === 'dispatch') {
            previewTitle.value      = 'Dispatch Token Preview';
            previewWidth.value      = '380px';
            previewIframeWidth.value = '340px';
            tokenPreviewUrl.value   = route('batches.dispatch-token', id);
        } else if (type === 'delivery') {
            previewTitle.value      = 'Delivery Token Preview (A4)';
            previewWidth.value      = '850px';
            previewIframeWidth.value = '810px';
            tokenPreviewUrl.value   = route('batches.delivery-token', id);
        } else {
            previewTitle.value      = 'Batching Token Preview';
            previewWidth.value      = '380px';
            previewIframeWidth.value = '340px';
            tokenPreviewUrl.value   = route('batches.token', id);
        }
        iframeHeight.value        = '300px'; // Reset before load
        tokenPreviewVisible.value = true;
    };

    // ── Iframe Auto-Height ───────────────────────────────────────────────────
    const adjustIframeHeight = (event: any) => {
        const iframe = event.target;
        if (iframe && iframe.contentDocument) {
            setTimeout(() => {
                try {
                    const doc    = iframe.contentDocument;
                    const height = Math.max(doc.body.scrollHeight, doc.documentElement.scrollHeight);
                    iframeHeight.value = `${height + 15}px`; // +15px buffer to hide inner scrollbar
                } catch (e) {
                    console.error(e);
                }
            }, 150);
        }
    };

    // ── Print Iframe ─────────────────────────────────────────────────────────
    const printTokenIframe = () => {
        const iframe = document.querySelector('.token-preview-dialog iframe') as HTMLIFrameElement;
        if (iframe && iframe.contentWindow) {
            iframe.contentWindow.focus();
            iframe.contentWindow.print();
        }
    };

    // ── Custom DOM Event Handler ─────────────────────────────────────────────
    const handleShowTokenEvent = (e: any) => {
        if (!e.detail?.url) return;

        tokenPreviewUrl.value = e.detail.url;

        if (e.detail.url.includes('delivery-token')) {
            previewTitle.value      = 'Delivery Token Preview (A4)';
            previewWidth.value      = '850px';
            previewIframeWidth.value = '810px';
        } else if (e.detail.url.includes('dispatch-token')) {
            previewTitle.value      = 'Dispatch Token Preview';
            previewWidth.value      = '380px';
            previewIframeWidth.value = '340px';
        } else {
            previewTitle.value      = 'Batching Token Preview';
            previewWidth.value      = '380px';
            previewIframeWidth.value = '340px';
        }

        iframeHeight.value        = '300px';
        tokenPreviewVisible.value = true;
    };

    // ── Lifecycle ─────────────────────────────────────────────────────────────
    onMounted(() => {
        window.addEventListener('show-batch-token', handleShowTokenEvent);
        window.addEventListener('click', closeAllMenus);
    });

    onUnmounted(() => {
        window.removeEventListener('show-batch-token', handleShowTokenEvent);
        window.removeEventListener('click', closeAllMenus);
    });

    // ── Public API ────────────────────────────────────────────────────────────
    return {
        tokenPreviewVisible,
        tokenPreviewUrl,
        iframeHeight,
        previewTitle,
        previewWidth,
        previewIframeWidth,
        viewToken,
        adjustIframeHeight,
        printTokenIframe,
        handleShowTokenEvent,
    };
}
