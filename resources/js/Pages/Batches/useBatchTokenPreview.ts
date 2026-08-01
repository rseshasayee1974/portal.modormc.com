import { ref, onMounted, onUnmounted } from 'vue';
import type { Ref } from 'vue';

type TokenType = 'batching' | 'dispatch' | 'delivery' | 'gate-pass';
type CloseReason = 'print' | 'close' | 'manual';

interface UseBatchTokenPreviewOptions {
    closeAllMenus: () => void;
    onClose?: (batchId: number | null, reason: CloseReason) => void;
}

interface UseBatchTokenPreviewReturn {
    tokenPreviewVisible: Ref<boolean>;
    tokenPreviewUrl: Ref<string>;
    iframeHeight: Ref<string>;
    previewTitle: Ref<string>;
    previewWidth: Ref<string>;
    previewIframeWidth: Ref<string>;
    viewToken: (id: number, type?: TokenType) => void;
    closeTokenPreview: () => void;
    adjustIframeHeight: (event: any) => void;
    printTokenIframe: () => void;
    handleShowTokenEvent: (e: any) => void;
}

/**
 * Composable – useBatchTokenPreview
 */
export function useBatchTokenPreview({
    closeAllMenus,
    onClose,
}: UseBatchTokenPreviewOptions): UseBatchTokenPreviewReturn {

    // ── Token Preview State ───────────────────────────────────────────────────
    const tokenPreviewVisible = ref(false);
    const tokenPreviewUrl = ref('');
    const iframeHeight = ref('300px');
    const previewTitle = ref('Batching Token Preview');
    const previewWidth = ref('380px');
    const previewIframeWidth = ref('340px');
    const currentBatchId = ref<number | null>(null);

    // ── Open Token Dialog ────────────────────────────────────────────────────
    const viewToken = (id: number, type: TokenType = 'batching') => {
        currentBatchId.value = id;
        if (type === 'dispatch') {
            previewTitle.value = 'Dispatch Token Preview';
            previewWidth.value = '380px';
            previewIframeWidth.value = '340px';
            tokenPreviewUrl.value = route('batches.dispatch-token', id);
        } else if (type === 'delivery') {
            previewTitle.value = 'Delivery Token Preview (A4)';
            previewWidth.value = '850px';
            previewIframeWidth.value = '810px';
            tokenPreviewUrl.value = route('batches.delivery-token', id);
        } else if (type === 'gate-pass') {
            previewTitle.value = 'Gate Pass Preview';
            previewWidth.value = '380px';
            previewIframeWidth.value = '340px';
            tokenPreviewUrl.value = route('batches.gate-pass', id);
        } else {
            previewTitle.value = 'Batching Token Preview';
            previewWidth.value = '380px';
            previewIframeWidth.value = '340px';
            tokenPreviewUrl.value = route('batches.token', id);
        }
        iframeHeight.value = '300px';
        tokenPreviewVisible.value = true;
    };

    const closeTokenPreview = () => {
        tokenPreviewVisible.value = false;
        tokenPreviewUrl.value = '';
        iframeHeight.value = '300px';
        setTimeout(() => {
            onClose?.(currentBatchId.value, 'manual');
        }, 350);
    };

    // ── Iframe Auto-Height ───────────────────────────────────────────────────
    const adjustIframeHeight = (event: any) => {
        const iframe = event.target;
        if (iframe && iframe.contentDocument) {
            setTimeout(() => {
                try {
                    const doc = iframe.contentDocument;
                    const height = Math.max(doc.body.scrollHeight, doc.documentElement.scrollHeight);
                    iframeHeight.value = `${height + 15}px`;
                } catch (e) {
                    console.error(e);
                }
            }, 150);
        }
    };

    // ── Print Iframe ─────────────────────────────────────────────────────────
    const printTokenIframe = () => {
        if (!tokenPreviewUrl.value) return;

        const handlePrintComplete = () => {
            onClose?.(currentBatchId.value, 'print');
        };

        const popup = window.open(
            tokenPreviewUrl.value,
            '_blank',
            'width=800,height=600,menubar=no,toolbar=no,location=no,status=no'
        );

        if (!popup) {
            const iframe = document.querySelector('.token-preview-dialog iframe') as HTMLIFrameElement;
            if (iframe && iframe.contentWindow) {
                iframe.contentWindow.focus();
                iframe.contentWindow.print();
            }
            setTimeout(handlePrintComplete, 500);
            return;
        }

        popup.onload = () => {
            setTimeout(() => {
                popup.focus();
                popup.print();
                popup.onafterprint = () => {
                    popup.close();
                    handlePrintComplete();
                };
                setTimeout(() => {
                    try {
                        popup.close();
                        handlePrintComplete();
                    } catch (_) { }
                }, 60000);
            }, 300);
        };
    };

    // ── Custom DOM Event Handler ─────────────────────────────────────────────
    const handleShowTokenEvent = (e: any) => {
        if (!e.detail?.url) return;

        tokenPreviewUrl.value = e.detail.url;

        if (e.detail.url.includes('gate-pass')) {
            previewTitle.value = 'Gate Pass Preview';
            previewWidth.value = '380px';
            previewIframeWidth.value = '340px';
        } else if (e.detail.url.includes('delivery-token')) {
            previewTitle.value = 'Delivery Token Preview (A4)';
            previewWidth.value = '850px';
            previewIframeWidth.value = '810px';
        } else if (e.detail.url.includes('dispatch-token')) {
            previewTitle.value = 'Dispatch Token Preview';
            previewWidth.value = '380px';
            previewIframeWidth.value = '340px';
        } else {
            previewTitle.value = 'Batching Token Preview';
            previewWidth.value = '380px';
            previewIframeWidth.value = '340px';
        }

        iframeHeight.value = '300px';
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
        closeTokenPreview,
        adjustIframeHeight,
        printTokenIframe,
        handleShowTokenEvent,
    };
}