export function useCameraSnapshot() {
    const captureCameraSnap = async (baseUrl: string): Promise<string> => {
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
        
        const proxyUrl = `http://127.0.0.1:8089/api/camera?img_url=${encodeURIComponent(timestampedUrl)}`;

        console.log('Final Snapshot URL Logic:', { original: baseUrl, processed: processedUrl, proxy: proxyUrl });

        try {
            // Use a simple fetch, no extra headers
            const response = await fetch(proxyUrl);

            if (!response.ok) {
                // If it fails with timestamp, try ONE MORE TIME without it (some cameras are picky)
                console.warn('Capture failed with timestamp, retrying without cache-buster...');
                const retryUrl = `http://127.0.0.1:8089/api/camera?img_url=${encodeURIComponent(processedUrl)}`;
                const retryResponse = await fetch(retryUrl);
                
                if (!retryResponse.ok) {
                    throw new Error(`Proxy error: ${retryResponse.status}`);
                }
                
                const blob = await retryResponse.blob();
                return convertBlobToBase64(blob);
            }

            const blob = await response.blob();
            return convertBlobToBase64(blob);
        } catch (error) {
            console.error('Snapshot failed:', error);
            throw error;
        }
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
        captureCameraSnap
    };
}
