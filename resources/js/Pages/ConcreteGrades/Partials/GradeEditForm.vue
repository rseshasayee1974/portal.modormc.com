<script setup lang="ts">
import { computed, watch } from 'vue';
import { useForm } from '@inertiajs/vue3';
import BaseInput from '@/Components/Base/BaseInput.vue';
import BaseInputNumber from '@/Components/Base/BaseInputNumber.vue';
import BaseSelect from '@/Components/Base/BaseSelect.vue';
import BaseFormActions from '@/Components/Base/BaseFormActions.vue';
import { 
    PlusIcon, 
    ArrowPathIcon,
    TrashIcon,
    VariableIcon,
    TableCellsIcon
} from '@heroicons/vue/24/outline';
import Swal from 'sweetalert2';
import BaseDeleteButton from '@/Components/Base/BaseDeleteButton.vue';
const props = defineProps<{
    grade: any;
    products: any[];
}>();

const emit = defineEmits(['saved', 'cancel']);

const form = useForm({
    name: props.grade.name,
    concrete_code: props.grade.concrete_code || '',
    concrete_ratio: props.grade.concrete_ratio || '',
    cement_ratio: props.grade.cement_ratio,
    sand_ratio: props.grade.sand_ratio,
    aggregate_ratio: props.grade.aggregate_ratio,
    status: Boolean(props.grade.status),
    items: props.grade.items.map((item: any) => ({
        product_id: item.product_id,
        quantity: item.quantity
    }))
});

const productOptions = computed(() => props.products.map(p => ({ label: p.title, value: p.id })));

watch(
    () => [form.cement_ratio, form.sand_ratio, form.aggregate_ratio],
    ([c, s, a]) => {
        const cVal = c || 0;
        const sVal = s || 0;
        const aVal = a || 0;
        if (cVal > 0 || sVal > 0 || aVal > 0) {
            form.concrete_ratio = `${cVal}:${sVal}:${aVal}`;
        }
    },
    { deep: true, immediate: true }
);

const addItem = () => {
    form.items.push({
        product_id: null,
        quantity: 0
    });
};

const removeItem = (index: number) => {
    if (form.items.length > 1) {
        form.items.splice(index, 1);
    }
};

const applyCalculatedProportions = () => {
    const c = form.cement_ratio || 0;
    const s = form.sand_ratio || 0;
    const a = form.aggregate_ratio || 0;
    const total = c + s + a;

    if (total === 0) {
        Swal.fire('Error', 'Ratio parts are zero.', 'error');
        return;
    }

    const factor = 1.54;
    const densities = { cement: 1440, sand: 1600, aggregate: 1550 };
    const items: any[] = [];

    const findProduct = (terms: string[]) => {
        return props.products.find(p => 
            terms.some(t => 
                p.title.toLowerCase().includes(t.toLowerCase()) || 
                (p.category?.name?.toLowerCase().includes(t.toLowerCase()))
            )
        )?.id || null;
    };

    if (c > 0) {
        const volume = (c / total) * factor;
        items.push({ product_id: findProduct(['cement']), quantity: Number((volume * densities.cement).toFixed(2)) });
    }
    if (s > 0) {
        const volume = (s / total) * factor;
        items.push({ product_id: findProduct(['sand', 'fine aggregate']), quantity: Number((volume * densities.sand).toFixed(2)) });
    }
    if (a > 0) {
        const volume = (a / total) * factor;
        items.push({ product_id: findProduct(['aggregate', 'coarse aggregate']), quantity: Number((volume * densities.aggregate).toFixed(2)) });
    }

    if (items.length > 0) {
        form.items = items;
    }
};

const submit = () => {
    form.clearErrors();
    let hasFrontendErrors = false;

    if (!form.name || form.name.trim() === '') {
        form.setError('name', 'Grade Name is required.');
        hasFrontendErrors = true;
    }

    if (form.items.length === 0) {
        form.setError('items', 'Matrix cannot be empty.');
        hasFrontendErrors = true;
    } else {
        form.items.forEach((item: any, index: number) => {
            if (!item.product_id) {
                form.setError(`items.${index}.product_id`, 'Material is required.');
                hasFrontendErrors = true;
            }
            if (item.quantity === null || item.quantity <= 0) {
                form.setError(`items.${index}.quantity`, 'Quantity must be > 0');
                hasFrontendErrors = true;
            }
        });
    }

    if (hasFrontendErrors) {
        return;
    }

    form.put(route('concretegrades.update', props.grade.id), {
        onSuccess: () => {
            emit('saved');
            Swal.fire({ toast: true, position: 'top-end', icon: 'success', title: 'Changes sealed', timer: 2000, showConfirmButton: false });
        },
    });
};
</script>

