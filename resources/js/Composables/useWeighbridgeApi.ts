import axios from 'axios';
import Swal from 'sweetalert2';

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
            let _s = 0;
            if (!isNaN(w)) {
                const s = w / 1000;
                _s = s > 99 ? 0 : s;
            }
            
            callback(_s);
            Swal.fire({ toast: true, position: 'top-end', icon: 'success', title: `Captured: ${_s}`, showConfirmButton: false, timer: 1500 });
        } catch (error) {
            console.error('API weighbridge error:', error);
            Swal.fire({ toast: true, position: 'top-end', icon: 'error', title: 'Failed to fetch weight from local API', showConfirmButton: false, timer: 3500 });
        }
    };

    return {
        captureWeightApi
    };
}
