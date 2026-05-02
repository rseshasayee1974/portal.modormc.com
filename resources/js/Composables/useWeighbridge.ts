import { usePage } from '@inertiajs/vue3';
import { useWeighbridgeSerial } from './useWeighbridgeSerial';
import { useWeighbridgeApi } from './useWeighbridgeApi';
import { useCameraSnapshot } from './useCameraSnapshot';

export function useWeighbridge() {
    const page = usePage();
    const { isScaleConnected, captureWeightSerial, manualConnect } = useWeighbridgeSerial();
    const { captureWeightApi } = useWeighbridgeApi();
    const { captureCameraSnap } = useCameraSnapshot();

    const captureWeight = async (callback: (w: number) => void) => {
        const customSettings: any = page.props.custom_settings || {};
        const newWeight = customSettings.batching?.newweight || localStorage.getItem('newweight');

        if (newWeight == 1 || newWeight == '1') {
            await captureWeightApi(callback);
        } else {
            await captureWeightSerial(callback);
        }
    };

    return {
        isScaleConnected,
        captureWeight,
        manualConnect,
        captureCameraSnap
    };
}