<template>
    <div class="edit-form-inner">
        <div class="grid grid-cols-12 gap-6">
            <!-- Left Side -->
            <div class="col-span-12 lg:col-span-4 space-y-4">
                <div class="section-title">
                    <VariableIcon class="w-3.5 h-3.5" />
                    <span>Identification</span>
                </div>
                <BaseInput v-model="form.name" label="Grade Name" placeholder="Mix Name" :error="form.errors.name" />
                <BaseInput v-model="form.concrete_code" label="Standard Code" placeholder="Reference" :error="form.errors.concrete_code" />

                <div class="ratio-calc-compact">
                    <div class="flex items-center justify-between mb-3">
                        <span class="text-[9px] font-black uppercase text-slate-400">Ratio Estimator</span>
                        <button type="button" @click="applyCalculatedProportions" class="refresh-link">
                            <ArrowPathIcon class="w-2.5 h-2.5" />
                            <span>Re-calculate</span>
                        </button>
                    </div>
                    <div class="grid grid-cols-3 gap-2">
                        <BaseInputNumber v-model="form.cement_ratio" :minFractionDigits="1" placeholder="C" />
                        <BaseInputNumber v-model="form.sand_ratio" :minFractionDigits="1" placeholder="S" />
                        <BaseInputNumber v-model="form.aggregate_ratio" :minFractionDigits="1" placeholder="A" />
                    </div>
                </div>
            </div>

            <!-- Right Side: Matrix -->
            <div class="col-span-12 lg:col-span-8 space-y-4">
                <div class="flex justify-between items-center">
                    <div class="section-title">
                        <TableCellsIcon class="w-3.5 h-3.5" />
                        <span>Composition Matrix (Kg/m³)</span>
                    </div>
                    <button type="button" @click="addItem" class="add-btn-mini">
                        <PlusIcon class="w-3 h-3" />
                        <span>Add Row</span>
                    </button>
                </div>
                <small v-if="form.errors.items" class="text-rose-500 text-[10px] block mt-1">{{ form.errors.items }}</small>

                <div class="matrix-mini-wrap">
                    <table class="w-full">
                        <thead>
                            <tr>
                                <th>Specification</th>
                                <th class="text-right w-28">Weight</th>
                                <th class="w-8"></th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="(item, index) in form.items" :key="index">
                                <td>
                                    <BaseSelect v-model="item.product_id" :options="productOptions" optionLabel="label" optionValue="value" filter placeholder="Pick Material" fluid />
                                    <small v-if="form.errors[`items.${index}.product_id`]" class="text-rose-500 text-[10px] mt-1 block">{{ form.errors[`items.${index}.product_id`] }}</small>
                                </td>
                                <td>
                                    <BaseInputNumber v-model="item.quantity" :minFractionDigits="1" fluid :inputClass="'text-right font-black'" />
                                    <small v-if="form.errors[`items.${index}.quantity`]" class="text-rose-500 text-[10px] mt-1 block text-right">{{ form.errors[`items.${index}.quantity`] }}</small>
                                </td>
                                <td>
                                    <BaseDeleteButton @click="removeItem(index)" :disabled="form.items.length <= 1" />
                                </td>
                            </tr>
                        </tbody>
                        <tfoot>
                            <tr>
                                <td class="text-right text-[10px] font-bold text-slate-400">Yield Weight:</td>
                                <td class="text-right font-black text-indigo-600">
                                    {{ form.items.reduce((sum, i) => sum + Number(i.quantity || 0), 0).toFixed(0) }} Kg
                                </td>
                                <td></td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            </div>
        </div>

        <div class="flex justify-end gap-3 mt-8 pt-6 border-t border-indigo-100">
            <BaseFormActions
                label="Update Mix Specification"
                cancelLabel="Discard"
                :loading="form.processing"
                @submit="submit"
                @reset="emit('cancel')"
            />
        </div>
    </div>
</template>

<style scoped>
.edit-form-inner { width: 100%; }
.section-title { display: flex; align-items: center; gap: 8px; font-size: 10px; font-weight: 800; text-transform: uppercase; letter-spacing: 0.12em; color: #64748b; margin-bottom: 16px; }

.ratio-calc-compact { background: #fff; border: 1px solid #e2e8f0; border-radius: 12px; padding: 12px; margin-top: 20px; box-shadow: 0 1px 2px rgba(0,0,0,0.02); }
.refresh-link { background: none; border: none; padding: 0; color: #6366f1; font-size: 9px; font-weight: 800; text-transform: uppercase; display: flex; align-items: center; gap: 4px; cursor: pointer; }
.refresh-link:hover { color: #4f46e5; text-decoration: underline; }

.matrix-mini-wrap { border: 1px solid #e2e8f0; border-radius: 12px; overflow: hidden; background: white; }
.matrix-mini-wrap table th { padding: 10px 14px; background: #fafafa; font-size: 8px; font-weight: 800; text-transform: uppercase; color: #94a3b8; border-bottom: 1px solid #f1f5f9; }
.matrix-mini-wrap table td { padding: 4px 10px; border-bottom: 1px solid #fafafa; }

.add-btn-mini { background: #6366f1; color: white; border: none; padding: 4px 10px; border-radius: 6px; font-size: 8px; font-weight: 800; text-transform: uppercase; display: flex; align-items: center; gap: 4px; cursor: pointer; }
.del-btn { color: #cbd5e1; transition: color 0.15s; background: none; border: none; cursor: pointer; }
.del-btn:hover:not(:disabled) { color: #ef4444; }
.del-btn:disabled { opacity: 0.2; }
</style>
