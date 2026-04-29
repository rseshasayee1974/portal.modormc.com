import { ref, onMounted } from 'vue';
import Swal from 'sweetalert2';
import axios from 'axios';
import { usePage } from '@inertiajs/vue3';

// Global shared state for the Web Serial API
const isScaleConnected = ref(false);
const scaleWeight = ref(0);
let serialReader: any = null;
let keepReading = true;
let activePort: any = null;
let isConnecting = false;

// The readData function can be defined globally as well
const readData = async (port: any) => {
    while (port.readable && keepReading) {
        const textDecoder = new window.TextDecoderStream();
        const readableStreamClosed = port.readable.pipeTo(textDecoder.writable);
        serialReader = textDecoder.readable.getReader();
        
        try {
            let buffer = '';
            while (true) {
                const { value, done } = await serialReader.read();
                if (done) break;
                
                buffer += value;
                const lines = buffer.split('\n');
                buffer = lines.pop() || ''; 
                
                for (const line of lines) {
                    const cleaned = line.replace(/[^0-9.-]/g, '');
                    if (cleaned) {
                        const w = parseInt(cleaned);
                        if (!isNaN(w)) {
                            const s = w / 1000;
                            scaleWeight.value = Number(s > 99 ? 0 : s);
                        }
                    }
                }
            }
        } catch (error) {
            console.error('Serial read error:', error);
            isScaleConnected.value = false;
            activePort = null;
        } finally {
            if (serialReader) {
                serialReader.releaseLock();
            }
        }
    }
};

const connectToPort = async (port: any) => {
    if (activePort === port && isScaleConnected.value) return; // Already connected to this port
    
    try {
        await port.open({ baudRate: 9600 });
        activePort = port;
        isScaleConnected.value = true;
        keepReading = true;
        Swal.fire({ toast: true, position: 'top-end', icon: 'success', title: 'Scale Connected', showConfirmButton: false, timer: 1500 });
        readData(port);
    } catch (e: any) {
        // If it's an InvalidStateError, it means the port is already open by this tab, which is fine.
        if (e.name === 'InvalidStateError') {
            activePort = port;
            isScaleConnected.value = true;
            keepReading = true;
            return;
        }
        console.error('Failed to open port:', e);
        if (e.name === 'NetworkError') {
            Swal.fire({ toast: true, position: 'top-end', icon: 'error', title: 'COM Port is locked by another tab or app!', showConfirmButton: false, timer: 4000 });
        } else {
            Swal.fire({ toast: true, position: 'top-end', icon: 'error', title: 'Failed to open COM port', showConfirmButton: false, timer: 2500 });
        }
    }
};

export function useWeighbridge() {
    const autoConnect = async () => {
        if (!('serial' in navigator)) return;
        if (isScaleConnected.value || isConnecting) return; // Prevent concurrent attempts
        
        isConnecting = true;
        try {
            // @ts-ignore
            const ports = await navigator.serial.getPorts();
            if (ports.length > 0) {
                await connectToPort(ports[0]);
            }
        } catch (e) {
            console.error('Auto connect failed:', e);
        } finally {
            isConnecting = false;
        }
    };

    const manualConnect = async () => {
        if (!('serial' in navigator)) {
            Swal.fire({ toast: true, position: 'top-end', icon: 'error', title: 'Browser does not support Web Serial API', showConfirmButton: false, timer: 3000 });
            return;
        }
        if (isScaleConnected.value) return;

        isConnecting = true;
        try {
            // @ts-ignore
            const port = await navigator.serial.requestPort();
            await connectToPort(port);
        } catch (e) {
            console.error('Manual connect failed:', e);
        } finally {
            isConnecting = false;
        }
    };

    const captureWeight = async (callback: (w: number) => void) => {
        // v2: Check custom setting to use local server API
        const page = usePage();
        const customSettings: any = page.props.custom_settings || {};
        console.log(customSettings);
        const newWeight = customSettings.batching?.newweight || localStorage.getItem('newweight');
        
        if (newWeight == 1 || newWeight == '1') {
            Swal.fire({ toast: true, position: 'top-end', icon: 'info', title: 'Reading scale API...', showConfirmButton: false, timer: 1000 });
            try {
                // Create a clean Axios instance that doesn't inherit Inertia's global headers
                const localAxios = axios.create();
                delete localAxios.defaults.headers.common['X-Requested-With'];
                
                // Mimic the exact $.ajax({ type: 'GET', cache: false }) behavior
                const response = await localAxios.get('http://localhost:8089/api/port', {
                    params: { _: new Date().getTime() },
                    headers: {
                        'Accept': '*/*'
                    }
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
                Swal.fire({ toast: true, position: 'top-end', icon: 'error', title: 'Failed to fetch weight from API', showConfirmButton: false, timer: 3500 });
            }
            return;
        }

        // v1: Web Serial API logic
        if (!isScaleConnected.value) {
            await manualConnect();
            if (!isScaleConnected.value) return; // connection failed or cancelled
            
            // Wait briefly for the stream to populate the first value
            Swal.fire({ toast: true, position: 'top-end', icon: 'info', title: 'Reading scale...', showConfirmButton: false, timer: 1000 });
            setTimeout(() => {
                callback(scaleWeight.value);
                Swal.fire({ toast: true, position: 'top-end', icon: 'success', title: `Captured: ${scaleWeight.value}`, showConfirmButton: false, timer: 1500 });
            }, 1000);
        } else {
            callback(scaleWeight.value);
            Swal.fire({ toast: true, position: 'top-end', icon: 'success', title: `Captured: ${scaleWeight.value}`, showConfirmButton: false, timer: 1500 });
        }
    };

    onMounted(() => {
        autoConnect();
    });

    return {
        isScaleConnected,
        captureWeight,
        manualConnect
    };
}
