import { usePage } from '@inertiajs/vue3';
import { requestSnapshot } from '@/Utils/cameraSnapshot';

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
        const image = await requestSnapshot(baseUrl, resolveMode(mode));
        return await convertBlobToBase64(image);
    };
    const convertBlobToBase64 = (blob: Blob): Promise<string> => {
        return new Promise((resolve, reject) => {
            if (blob.size < 100) return reject('Image data too small');
            const safeBlob = (blob.type && blob.type.startsWith('image/'))
                ? blob
                : new Blob([blob], { type: 'image/jpeg' });
            const reader = new FileReader();
            reader.onloadend = () => {
                const base64 = reader.result as string;
                if (base64.length > 100) resolve(base64);
                else reject('Invalid base64 length');
            };
            reader.onerror = () => reject('FileReader failed');
            reader.readAsDataURL(safeBlob);
        });
    };

    return {
        captureCameraSnap,
        resolveMode
    };
}
