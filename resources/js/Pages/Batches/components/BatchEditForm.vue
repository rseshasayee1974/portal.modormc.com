<script setup lang="ts">
import { useForm, usePage, router } from '@inertiajs/vue3';
import { computed, watch, ref, onMounted, onUnmounted } from 'vue';
import BaseInput from '@/Components/Base/BaseInput.vue';
import BaseInputNumber from '@/Components/Base/BaseInputNumber.vue';
import BaseSelect from '@/Components/Base/BaseSelect.vue';
import BaseDatePicker from '@/Components/Base/BaseDatePicker.vue';
import BaseFormActions from '@/Components/Base/BaseFormActions.vue';
import Button from 'primevue/button';
import TabView from 'primevue/tabview';
import TabPanel from 'primevue/tabpanel';
import axios from 'axios';
import Swal from 'sweetalert2';
import { CubeIcon, InformationCircleIcon, BeakerIcon, ListBulletIcon, ArrowDownTrayIcon, PlusCircleIcon, ClockIcon } from '@heroicons/vue/24/outline';
import { useWeighbridge } from '@/Composables/useWeighbridge';
import BatchSheetUploader from './BatchSheetUploader.vue';

interface BatchMaterial {
    id?: number;
    product_id: number | null;
    material_name: string;
    target_qty: number;
    deviation_quantity?: number;
    uom_id: number | null;
    runs: number[];
    product?: { title: string };
}

const props = withDefaults(defineProps<{
    batch?: any;
    salesOrders?: any[];
    trucks?: any[];
    transporters?: any[];
    sales_executives?: any[];
    drivers?: any[];
    products?: any[];
    uoms?: any[];
    statuses?: { label: string; value: number }[];
    concretePumpOptions?: any[];
    onSaved?: (payload?: { batchId: number, type: 'batching' | 'dispatch' }) => void;
}>(), {
    batch: () => ({}),
    salesOrders: () => [],
    trucks: () => [],
    transporters: () => [],
    sales_executives: () => [],
    drivers: () => [],
    products: () => [],
    uoms: () => [],
    statuses: () => [],
    concretePumpOptions: () => [],
}); 
const emit = defineEmits<{
    (e: 'saved', payload?: { batchId: number, type: 'batching' | 'dispatch' }): void;
    (e: 'cancel'): void;
}>();

const blankMaterial = (): BatchMaterial => ({
    product_id: null,
    material_name: '',
    target_qty: 0,
    uom_id: null,
    runs: Array(1).fill(0),
});

const form = useForm({
    sales_order_id: props.batch?.sales_order_id ?? null,
    batch_no: props.batch?.batch_no ?? null,
    batch_size: Number(props.batch?.batch_size ?? 1),
    truck_id: props.batch?.dispatches?.[0]?.truck_id ?? null,
    transport_id: props.batch?.dispatches?.[0]?.transport_id ?? null,
    driver_id: props.batch?.dispatches?.[0]?.driver_id ?? null,
    sales_executive_id: props.batch?.dispatches?.[0]?.sales_executive_id ?? null,
    concrete_pump: props.batch?.dispatches?.[0]?.concrete_pump ?? null,
    empty_weight_truck: Number(props.batch?.dispatches?.[0]?.empty_weight_truck ?? 0),
    loaded_weight_truck: Number(props.batch?.dispatches?.[0]?.loaded_weight_truck ?? 0),
        
    loaded_weight_photo: null as string | null,
    net_weight: Number(props.batch?.dispatches?.[0]?.net_weight ?? 0),
    uom_id: props.batch?.uom_id ?? props.uoms?.find((u: any) => String(u.unit_code).toUpperCase() === 'CBM')?.id ?? null,
    status: Number(props.batch?.status ?? 1),
    start_time: props.batch?.start_time ? new Date(props.batch.start_time) : new Date(),
    end_time: props.batch?.end_time ? new Date(props.batch.end_time) : new Date(),
    empty_time: props.batch?.dispatches?.[0]?.empty_time ? new Date(props.batch.dispatches[0].empty_time) : new Date(),
    load_time: props.batch?.dispatches?.[0]?.load_time ? new Date(props.batch.dispatches[0].load_time) : new Date(),
    materials: (() => {
        let initialMaterials = props.batch?.materials || [];
        if (!initialMaterials.length) return [blankMaterial()];

        const grouped: { [key: number]: any } = {};
        initialMaterials.forEach((item: any) => {
            if (!item.product_id) return;
            if (!grouped[item.product_id]) {
                grouped[item.product_id] = {
                    product_id: item.product_id,
                    material_name: item.material_name?.split(' - Run')[0] || item.product?.title || '',
                    target_qty: 0,
                    uom_id: item.uom_id ?? null,
                    rawItems: [],
                };
            }
            grouped[item.product_id].target_qty += Number(item.target_qty || 0);
            grouped[item.product_id].rawItems.push(item);
        });

        return Object.values(grouped).map((group: any) => {
            const size = Number(props.batch?.batch_size ?? 1);
            const matchingSO = props.batch?.sales_order || props.salesOrders.find((wo: any) => wo.id === props.batch?.sales_order_id);
            const cap = Number(matchingSO?.plant?.mixer_capacity || 1.25);
            const runsCount = Math.ceil(size / cap) || 1;
            
            const runsArray = Array(runsCount).fill(0);
            group.rawItems.forEach((item: any) => {
                const match = String(item.material_name).match(/Run (\d+)/i);
                if (match) {
                    const runIdx = parseInt(match[1]) - 1;
                    if (runIdx >= 0 && runIdx < runsCount) {
                        runsArray[runIdx] = Number(item.actual_qty || 0);
                    }
                } else {
                    runsArray[0] = Number(item.actual_qty || 0);
                }
            });

            return {
                product_id: group.product_id,
                material_name: group.material_name,
                target_qty: Number(group.target_qty.toFixed(3)),
                uom_id: group.uom_id,
                runs: runsArray,
            };
        });
    })(),
});

const selectedSalesOrder = computed(() => {
    if (props.batch?.sales_order && props.batch.sales_order.id === form.sales_order_id) {
        return props.batch.sales_order;
    }
    return props.salesOrders.find(wo => wo.id === form.sales_order_id);
});

const mixerCapacity = computed(() => {
    return Number(selectedSalesOrder.value?.plant?.mixer_capacity || props.batch?.sales_order?.plant?.mixer_capacity || 1.25);
});

