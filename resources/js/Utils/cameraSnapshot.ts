export function snapshotRequestUrls(value: string, mode = 1): string[] {
    const input = value.trim();
    const url = new URL(input);
    if (!['http:', 'https:'].includes(url.protocol)) {
        throw new Error('Enter a camera HTTP URL or a local snapshot API URL, not a blob preview.');
    }
    const local = ['localhost', '127.0.0.1', '[::1]'].includes(url.hostname);
    const direct = /\/api\/cameras\/[a-f0-9]{32}\/snapshot\/?$/i.test(url.pathname)
        || (/\/api\/camera\/?$/i.test(url.pathname) && url.searchParams.has('img_url'));
    if (local && direct) {
        if (url.username || url.password) throw new Error('Local snapshot URLs must not contain login credentials.');
        return [url.href];
    }
    if (local) throw new Error('Use the local GET snapshot URL ending in /snapshot.');
    const proxies = ['http://127.0.0.1:8089', 'https://127.0.0.1:8074'];
    if (mode === 2) proxies.reverse();
    // Preserve the exact camera URL: adding a timestamp invalidates saved endpoint matching.
    return proxies.map(base => `${base}/api/camera?img_url=${encodeURIComponent(input)}`);
}

export async function requestSnapshot(value: string, mode = 1, fetcher: typeof fetch = fetch): Promise<Blob> {
    let lastError: unknown;
    for (const url of snapshotRequestUrls(value, mode)) {
        const controller = new AbortController();
        const timeout = setTimeout(() => controller.abort(), 60000);
        try {
            const response = await fetcher(url, {
                method: 'GET', cache: 'no-store', credentials: 'omit', signal: controller.signal,
            });
            if (!response.ok) throw new Error(`Camera capture failed (HTTP ${response.status}). Check the local camera configuration.`);
            const image = await response.blob();
            const signature = new Uint8Array(await image.slice(0, 3).arrayBuffer());
            if (image.size > 10 * 1024 * 1024 || signature[0] !== 0xff || signature[1] !== 0xd8 || signature[2] !== 0xff) {
                throw new Error('The camera service did not return a valid JPEG snapshot.');
            }
            return new Blob([image], { type: 'image/jpeg' });
        } catch (error) {
            lastError = error;
        } finally {
            clearTimeout(timeout);
        }
    }
    throw lastError;
}
