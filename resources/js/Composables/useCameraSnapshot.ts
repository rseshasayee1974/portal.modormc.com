import { usePage } from '@inertiajs/vue3';

export function useCameraSnapshot() {
    let page: any = null;
    try {
        page = usePage();
    } catch (e) {
        // Outside Inertia context
    }

    const resolveMode = (passedMode?: number): number => {
        if (passedMode === 1 || passedMode === 2) return passedMode;
        const customSettings: any = page?.props?.custom_settings || {};
        const val = customSettings.batching?.new_weight 
            ?? customSettings.batching?.newweight 
            ?? customSettings.new_weight 
            ?? customSettings.newweight 
            ?? localStorage.getItem('new_weight') 
            ?? localStorage.getItem('newweight');
        return Number(val || 1);
    };

    const captureCameraSnap = async (baseUrl: string, mode?: number): Promise<string> => {
        if (!baseUrl) {
            throw new Error('No base URL provided for camera');
        }

        // Special handling for passwords with @ - the proxy often fails on double @
        // We ensure the password's @ is encoded as %40 but the host @ remains
        let processedUrl = baseUrl;
        if (baseUrl.includes('://') && baseUrl.includes('@')) {
            const parts = baseUrl.split('://');
            const protocol = parts[0];
            const rest = parts[1];
            const lastAtIndex = rest.lastIndexOf('@');
            if (lastAtIndex > 0) {
                const credentials = rest.substring(0, lastAtIndex);
                const hostPath = rest.substring(lastAtIndex);
                // Encode @ in credentials only
                processedUrl = `${protocol}://${credentials.replace(/@/g, '%40')}${hostPath}`;
            }
        }

        const separator = processedUrl.includes('?') ? '&' : '?';
        const timestampedUrl = `${processedUrl}${separator}_=${Date.now()}`;

        const activeMode = resolveMode(mode);

        // Mode 1: http://127.0.0.1:8089/api/camera?img_url=
        // Mode 2: https://127.0.0.1:8074/api/camera?img_url=
        const proxyBases = activeMode === 2
            ? [
                'https://127.0.0.1:8074/api/camera?img_url=',
                'http://127.0.0.1:8089/api/camera?img_url='
              ]
            : [
                'http://127.0.0.1:8089/api/camera?img_url=',
                'https://127.0.0.1:8074/api/camera?img_url='
              ];

        let lastError: any = null;

        for (const proxyBase of proxyBases) {
            const proxyUrl = `${proxyBase}${encodeURIComponent(timestampedUrl)}`;
            console.log(`Attempting Camera Snapshot via proxy (Mode ${activeMode}):`, { original: baseUrl, processed: processedUrl, proxyUrl });

            try {
                let response = await fetch(proxyUrl);

                if (!response.ok) {
                    // If it fails with timestamp, try ONE MORE TIME without it (some cameras are picky)
                    console.warn(`Capture failed with timestamp on ${proxyBase}, retrying without cache-buster...`);
                    const retryUrl = `${proxyBase}${encodeURIComponent(processedUrl)}`;
                    response = await fetch(retryUrl);
                }

                if (!response.ok) {
                    throw new Error(`Proxy error: ${response.status} on ${proxyBase}`);
                }

                const blob = await response.blob();
                return await convertBlobToBase64(blob);
            } catch (error) {
                lastError = error;
                console.warn(`Snapshot failed on ${proxyBase}:`, error);
            }
        }

        console.error('All camera snapshot proxy endpoints failed:', lastError);
        throw lastError;
    };

    const convertBlobToBase64 = (blob: Blob): Promise<string> => {
        return new Promise((resolve, reject) => {
            if (blob.size < 100) return reject('Image data too small');
            const reader = new FileReader();
            reader.onloadend = () => {
                const base64 = reader.result as string;
                if (base64.length > 100) resolve(base64);
                else reject('Invalid base64 length');
            };
            reader.onerror = () => reject('FileReader failed');
            reader.readAsDataURL(blob);
        });
    };

    return {
        captureCameraSnap,
        resolveMode
    };
}