const numberOfRuns = computed(() => {
    const size = Number(form.batch_size || 1);
    const cap = Number(mixerCapacity.value || 1.25);
    return Math.ceil(size / cap) || 1;
});

const runSizes = computed(() => {
    const size = Number(form.batch_size || 1);
    const cap = Number(mixerCapacity.value || 1.25);
    const runs = [];
    let remaining = size;
    while (remaining > 0) {
        if (remaining >= cap) {
            runs.push(cap);
            remaining -= cap;
        } else {
            runs.push(Number(remaining.toFixed(3)));
            remaining = 0;
        }
    }
    if (runs.length === 0) runs.push(size);
    return runs;
});

console.log('batcjh==',props);

const page = usePage();
const customSettings = page.props.custom_settings as any;

const isMetricTon = computed(() => {
    return customSettings?.batching?.InvoiceInMetricTon == 1;
});

const isLocked = computed(() => {
    return form.status === 3;
});

const salesOrderDetails = computed(() => {
    if (!selectedSalesOrder.value) return [];
    const wo = selectedSalesOrder.value;
    return [
        { label: 'Customer', value: wo.customer?.legal_name || 'N/A' },
        { label: 'Site', value: wo.site?.name || 'N/A' },
        { label: 'Design', value: wo.mix_design?.design_name || 'N/A' },
        { label: 'Grade/Ratio', value: wo.mix_design?.concrete_grade?.name 
            ? `${wo.mix_design.concrete_grade.name}${wo.mix_design.concrete_grade.concrete_ratio ? ` (${wo.mix_design.concrete_grade.concrete_ratio})` : ''}` 
            : (wo.mix_design?.grade || 'N/A') 
        },
        { label: 'Total Qty', value: `${wo.produced_qty} / ${wo.total_qty} m³` },
    ];
});

watch(() => form.batch_size, (newVal) => {
    if (form.sales_order_id && selectedSalesOrder.value?.mix_design?.items) {
        form.materials.forEach((mat) => {
            const originalItem = selectedSalesOrder.value.mix_design.items.find((item: any) => item.product_id === mat.product_id);
            if (originalItem) {
                mat.target_qty = Number(originalItem.cross_quantity || originalItem.actual_quantity || originalItem.quantity || 0) * newVal;
            }
        });
    }
});




watch(() => form.truck_id, async (newVal, oldVal) => {
    if (oldVal !== undefined && oldVal !== null && newVal) {
        try {
            const response = await axios.get(route('batches.truck-empty-weight'), {
                params: { truck_id: newVal }
            });
            form.empty_weight_truck = response.data.empty_weight || 0;
        } catch (err) {
            console.error('Failed to fetch truck empty weight:', err);
        }
    }
});

watch(() => [form.empty_weight_truck, form.loaded_weight_truck], ([emptyWt, loadedWt]) => {
    form.net_weight = (Number(loadedWt) || 0) - (Number(emptyWt) || 0);
});

const addMaterial = () => {
    const mat = blankMaterial();
    mat.runs = Array(numberOfRuns.value).fill(0);
    form.materials.push(mat);
};
const removeMaterial = (index: number) => {
    if (form.materials.length > 1) form.materials.splice(index, 1);
};

// Auto-fill material_name when product_id is selected
watch(
    () => form.materials.map(m => m.product_id),
    (newIds) => {
        newIds.forEach((productId, index) => {
            if (productId && !form.materials[index].material_name) {
                const product = props.products.find((p: any) => p.id === productId);
                if (product) form.materials[index].material_name = product.title;
            }
        });
    }
);

const applyBatchToForm = (newBatch: any) => {
    if (!newBatch) return;

    form.sales_order_id = newBatch.sales_order_id ?? null;
    form.batch_no = newBatch.batch_no ?? null;
    form.batch_size = Number(newBatch.batch_size ?? 1);
    
    const dispatch = newBatch.dispatches?.[0];
    form.truck_id = dispatch?.truck_id ?? null;
    form.transport_id = dispatch?.transport_id ?? null;
    form.driver_id = dispatch?.driver_id ?? null;
    form.sales_executive_id = dispatch?.sales_executive_id ?? null;
    form.concrete_pump = dispatch?.concrete_pump ?? null;
    form.empty_weight_truck = Number(dispatch?.empty_weight_truck ?? 0);
    form.loaded_weight_truck = Number(dispatch?.loaded_weight_truck ?? 0);
    form.net_weight = Number(dispatch?.net_weight ?? 0);
    form.empty_time = dispatch?.empty_time ? new Date(dispatch.empty_time) : new Date();
    form.load_time = dispatch?.load_time ? new Date(dispatch.load_time) : new Date();
    
    form.uom_id = newBatch.uom_id ?? props.uoms?.find((u: any) => String(u.unit_code).toUpperCase() === 'CBM')?.id ?? null;
    form.status = Number(newBatch.status ?? 1);
    form.start_time = newBatch.start_time ? new Date(newBatch.start_time) : new Date();
    form.end_time = newBatch.end_time ? new Date(newBatch.end_time) : new Date();
    
    let initialMaterials = newBatch?.materials || [];
    if (!initialMaterials.length) {
        form.materials = [blankMaterial()];
        return;
    }

    const grouped: { [key: number]: any } = {};
    initialMaterials.forEach((item: any) => {
        if (!item.product_id) return;
        if (!grouped[item.product_id]) {
            grouped[item.product_id] = {
                product_id: item.product_id,
                material_name: item.material_name?.split(' - Run')[0] || item.product?.title || '',
                target_qty: 0,
                uom_id: item.uom_id ?? null,
                rawItems: [],
            };
        }
        grouped[item.product_id].target_qty += Number(item.target_qty || 0);
        grouped[item.product_id].rawItems.push(item);
    });

    const runsCount = numberOfRuns.value;
    form.materials = Object.values(grouped).map((group: any) => {
        const runsArray = Array(runsCount).fill(0);
        group.rawItems.forEach((item: any) => {
            const match = String(item.material_name).match(/Run (\d+)/i);
            if (match) {
                const runIdx = parseInt(match[1]) - 1;
                if (runIdx >= 0 && runIdx < runsCount) {
                    runsArray[runIdx] = Number(item.actual_qty || 0);
                }
            } else {
                runsArray[0] = Number(item.actual_qty || 0);
            }
        });

        return {
            product_id: group.product_id,
            material_name: group.material_name,
            target_qty: Number(group.target_qty.toFixed(3)),
            uom_id: group.uom_id,
            runs: runsArray,
        };
    });
};

