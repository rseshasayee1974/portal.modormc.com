import { usePage } from '@inertiajs/vue3';
import { useWeighbridgeSerial } from './useWeighbridgeSerial';
import { useWeighbridgeApi } from './useWeighbridgeApi';
import { useCameraSnapshot } from './useCameraSnapshot';

export function useWeighbridge() {
    const page = usePage();
    const { isScaleConnected, captureWeightSerial, manualConnect } = useWeighbridgeSerial();
    const { captureWeightApi } = useWeighbridgeApi();
    const { captureCameraSnap } = useCameraSnapshot();

    const getNewWeightMode = (): number => {
        const customSettings: any = page?.props?.custom_settings || {};
        const val = customSettings.batching?.new_weight 
            ?? customSettings.batching?.newweight 
            ?? customSettings.new_weight 
            ?? customSettings.newweight 
            ?? localStorage.getItem('new_weight') 
            ?? localStorage.getItem('newweight');
        return Number(val ?? 0);
    };

    const captureWeight = async (callback: (w: number) => void) => {
        const mode = getNewWeightMode();

        if (mode === 1 || mode === 2) {
            await captureWeightApi(callback, mode);
        } else {
            await captureWeightSerial(callback);
        }
    };

    const captureCameraSnapWithMode = async (baseUrl: string): Promise<string> => {
        const mode = getNewWeightMode();
        return await captureCameraSnap(baseUrl, mode);
    };

    return {
        isScaleConnected,
        captureWeight,
        manualConnect,
        captureCameraSnap: captureCameraSnapWithMode,
        getNewWeightMode
    };
}
