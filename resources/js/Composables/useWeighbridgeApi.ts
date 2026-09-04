import axios from 'axios';
import Swal from 'sweetalert2';
import { ref } from 'vue';
import { usePage } from '@inertiajs/vue3';

const failureCount = ref(0);

export function useWeighbridgeApi() {
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

    const captureWeightApi = async (callback: (w: number) => void, mode?: number) => {
        const activeMode = resolveMode(mode);

        // Mode 1: http://localhost:8089/api/port
        // Mode 2: https://localhost:8074/api/port
        const endpoints = activeMode === 2
            ? ['https://localhost:8074/api/port', 'http://localhost:8089/api/port']
            : ['http://localhost:8089/api/port', 'https://localhost:8074/api/port'];

        const portLabel = activeMode === 2 ? '8074 (HTTPS)' : '8089 (HTTP)';
        Swal.fire({ toast: true, position: 'top-end', icon: 'info', title: `Reading scale API (Port ${portLabel})...`, showConfirmButton: false, timer: 1000 });

        const localAxios = axios.create({ timeout: 2500 });
        delete localAxios.defaults.headers.common['X-Requested-With'];

        let lastError: any = null;

        for (const endpoint of endpoints) {
            try {
                const response = await localAxios.get(endpoint, {
                    params: { _: new Date().getTime() },
                    headers: { 'Accept': '*/*' }
                });

                const w = parseInt(response.data);
                let _s = 0;
                if (!isNaN(w)) {
                    const s = w / 1000; // 1000 kg 
                    _s = s > 99 ? 0 : s;
                }

                failureCount.value = 0; // Reset on success
                callback(_s);
                Swal.fire({ toast: true, position: 'top-end', icon: 'success', title: `Captured: ${_s}`, showConfirmButton: false, timer: 1500 });
                return;
            } catch (error: any) {
                lastError = error;
                console.warn(`Weighbridge API failed on ${endpoint}:`, error.message || error);
            }
        }

        console.error('All weighbridge API endpoints failed:', lastError);
        failureCount.value++;

        if (failureCount.value >= 5) {
            sendFailureAlert(lastError, endpoints);
            failureCount.value = 0; // Reset after sending alert
        }

        Swal.fire({ toast: true, position: 'top-end', icon: 'error', title: `Failed to fetch weight (${failureCount.value}/5)`, showConfirmButton: false, timer: 3500 });
    };

    const sendFailureAlert = async (error: any, endpoints: string[]) => {
        try {
            await (window as any).axios.post('/orders/weighbridge/alert', {
                errors: {
                    message: error?.message || 'Unknown network error',
                    code: error?.code || 'N/A',
                    status: error?.response?.status || 'N/A',
                    responseData: error?.response?.data || 'No response data from local service',
                    stack: error?.stack || 'No stack trace available',
                    browser: navigator.userAgent,
                    url: window.location.href,
                    endpoint: endpoints.join(' | '),
                    attempts: 5,
                    timestamp: new Date().toISOString(),
                }
            }, { withCredentials: true });
            console.log('Detailed weighbridge failure alert sent to support.');
        } catch (alertError) {
            console.error('Failed to send weighbridge alert to backend:', alertError);
        }
    };

    return {
        captureWeightApi,
        resolveMode
    };
}
