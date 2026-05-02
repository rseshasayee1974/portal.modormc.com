import { ref } from 'vue';
import Swal from 'sweetalert2';

const isScaleConnected = ref(false);
const scaleWeight = ref(0);
let serialReader: any = null;
let keepReading = true;
let activePort: any = null;
let isConnecting = false;

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
    if (activePort === port && isScaleConnected.value) return;
    
    try {
        await port.open({ baudRate: 9600 });
        activePort = port;
        isScaleConnected.value = true;
        keepReading = true;
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
        Swal.fire({ toast: true, position: 'top-end', icon: 'error', title: 'Serial Port Error', showConfirmButton: false, timer: 2500 });
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
        } catch (e) {
            console.error('Manual connect failed:', e);
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
