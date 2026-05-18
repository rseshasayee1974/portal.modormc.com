import { ref } from 'vue';
import Swal from 'sweetalert2';
import axios from 'axios';

const isScaleConnected = ref(false);
const scaleWeight = ref(0);
const failureCount = ref(0);
let serialReader: any = null;
let keepReading = true;
let activePort: any = null;
let isConnecting = false;

const sendFailureAlert = async (errorMessage: string) => {
    try {
        await (window as any).axios.post('/orders/weighbridge/alert', {
            errors: {
                message: errorMessage,
                type: 'Serial Connection',
                browser: navigator.userAgent,
                url: window.location.href,
                attempts: failureCount.value,
                timestamp: new Date().toISOString(),
            }
        }, { withCredentials: true });
        console.log('Detailed serial failure alert sent to support.');
    } catch (alertError) {
        console.error('Failed to send serial failure alert:', alertError);
    }
};

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
                            failureCount.value = 0; // Reset on data received
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
    if (activePort === port && isScaleConnected.value) return;
    
    try {
        await port.open({ baudRate: 9600 });
        activePort = port;
        isScaleConnected.value = true;
        keepReading = true;
        failureCount.value = 0; // Reset on successful connect
        Swal.fire({ toast: true, position: 'top-end', icon: 'success', title: 'Scale Connected (Serial)', showConfirmButton: false, timer: 1500 });
        readData(port);
    } catch (e: any) {
        if (e.name === 'InvalidStateError') {
            activePort = port;
            isScaleConnected.value = true;
            keepReading = true;
            return;
        }
        console.error('Failed to open port:', e);
        failureCount.value++;
        if (failureCount.value >= 5) {
            sendFailureAlert(`Serial Port Error: ${e.message}`);
            failureCount.value = 0;
        }
        Swal.fire({ toast: true, position: 'top-end', icon: 'error', title: `Serial Port Error (${failureCount.value}/5)`, showConfirmButton: false, timer: 2500 });
    }
};

export function useWeighbridgeSerial() {
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
        } catch (e: any) {
            console.error('Manual connect failed:', e);
            failureCount.value++;
            if (failureCount.value >= 5) {
                sendFailureAlert('User/System failed to select/connect to serial port after 5 tries');
                failureCount.value = 0;
            }
        } finally {
            isConnecting = false;
        }
    };

    const captureWeightSerial = async (callback: (w: number) => void) => {
        if (!isScaleConnected.value) {
            await manualConnect();
            if (!isScaleConnected.value) return;
            
            Swal.fire({ toast: true, position: 'top-end', icon: 'info', title: 'Reading serial scale...', showConfirmButton: false, timer: 1000 });
            setTimeout(() => {
                callback(scaleWeight.value);
                Swal.fire({ toast: true, position: 'top-end', icon: 'success', title: `Captured: ${scaleWeight.value}`, showConfirmButton: false, timer: 1500 });
            }, 1000);
        } else {
            callback(scaleWeight.value);
            Swal.fire({ toast: true, position: 'top-end', icon: 'success', title: `Captured: ${scaleWeight.value}`, showConfirmButton: false, timer: 1500 });
        }
    };

    return {
        isScaleConnected,
        captureWeightSerial,
        manualConnect
    };
}
