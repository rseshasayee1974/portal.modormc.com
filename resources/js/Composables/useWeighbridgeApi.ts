import axios from 'axios';
import Swal from 'sweetalert2';
import { ref } from 'vue';

const failureCount = ref(0);

export function useWeighbridgeApi() {
    const captureWeightApi = async (callback: (w: number) => void) => {
        Swal.fire({ toast: true, position: 'top-end', icon: 'info', title: 'Reading scale API...', showConfirmButton: false, timer: 1000 });
        try {
            const localAxios = axios.create();
            delete localAxios.defaults.headers.common['X-Requested-With'];

            const response = await localAxios.get('http://localhost:8089/api/port', {
                params: { _: new Date().getTime() },
                headers: { 'Accept': '*/*' }
            });

            const w = parseInt(response.data);
            // console.log('API weighbridge weight:', w);
            // console.log('type of weight:', typeof w);
            let _s = 0;
            if (!isNaN(w)) {
                // const s = w / 1000;
                const s = w / 1000000; // 1000 kg 
                _s = s > 99 ? 0 : s;
            }

            failureCount.value = 0; // Reset on success
            callback(_s);
            Swal.fire({ toast: true, position: 'top-end', icon: 'success', title: `Captured: ${_s}`, showConfirmButton: false, timer: 1500 });
        } catch (error: any) {
            console.error('API weighbridge error:', error);
            failureCount.value++;

            if (failureCount.value >= 5) {
                sendFailureAlert(error);
                failureCount.value = 0; // Reset after sending alert
            }

            Swal.fire({ toast: true, position: 'top-end', icon: 'error', title: `Failed to fetch weight (${failureCount.value}/5)`, showConfirmButton: false, timer: 3500 });
        }
    };

    const sendFailureAlert = async (error: any) => {
        try {
            await (window as any).axios.post('/orders/weighbridge/alert', {
                errors: {
                    message: error.message || 'Unknown network error',
                    code: error.code || 'N/A',
                    status: error.response?.status || 'N/A',
                    responseData: error.response?.data || 'No response data from local service',
                    stack: error.stack || 'No stack trace available',
                    browser: navigator.userAgent,
                    url: window.location.href,
                    endpoint: 'http://localhost:8089/api/port',
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
        captureWeightApi
    };
}