// Run on mount with whatever data is available at render time
applyBatchToForm(props.batch);

/**
 * Fire whenever the batch OBJECT REFERENCE changes.
 * This covers:
 *   - Switching from one expanded row to another (id changes)
 *   - The async upgrade: slotProps.data  →  detailedBatches[id]
 *     (same id, different object reference — the materials key appears)
 */ 
watch(
    () => props.batch,
    (newBatch) => applyBatchToForm(newBatch),
    { deep: false }  // shallow — only fires when the object reference itself changes
);

/**
 * Extra safety: if Vue decides to reuse the same object reference but
 * mutates the materials array in-place, catch that too.
 */
watch(
    () => props.batch?.materials,
    (newMaterials, oldMaterials) => {
        // Only re-apply when materials transitions from undefined/null → defined
        if (newMaterials !== undefined && oldMaterials === undefined) {
            applyBatchToForm(props.batch);
        }
    },
    { deep: false }
);

const { isScaleConnected, captureWeight, captureCameraSnap } = useWeighbridge();

const handleWeightCapture = (type: 'empty' | 'loaded') => {
    captureWeight(async (w) => {
        if (type === 'empty') {
            form.empty_weight_truck = w;
            form.empty_time = new Date();
        } else {
            form.loaded_weight_truck = w;
            form.load_time = new Date();
        }
        
        if (customSettings?.batching?.camera == 1 && (customSettings?.batching?.camera_url || customSettings?.batching?.camera_url_1 || customSettings?.batching?.camera_url_2)) {
            let cameraUrl = customSettings.batching.camera_url_1 || customSettings.batching.camera_url;
            if (type === 'loaded' && customSettings.batching.camera_url_2) {
                cameraUrl = customSettings.batching.camera_url_2;
            }

            try {
                const snap = await captureCameraSnap(cameraUrl);
                if (type === 'empty') {
                    // form.empty_weight_photo = snap;
                } else {
                    form.loaded_weight_photo = snap;
                }
            } catch (err) {
                console.error('Camera capture failed:', err);
                Swal.fire({
                    toast: true,
                    position: 'top-end',
                    icon: 'warning',
                    title: `${type} weight captured, but camera failed`,
                    showConfirmButton: false,
                    timer: 1500
                });
            }
        }
    });
};

const isFetchingConsumption = ref(false);
const isConsumptionSynced = ref(false);
const isSaved = ref(false);
const isScanning = ref(false);
const ocrWarning = ref<string | null>(null);
const sheetUrl = ref<string | null>(props.batch?.original_sheet_url ?? props.batch?.sheet_url ?? null);
const showUploadZone = ref(false);

const openUploadZone = () => { showUploadZone.value = true; };
const closeUploadZone = () => { showUploadZone.value = false; };

const handleUploaderCompleted = (result: any) => {
    const data = result.data || result; // Fallback in case just data was passed
    
    if (data?.materials?.length) applyMaterialData(data.materials);
    if (data?.original_url || data?.url) sheetUrl.value = data.original_url || data.url;

    if (result.status === false) {
        ocrWarning.value = result.message || 'Automatic parsing failed. Please enter the data manually.';
        Swal.fire({ toast: true, position: 'top-end', icon: 'warning', title: 'File uploaded, but parsing failed', showConfirmButton: false, timer: 3000 });
    } else {
        ocrWarning.value = null;
        Swal.fire({ toast: true, position: 'top-end', icon: 'success', title: 'Sheet uploaded & analysed successfully', showConfirmButton: false, timer: 1800 });
    }
    setTimeout(() => closeUploadZone(), 1200);
};

onUnmounted(() => {
    showUploadZone.value = false;
    // showReviewZone.value = false;
});

const hasConsumptionData = computed(() => {
    return form.materials.some(mat => (mat.runs || []).some(runVal => Number(runVal) > 0));
});

// Check if the upload-based fetch is enabled in custom settings
const isUploadFetchEnabled = computed(() => !!customSettings?.batching?.sheet_upload);

// Shared function – applies extracted material list to the form
const applyMaterialData = (materials: any[]) => {
    let usedIndices = new Set();
    
    materials.forEach((apiMat: any) => {
        const key = (apiMat.item || apiMat.name || '').toString();
        if (!key) return;

        let matchIndex = form.materials.findIndex((mat, idx) => {
            if (usedIndices.has(idx)) return false;
            const productName = props.products.find((p: any) => p.id === mat.product_id)?.title || '';
            const materialName = mat.material_name || '';
            return (
                productName.toUpperCase().includes(key.toUpperCase()) ||
                materialName.toUpperCase().includes(key.toUpperCase()) ||
                (productName.trim() && key.toUpperCase().includes(productName.toUpperCase().trim()))
            );
        });

        if (matchIndex === -1) {
            matchIndex = form.materials.findIndex(mat => {
                const productName = props.products.find((p: any) => p.id === mat.product_id)?.title || '';
                const materialName = mat.material_name || '';
                return (
                    productName.toUpperCase().includes(key.toUpperCase()) ||
                    materialName.toUpperCase().includes(key.toUpperCase()) ||
                    (productName.trim() && key.toUpperCase().includes(productName.toUpperCase().trim()))
                );
            });
        }

        if (matchIndex !== -1) {
            const val = Number(apiMat.actual ?? apiMat.act ?? 0);
            if (!form.materials[matchIndex].runs) {
                form.materials[matchIndex].runs = Array(numberOfRuns.value).fill(0);
            }
            
            const runMatch = key.match(/Run (\d+)/i);
            if (runMatch) {
                const runIdx = parseInt(runMatch[1]) - 1;
                if (runIdx >= 0 && runIdx < numberOfRuns.value) {
                    form.materials[matchIndex].runs[runIdx] = val;
                }
            } else {
                const size = Number(form.batch_size || 1);
                for (let i = 0; i < numberOfRuns.value; i++) {
                    const runSz = runSizes.value[i] || 0;
                    form.materials[matchIndex].runs[i] = Number((val * (runSz / size)).toFixed(3));
                }
            }
            usedIndices.add(matchIndex);
        }
    });
};

const viewBatchSheet = () => {
    const url = sheetUrl.value ?? props.batch?.original_sheet_url ?? props.batch?.sheet_url;
    if (url) {
        window.open(url, '_blank');
    }
};

// Option 1: Fetch consumption via batches.sync API
const fetchConsumption = async () => {
    isFetchingConsumption.value = true;
    try {
        const response = await axios.post(route('batches.sync', props.batch?.id));
        const data = response.data;

        if (data?.mat?.length) {
            applyMaterialData(data.mat);
            if (data.end) form.end_time = new Date(data.end);
            if (data.start) form.start_time = new Date(data.start);
            isConsumptionSynced.value = true;
            Swal.fire({ toast: true, position: 'top-end', icon: 'success', title: 'Consumption synced', showConfirmButton: false, timer: 1500 });
        } else {
            Swal.fire({ toast: true, position: 'top-end', icon: 'info', title: data?.message || 'No consumption data returned', showConfirmButton: false, timer: 2000 });
        }
    } catch (error: any) {
        const msg = error?.response?.data?.message || 'Failed to sync consumption';
        Swal.fire({ toast: true, position: 'top-end', icon: 'error', title: msg, showConfirmButton: false, timer: 2000 });
        console.error('Sync error:', error);
    } finally {
        isFetchingConsumption.value = false;
    }
};



const copyTargetsToActuals = () => {
    form.materials.forEach((mat) => {
        const size = Number(form.batch_size || 1);
        mat.runs = runSizes.value.map((runSz) => {
            const targetForRun = Number(mat.target_qty ?? 0) * (runSz / size);
            return Number(targetForRun.toFixed(3));
        });
    });
    Swal.fire({
        toast: true,
        position: 'top-end',
        icon: 'success',
        title: 'Recipe targets copied to actual quantities',
        showConfirmButton: false,
        timer: 1500
    });
};

const getDeviation = (item: any) => {
    const totalActual = (item.runs || []).reduce((sum: number, val: any) => sum + Number(val || 0), 0);
    return totalActual - Number(item.target_qty || 0);
};

const normalizeNumber = (val: any) => {
    const n = Number(val);
    return isNaN(n) || val === null || val === '' ? 0 : n;
}

// Run it on key financial fields whenever they change
watch(() => [
    form.empty_weight_truck, 
    form.loaded_weight_truck, 
    form.net_weight,
    form.batch_size,
    ...form.materials.flatMap(m => [m.target_qty, ...(m.runs || [])])
], () => {
    form.empty_weight_truck = normalizeNumber(form.empty_weight_truck);
    form.loaded_weight_truck = normalizeNumber(form.loaded_weight_truck);
    form.net_weight = normalizeNumber(form.net_weight);
    form.batch_size = normalizeNumber(form.batch_size) || 1; // batch_size min 1
    
    form.materials.forEach(m => {
        m.target_qty = normalizeNumber(m.target_qty);
        if (!m.runs) m.runs = Array(numberOfRuns.value).fill(0);
        m.runs.forEach((r, idx) => {
            m.runs[idx] = normalizeNumber(r);
        });
    });
}, { deep: true });

watch(numberOfRuns, (newVal) => {
    form.materials.forEach((mat) => {
        if (!mat.runs) mat.runs = [];
        if (mat.runs.length < newVal) {
            const diff = newVal - mat.runs.length;
            mat.runs.push(...Array(diff).fill(0));
        } else if (mat.runs.length > newVal) {
            mat.runs = mat.runs.slice(0, newVal);
        }
    });
}, { immediate: true });
const submit = () => {
    form.clearErrors();
    let hasErrors = false;
        if (form.empty_weight_truck === null || form.empty_weight_truck === undefined || form.empty_weight_truck <= 0) {
            form.setError('empty_weight_truck', 'Empty Weight is required');
            hasErrors = true;
        }
        if (form.loaded_weight_truck === null || form.loaded_weight_truck === undefined || form.loaded_weight_truck <= 0) {
            form.setError('loaded_weight_truck', 'Full Weight is required');
            hasErrors = true;
        }
        if (!form.empty_time) {
            form.setError('empty_time', 'Empty Time is required');
            hasErrors = true;
        }
        if (!form.load_time) {
            form.setError('load_time', 'Load Time is required');
            hasErrors = true;
        }
    if (hasErrors) {
        Swal.fire({
            icon: 'error',
            title: 'Validation Failed',
            text: 'Please check the required fields.',
            confirmButtonColor: '#0891b2',
        });
        return;
    }

    const formatDateTime = (date: Date | null | string) => {
        if (!date) return null;
        const d = date instanceof Date ? date : new Date(date);
        if (isNaN(d.getTime())) return null;
        const year = d.getFullYear();
        const month = String(d.getMonth() + 1).padStart(2, '0');
        const day = String(d.getDate()).padStart(2, '0');
        const hours = String(d.getHours()).padStart(2, '0');
        const minutes = String(d.getMinutes()).padStart(2, '0');
        const seconds = String(d.getSeconds()).padStart(2, '0');
        return `${year}-${month}-${day} ${hours}:${minutes}:${seconds}`;
    }; 
    form.transform((data) => ({
        ...data,
        start_time: formatDateTime(data.start_time),
        end_time: formatDateTime(data.end_time),
        empty_time: formatDateTime(data.empty_time),
        load_time: formatDateTime(data.load_time),
        materials: data.materials.flatMap((mat: any) => {
            const runsCount = numberOfRuns.value;
            const pTitle = props.products.find((p: any) => p.id === mat.product_id)?.title || 'Material';
            const baseName = mat.material_name || pTitle;
            
            const list = [];
            for (let i = 0; i < runsCount; i++) {
                const actual = Number(mat.runs?.[i] || 0);
                const runSz = runSizes.value[i] || 0;
                const target = Number(mat.target_qty || 0) * (runSz / Number(form.batch_size || 1));
                list.push({
                    product_id: mat.product_id,
                    material_name: `${baseName} - Run ${i + 1}`,
                    target_qty: Number(target.toFixed(3)),
                    actual_qty: actual,
                    deviation_quantity: Number((actual - target).toFixed(3)),
                    uom_id: mat.uom_id
                });
            }
            return list;
        }),
    })).put(route('batches.update', props.batch?.id), {
        preserveState: true,
        preserveScroll: true,
        onSuccess: () => {
            Swal.fire({
                toast: true,
                position: 'top-end',
                icon: 'success',
                title: 'Batch updated',
                timer: 1500,
                showConfirmButton: false,
            });
            isSaved.value = true;
            emit('saved', { batchId: props.batch.id, type: 'batching' });
        },
        onError: (errors) => {
            const errorMessages = Object.values(errors).flat().join('\n');
            Swal.fire({
                icon: 'error',
                title: 'Update Failed',
                text: errorMessages || 'Please check the input fields.',
                confirmButtonColor: '#0891b2',
            });
        }
    });
};


</script>

<template>
    <div class="rounded-2xl border border-slate-100 bg-white shadow-xl shadow-slate-100/50 overflow-hidden">
        <!-- Premium Header Banner -->
        <div class="bg-gradient-to-r from-slate-900 via-cyan-950 to-slate-900 px-6 py-5 text-white">
            <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
                <div class="flex items-center gap-3">
                    <div class="rounded-xl bg-white/10 p-2.5 backdrop-blur-md ring-1 ring-white/20">
                        <CubeIcon class="h-6 w-6 text-cyan-300" />
                    </div>
                    <div>
                        <h2 class="text-base font-bold uppercase tracking-wider text-white">Edit Batch Details</h2>
                        <p class="mt-0.5 text-xs text-slate-300">Modify execution parameters and perform material reconciliation.</p>
                    </div>
                </div>

                <div class="flex items-center gap-3 rounded-xl bg-white/10 px-4 py-2 backdrop-blur-md border border-white/10 self-start sm:self-center">
                    <span class="text-[10px] font-bold uppercase tracking-widest text-slate-300">Batch ID</span>
                    <span class="text-base font-black text-cyan-300">#{{ batch.batch_no }}</span>
                </div>
            </div>
        </div>

        <div class="p-6 space-y-6">
            <!-- Section 1: Sales Order Selection & Reference Card (Unified Full-Width) -->
            <div class="rounded-2xl border border-slate-100 bg-slate-50/50 p-5 shadow-sm">
                <div class="grid grid-cols-1 lg:grid-cols-4 gap-6">
                    <div class="flex flex-col justify-center">
                        <h3 class="text-xs font-bold uppercase tracking-wider text-slate-600 mb-3 flex items-center gap-2">
                            <span class="h-2 w-2 rounded-full bg-cyan-600"></span>
                            Sales Order Context
                        </h3>
                        <BaseSelect 
                            v-model="form.sales_order_id" 
                            optionLabel="full_number" 
                            :options="salesOrders"  
                            optionValue="id" 
                            filter 
                            :disabled="true"
                            label="Sales Order" 
                            :error="form.errors.sales_order_id" 
                        />
                    </div>
                    
                    <div v-if="salesOrderDetails.length" class="lg:col-span-3 border-t lg:border-t-0 lg:border-l border-slate-200/60 lg:pl-6 pt-4 lg:pt-0">
                        <h3 class="mb-3 text-[10px] font-bold uppercase tracking-widest text-cyan-600 flex items-center justify-between">
                            <span>Reference Details</span>
                            <span class="rounded bg-cyan-100 px-2 py-0.5 text-[9px] font-bold text-cyan-700">Live</span>
                        </h3>
                        <div class="grid grid-cols-2 sm:grid-cols-4 gap-4">
                            <div v-for="detail in salesOrderDetails" :key="detail.label" class="flex flex-col">
                                <span class="text-[9px] font-semibold uppercase tracking-wider text-slate-400">{{ detail.label }}</span>
                                <span class="text-xs font-bold text-slate-800 mt-0.5 leading-tight">{{ detail.value }}</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Section 2: Tabbed Workspace -->
            <div class="bg-white rounded-2xl border border-slate-100 shadow-sm overflow-hidden">
                <TabView>
                    <!-- Tab 1: Production Details & Weights -->
                    <TabPanel>
                        <template #header>
                            <div class="flex items-center gap-2 py-1">
                                <ClockIcon class="w-4 h-4 text-cyan-600" />
                                <span class="text-xs font-bold uppercase tracking-wider text-slate-700">1. Details & Weights</span>
                            </div>
                        </template>

                        <div class="p-5 space-y-6">
                            <!-- Parameters Grid -->
                            <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-5 gap-5">
                                <div>
                                    <div class="flex items-end gap-2">
                                        <div class="flex-1">
                                            <BaseInputNumber v-model="form.empty_weight_truck" :disabled="isLocked" label="Empty Weight" required :error="form.errors.empty_weight_truck" />
                                        </div>
                                    </div>
                                </div>
                                <div>
                                    <div class="flex items-end gap-2">
                                        <div class="flex-1">
                                            <BaseInputNumber v-model="form.loaded_weight_truck" :disabled="isLocked || customSettings?.batching?.manual_weight === 0" label="Full Weight" required :error="form.errors.loaded_weight_truck" />
                                        </div>
                                        <button v-if="!isLocked && !customSettings?.batching?.manual_weight && form.net_weight<=0" @click="handleWeightCapture('loaded')" type="button" 
                                            :class="['p-2.5 rounded-xl transition-all duration-200 border shadow-sm flex items-center justify-center', isScaleConnected ? 'bg-emerald-50 text-emerald-600 hover:bg-emerald-100 border-emerald-200' : 'bg-amber-50 text-amber-600 hover:bg-amber-100 border-amber-200']" 
                                            :title="isScaleConnected ? 'Capture Current Weight' : 'Connect & Capture'">
                                            <div class="flex flex-col items-center gap-0.5">
                                                <ArrowDownTrayIcon class="w-4 h-4" />
                                                <span v-if="customSettings?.batching?.camera == 1" class="text-[7px] font-black uppercase tracking-widest">Snap</span>
                                            </div>
                                        </button>
                                    </div>
                                    <div v-if="form.loaded_weight_photo" class="mt-2 relative group rounded-xl overflow-hidden shadow-inner border border-slate-100">
                                        <img :src="form.loaded_weight_photo" class="w-full h-24 object-cover" />
                                        <div class="absolute inset-0 bg-black/40 opacity-0 group-hover:opacity-100 transition-opacity flex items-center justify-center">
                                            <button @click="form.loaded_weight_photo = null" type="button" class="text-white text-xs font-bold bg-red-600 hover:bg-red-700 px-3 py-1.5 rounded-lg transition-all">Remove Snap</button>
                                        </div>
                                    </div>
                                </div>
                                <div>
                                    <BaseInputNumber v-model="form.net_weight" :disabled="isLocked || isMetricTon || customSettings?.batching?.manual_weight === 1" label="Net Weight (kg)" :required="!isMetricTon" :error="form.errors.net_weight" />
                                </div>
                                <div>
                                    <BaseSelect
                                        v-model="form.uom_id"
                                        :options="uoms"
                                        label="UOM"
                                        optionLabel="unit_code"
                                        optionValue="id"
                                        size="small"
                                        :fluid="true"
                                        :error="form.errors.uom_id"
                                        :disabled="isLocked"
                                    />
                                </div>
                                <div>
                                    <BaseSelect v-model="form.truck_id" :options="trucks" optionLabel="registration" optionValue="id" filter label="Truck Assignment" :error="form.errors.truck_id" :disabled="isLocked" />
                                </div>
                                <div>
                                    <BaseSelect v-model="form.transport_id" :options="transporters" optionLabel="legal_name" optionValue="id" filter label="Transporter" showClear :error="form.errors.transport_id" :disabled="isLocked" />
                                </div>
                                <div>
                                    <BaseSelect v-model="form.driver_id" :options="drivers" optionLabel="label" optionValue="id" filter label="Driver" showClear :error="form.errors.driver_id" :disabled="isLocked" />
                                </div>
                                <div>
                                    <BaseSelect v-model="form.sales_executive_id" :options="sales_executives" optionLabel="label" optionValue="id" filter label="Sales Executive" showClear :error="form.errors.sales_executive_id" :disabled="isLocked" />
                                </div>
                                <div>
                                    <BaseInputNumber v-model="form.batch_size" label="Batch Quantity (m³)" :minFractionDigits="2" :disabled="true" :error="form.errors.batch_size" />
                                </div>
                                <div>
                                    <BaseDatePicker label="Empty Time" v-model="form.empty_time" showTime hourFormat="24" fluid :required="isMetricTon" :error="form.errors.empty_time" :disabled="isLocked" />
                                </div>
                                <div>
                                    <BaseDatePicker label="Load Time" v-model="form.load_time" showTime hourFormat="24" fluid :required="isMetricTon" :error="form.errors.load_time" :disabled="isLocked" />
                                </div>
                                <div>
                                    <BaseSelect
                                        v-model="form.concrete_pump"
                                        :options="concretePumpOptions"
                                        label="Concrete Type"
                                        placeholder="Select Concrete Type"
                                        optionLabel="label"
                                        optionValue="value"
                                        :fluid="true"
                                        :error="form.errors.concrete_pump"
                                        :disabled="isLocked"
                                    />
                                </div>
                            </div>

                            <!-- Target Recipe Visualization -->
                            <div v-if="selectedSalesOrder?.mix_design?.items?.length" class="rounded-2xl border border-cyan-100 bg-cyan-50/10 p-5 shadow-sm">
                                <div class="mb-4 flex items-center justify-between">
                                    <div class="flex items-center gap-2">
                                        <BeakerIcon class="w-5 h-5 text-cyan-600" />
                                        <h3 class="text-xs font-bold uppercase tracking-wider text-cyan-900">Calculated Target Yields</h3>
                                    </div>
                                    <span class="rounded-lg bg-cyan-100 px-3 py-1 text-xs font-bold text-cyan-700">
                                        Batch Size: {{ form.batch_size }} m³
                                    </span>
                                </div>
                                <div class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4  gap-3">
                                    <div v-for="item in selectedSalesOrder.mix_design.items" :key="item.id" 
                                        class="flex items-center justify-between rounded-xl bg-white border border-cyan-100/50 p-3 shadow-sm hover:border-cyan-200 transition-all duration-200">
                                        <div class="flex flex-col">
                                            <span class="text-[9px] font-bold text-slate-400 uppercase tracking-wider">Material</span>
                                            <span class="text-xs font-bold text-slate-700 mt-0.5">{{ item.product?.title || 'Material' }}</span>
                                        </div>
                                        <div class="text-right">
                                            <span class="text-[9px] font-bold text-cyan-400 uppercase tracking-wider">Target Qty</span>
                                            <div class="text-xs font-black text-cyan-700 mt-0.5">
                                                {{ (Number(item.cross_quantity || item.quantity || 0) * form.batch_size).toFixed(3) }}
                                                <span class="text-[9px] font-normal text-slate-400 ml-0.5">{{ item.uom?.unit_code || 'KGS' }}</span>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </TabPanel>

                    <!-- Tab 2: Material Reconciliation -->
                    <TabPanel>
                        <template #header>
                            <div class="flex items-center gap-2 py-1">
                                <ListBulletIcon class="w-4 h-4 text-cyan-600" />
                                <span class="text-xs font-bold uppercase tracking-wider text-slate-700">2. Input Reconciliation</span>
                            </div>
                        </template>

                        <div class="p-5 space-y-6">
                            <!-- Action Control Header bar -->
                            <div class="flex flex-wrap items-center justify-between gap-3 border-b border-slate-100 pb-4">
                                <div class="flex items-center gap-2">
                                    <ListBulletIcon class="w-5 h-5 text-slate-400" />
                                    <h3 class="text-xs font-bold uppercase tracking-wider text-slate-700">Raw Batch Reconciliation</h3>
                                </div>
                                <div class="flex flex-wrap items-center gap-2">
                                    <!-- Upload Batch Sheet button -->
                                    <Button
                                        v-if="form.status !== 3 && isUploadFetchEnabled"
                                        :label="sheetUrl || props.batch?.sheet_url ? 'Re-upload Sheet' : 'Upload Batch Sheet'"
                                        icon="pi pi-upload"
                                        size="small"
                                        severity="info"
                                        outlined
                                        class="!text-xs"
                                        :loading="isScanning"
                                        @click="openUploadZone"
                                    />

                                    <!-- Upload Dialog -->
                                    <Transition name="ocr-fade">
                                        <div v-if="showUploadZone"
                                            class="fixed inset-0 z-[9999] flex items-center justify-center p-4"
                                            style="background:rgba(15,23,42,0.55);backdrop-filter:blur(4px)"
                                            @click.self="closeUploadZone"
                                        >
                                            <div class="relative w-full max-w-2xl mx-4 rounded-2xl bg-white shadow-2xl overflow-hidden max-h-[90vh] overflow-y-auto">
                                                <div class="flex items-center justify-between px-5 py-4 border-b border-slate-100 bg-gradient-to-r from-cyan-50 to-blue-50">
                                                    <div class="flex items-center gap-3">
                                                        <div class="w-8 h-8 rounded-lg bg-cyan-100 flex items-center justify-center">
                                                            <svg class="w-4 h-4 text-cyan-600" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" /></svg>
                                                        </div>
                                                        <div>
                                                            <p class="text-xs font-bold text-slate-700">Upload Batch Sheet</p>
                                                            <p class="text-[10px] text-slate-400">AI will extract material weights automatically</p>
                                                        </div>
                                                    </div>
                                                    <button @click="closeUploadZone" class="w-7 h-7 rounded-full bg-slate-100 hover:bg-slate-200 flex items-center justify-center transition-colors">
                                                        <svg class="w-3.5 h-3.5 text-slate-500" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" /></svg>
                                                    </button>
                                                </div>
                                                <div class="p-5">
                                                    <BatchSheetUploader
                                                        :batchId="props.batch?.id"
                                                        @completed="handleUploaderCompleted"
                                                        @close="closeUploadZone"
                                                    />
                                                </div>
                                            </div>
                                        </div>
                                    </Transition>

                                    <!-- View/Download Batch Sheet -->
                                    <Button
                                        v-if="(sheetUrl || props.batch?.sheet_url) && isUploadFetchEnabled"
                                        label="View Sheet"
                                        icon="pi pi-eye"
                                        size="small"
                                        severity="help"
                                        outlined
                                        class="!text-xs"
                                        @click="viewBatchSheet"
                                    />

                                    <!-- Sync Consumption -->
                                    <Button
                                        v-if="form.status !== 3 && !isUploadFetchEnabled"
                                        label="Sync Consumption"
                                        icon="pi pi-sync"
                                        size="small"
                                        severity="secondary"
                                        outlined
                                        class="!text-xs"
                                        :loading="isFetchingConsumption"
                                        @click="fetchConsumption"
                                    />
                                    
                                    <!-- One-Click Target to Actual -->
                                    <Button
                                        v-if="form.status !== 3 && customSettings?.batching?.target_to_actual == 1"
                                        label="Set Actuals = Targets"
                                        icon="pi pi-copy"
                                        size="small"
                                        severity="success"
                                        outlined
                                        class="!text-xs"
                                        @click="copyTargetsToActuals"
                                    />

                                    <Button v-if="form.status !== 3" label="Add Material" icon="pi pi-plus" size="small" outlined severity="info" class="!text-xs" @click="addMaterial" />
                                </div>
                            </div>

                            <!-- OCR Failure Warning -->
                            <div v-if="ocrWarning" class="p-4 rounded-xl bg-orange-50 border border-orange-200 text-orange-800 text-xs shadow-sm flex items-start gap-3 relative overflow-hidden">
                                <div class="absolute inset-y-0 left-0 w-1 bg-orange-400"></div>
                                <div class="mt-0.5 bg-orange-100 rounded-full p-1.5 flex-shrink-0">
                                    <svg class="w-4 h-4 text-orange-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                                    </svg>
                                </div>
                                <div class="flex-1">
                                    <h4 class="font-bold text-orange-800 text-sm mb-1">Manual Entry Required</h4>
                                    <p class="text-orange-700">{{ ocrWarning }}</p>
                                </div>
                                <button @click="ocrWarning = null" class="text-orange-400 hover:text-orange-600 transition-colors p-1 rounded-full hover:bg-orange-100/50">
                                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" /></svg>
                                </button>
                            </div>

                            <div v-if="form.errors.materials" class="p-4 rounded-xl bg-rose-50 border border-rose-100 text-rose-800 text-xs flex flex-col gap-1.5 shadow-sm">
                                <div class="font-bold flex items-center gap-2 text-rose-700">
                                    <svg class="w-4 h-4 text-rose-500 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                                    </svg>
                                    Stock Validation Failed
                                </div>
                                <ul class="list-disc list-inside mt-1 space-y-1 font-semibold text-rose-600">
                                    <li v-for="err in (Array.isArray(form.errors.materials) ? form.errors.materials : [form.errors.materials])" :key="err">
                                        {{ err }}
                                    </li>
                                </ul>
                            </div>

                            <!-- Materials Table Layout -->
                            <div>
                                <div v-if="form.materials.length === 0" class="rounded-2xl border-2 border-dashed border-slate-200 py-12 text-center bg-slate-50/30">
                                    <svg class="mx-auto mb-2 h-10 w-10 text-slate-300" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4" /></svg>
                                    <p class="text-xs font-bold text-slate-400 uppercase tracking-widest">No materials added</p>
                                    <p class="text-[10px] text-slate-300 mt-1">Click "Add Material" above to customize ingredients</p>
                                </div>

                                <div v-else class="overflow-x-auto border border-slate-200 rounded-xl shadow-sm bg-white">
                                    <table class="w-full text-left border-collapse whitespace-nowrap">
                                        <thead>
                                            <tr class="bg-slate-900 text-white">
                                                <th class="px-4 py-3 font-bold uppercase text-xs tracking-wider" :colspan="form.materials.length + 1">
                                                    Materials Breakdown & Tolerances
                                                </th>
                                            </tr>
                                            <tr class="bg-slate-50 text-slate-800 border-b border-slate-200">
                                                <th class="border-r border-slate-200 px-4 py-3 font-bold uppercase w-48 bg-slate-100 text-[10px] text-slate-500 tracking-wider">Product</th>
                                                <th v-for="(item, index) in form.materials" :key="index" class="border-r border-slate-200 px-3 py-2 min-w-[180px]">
                                                    <div class="flex items-center gap-1.5">
                                                        <BaseSelect
                                                            v-model="form.materials[index].product_id"
                                                            :options="products"
                                                            optionLabel="title"
                                                            optionValue="id"
                                                            filter
                                                            size="small"
                                                            :fluid="true"
                                                            :disabled="!!item.id || isLocked"
                                                            :error="form.errors[`materials.${index}.product_id`]"
                                                            placeholder="Select Product"
                                                            class="!text-[10px] w-full"
                                                        />
                                                        <Button
                                                            v-if="!isLocked && form.status !== 3 && !item.id"
                                                            icon="pi pi-trash" 
                                                            text rounded severity="danger"
                                                            class="!h-6 !w-6 !p-0 flex-shrink-0"
                                                            @click="removeMaterial(index)"
                                                        />
                                                    </div>
                                                    <BaseInput
                                                        v-model="form.materials[index].material_name"
                                                        disabled
                                                        size="small" 
                                                        :fluid="true"
                                                        placeholder="Label"
                                                        class="mt-1.5"
                                                    />
                                                </th>
                                            </tr>
                                        </thead>
                                        <tbody class="divide-y divide-slate-100">
                                            <!-- Target Qty Row -->
                                            <tr>
                                                <td class="border-r border-slate-200 px-4 py-2.5 font-bold bg-slate-50 text-slate-600 uppercase text-[10px] tracking-wider">Target Qty</td>
                                                <td v-for="(item, index) in form.materials" :key="index" class="border-r border-slate-200 px-3 py-2">
                                                    <BaseInputNumber
                                                        :modelValue="form.materials[index].target_qty"
                                                        @update:modelValue="form.materials[index].target_qty = Number($event ?? 0)"
                                                        :disabled="!!item.id || isLocked"
                                                        :minFractionDigits="3"
                                                        size="small"
                                                        :fluid="true"
                                                        :error="form.errors[`materials.${index}.target_qty`]"
                                                        class="!text-[11px] !font-bold text-center"
                                                    />
                                                </td>
                                            </tr>
                                            <!-- Recipe Targets Summary -->
                                            <tr class="bg-slate-50/50">
                                                <td :colspan="form.materials.length + 1" class="border-r px-4 py-2 text-right text-slate-500 font-bold uppercase text-[9px] tracking-wider">
                                                    Total Recipe Targets:
                                                    <span class="ml-1 text-slate-800 font-black text-xs">{{ form.materials.reduce((sum, m) => sum + Number(m.target_qty || 0), 0).toFixed(3) }} KGS</span>
                                                </td>
                                            </tr>
                                            <!-- Runs Rows -->
                                            <tr v-for="runIdx in numberOfRuns" :key="runIdx">
                                                <td class="border-r border-slate-200 px-4 py-2.5 font-bold bg-slate-50 text-slate-600 uppercase text-[10px] tracking-wider">
                                                    Run {{ runIdx }} Actual
                                                </td>
                                                <td v-for="(item, index) in form.materials" :key="index" class="border-r border-slate-200 px-3 py-2">
                                                    <BaseInputNumber
                                                        :modelValue="form.materials[index].runs?.[runIdx - 1]"
                                                        @update:modelValue="form.materials[index].runs[runIdx - 1] = Number($event ?? 0)"
                                                        :disabled="isLocked"
                                                        :minFractionDigits="3"
                                                        size="small"
                                                        :fluid="true"
                                                        class="!text-[11px] !font-bold text-center bg-cyan-50/10 focus:bg-cyan-50/30"
                                                    />
                                                </td>
                                            </tr>
                                            <!-- Total Actual Row -->
                                            <tr class="bg-slate-50/30">
                                                <td class="border-r border-slate-200 px-4 py-2.5 font-bold bg-slate-50 text-slate-600 uppercase text-[10px] tracking-wider">Total Actual</td>
                                                <td v-for="(item, index) in form.materials" :key="index" class="border-r border-slate-200 px-3 py-2 font-black text-center text-xs text-slate-800">
                                                    {{ (item.runs || []).reduce((sum, val) => sum + Number(val || 0), 0).toFixed(3) }}
                                                </td>
                                            </tr>
                                            <!-- Deviation Row -->
                                            <tr>
                                                <td class="border-r border-slate-200 px-4 py-2.5 font-bold bg-slate-50 text-slate-600 uppercase text-[10px] tracking-wider">Deviation</td>
                                                <td v-for="(item, index) in form.materials" :key="index" class="border-r border-slate-200 px-3 py-2.5 font-black text-center text-xs"
                                                    :class="getDeviation(item) > 0 ? 'text-rose-600 bg-rose-50/30' : getDeviation(item) < 0 ? 'text-emerald-600 bg-emerald-50/30' : 'text-slate-500 bg-slate-50/10'">
                                                    {{ getDeviation(item) > 0 ? '+' : '' }}{{ getDeviation(item).toFixed(3) }}
                                                </td>
                                            </tr>
                                            <!-- Actual Set Weight Summary -->
                                            <tr class="bg-slate-50/50 border-t border-slate-200">
                                                <td :colspan="form.materials.length + 1" class="border-r px-4 py-2 text-right text-slate-500 font-bold uppercase text-[9px] tracking-wider">
                                                    Total Set Actual Weight:
                                                    <span class="ml-1 text-slate-800 font-black text-xs">
                                                        {{ form.materials.reduce((sum, m) => sum + (m.runs || []).reduce((s, r) => s + Number(r || 0), 0), 0).toFixed(3) }} KGS
                                                    </span>
                                                </td>
                                            </tr>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </TabPanel>
                </TabView>
            </div>
        </div>

        <!-- Update Actions Footer (Sticky) -->
        <div class="border-t border-slate-100 bg-slate-50/50 px-6 py-4 flex justify-end gap-3" v-if="form.status !== 3">
            <Button 
                label="Cancel" 
                severity="secondary" 
                text
                class="!px-6 !py-2.5 !rounded-xl text-xs font-bold uppercase tracking-wider text-slate-600 hover:!bg-slate-100" 
                @click="emit('cancel')" 
            />
            <Button 
                label="Save Changes" 
                icon="pi pi-check" 
                class="!bg-cyan-600 hover:!bg-cyan-700 !border-cyan-600 !px-8 !py-2.5 !rounded-xl text-xs font-bold uppercase tracking-wider shadow-lg shadow-cyan-100" 
                :loading="form.processing"
                @click="submit" 
            />
        </div>
    </div>
</template>

<style scoped>
.ocr-fade-enter-active,
.ocr-fade-leave-active {
    transition: opacity 0.2s ease, transform 0.2s ease;
}
.ocr-fade-enter-from,
.ocr-fade-leave-to {
    opacity: 0;
    transform: scale(0.97);
}
</style>

