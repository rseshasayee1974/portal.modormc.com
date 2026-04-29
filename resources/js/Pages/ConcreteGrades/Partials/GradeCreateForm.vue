<script setup lang="ts">
import { ref, computed, watch } from 'vue';
import { useForm } from '@inertiajs/vue3';
import BaseInput from '@/Components/Base/BaseInput.vue';
import BaseInputNumber from '@/Components/Base/BaseInputNumber.vue';
import BaseSelect from '@/Components/Base/BaseSelect.vue';
import BaseFormActions from '@/Components/Base/BaseFormActions.vue';
import BaseDeleteButton from '@/Components/Base/BaseDeleteButton.vue';
import { 
    PlusIcon, 
    ArrowPathIcon,
    BeakerIcon,
    VariableIcon,
    TableCellsIcon
} from '@heroicons/vue/24/outline';
import Swal from 'sweetalert2';

const props = defineProps<{
    products: any[];
}>();

const isOpen = ref(true);

const form = useForm({
    name: '',
    concrete_code: '',
    concrete_ratio: '',
    cement_ratio: null as number | null,
    sand_ratio: null as number | null,
    aggregate_ratio: null as number | null,
    status: true,
    items: [
        { product_id: null as number | null, quantity: 0 }
    ] as any[]
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

const toggle = () => {
    isOpen.value = !isOpen.value;
    if (!isOpen.value) {
        form.reset();
        form.clearErrors();
        isOpen.value = true;
    }
};

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
        Swal.fire('Error', 'Please enter valid ratio parts first.', 'error');
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
        Swal.fire({ toast: true, position: 'top-end', icon: 'success', title: 'Calculated weights applied', timer: 1500, showConfirmButton: true });
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
        form.setError('items', 'At least one material must be added to the matrix.');
        hasFrontendErrors = true;
    } else {
        form.items.forEach((item, index) => {
            if (!item.product_id) {
                form.setError(`items.${index}.product_id`, 'Please select a material.');
                hasFrontendErrors = true;
            }
            // Validate quantity
            if (item.quantity === null || item.quantity <= 0) {
                form.setError(`items.${index}.quantity`, 'Quantity must be > 0');
                hasFrontendErrors = true;
            }
        });
    }

    if (hasFrontendErrors) {
        return;
    }

    form.post(route('concretegrades.store'), {
        onSuccess: () => {
            toggle();
            form.reset();
            Swal.fire({ toast: true, position: 'top-end', icon: 'success', title: 'Grade established successfully', timer: 1000, showConfirmButton: true });
        },
    });
};
</script>

<template>
    <div class="create-panel" :class="{ 'create-panel--open': isOpen }">
        <button class="create-panel__header" @click="toggle" type="button">
            <div class="flex items-center gap-3">
                <div class="create-panel__icon">
                    <BeakerIcon class="w-5 h-5 text-indigo-500" />
                </div>
                <div class="text-left">
                    <p class="text-xs font-bold text-gray-700 uppercase tracking-widest">Establish New Grade</p>
                    <p class="text-[11px] text-gray-400 font-medium mt-0.5">Define mix proportions, ratios and ingredient matrix</p>
                </div>
            </div>
            <div class="create-panel__toggle" :class="{ 'create-panel__toggle--open': isOpen }">
                <PlusIcon class="w-4 h-4" />
            </div>
        </button>

        <Transition name="panel-slide">
            <div v-if="isOpen" class="create-panel__body">
                <div class="grid grid-cols-12 gap-6">
                    <!-- General Config -->
                    <div class="col-span-12 lg:col-span-4 space-y-4">
                        <div class="section-title">
                            <VariableIcon class="w-3.5 h-3.5" />
                            <span>Base Specification</span>
                        </div>
                        <BaseInput v-model="form.name" label="Grade Name (Mix Identifier)" placeholder="e.g. M25 Supermix" :error="form.errors.name" />
                        <BaseInput v-model="form.concrete_code" label="Reference Code" placeholder="GRD-V4-25" :error="form.errors.concrete_code" />
                        
                        <div class="ratio-input-card mt-6">
                            <label class="text-[10px] font-bold uppercase tracking-widest text-slate-400 mb-3 block">Ratio Estimator (Cement : Sand : Agg)</label>
                            <div class="grid grid-cols-3 gap-2">
                                <BaseInputNumber v-model="form.cement_ratio" :minFractionDigits="1" placeholder="C" />
                                <BaseInputNumber v-model="form.sand_ratio" :minFractionDigits="1" placeholder="S" />
                                <BaseInputNumber v-model="form.aggregate_ratio" :minFractionDigits="1" placeholder="A" />
                            </div>
                            <div class="flex items-center justify-between mt-4 pt-4 border-t border-slate-100">
                                <span class="text-[10px] font-bold text-indigo-400 uppercase tracking-widest">Calculated Ratio:</span>
                                <span class="text-sm font-black text-indigo-900 tracking-tighter">{{ form.concrete_ratio || '0:0:0' }}</span>
                            </div>
                            <button type="button" @click="applyCalculatedProportions" class="calc-btn mt-4">
                                <ArrowPathIcon class="w-3 h-3" />
                                <span>Calculate & Map Matrix</span>
                            </button>
                        </div>
                    </div>

                    <!-- Ingredient Matrix -->
                    <div class="col-span-12 lg:col-span-8 space-y-4">
                        <div class="flex justify-between items-center">
                            <div class="section-title">
                                <TableCellsIcon class="w-3.5 h-3.5" />
                                <span>Ingredient Matrix (Target Kg/m³)</span>
                            </div>
                            <button type="button" @click="addItem" class="add-item-btn">
                                <PlusIcon class="w-3 h-3" />
                                <span>Add Material</span>
                            </button>
                        </div>
                        <small v-if="form.errors.items" class="text-rose-500 text-[10px] block mt-1">{{ form.errors.items }}</small>

                        <div class="matrix-table-wrap">
                            <table class="w-full">
                                <thead>
                                    <tr>
                                        <th>Material Specification</th>
                                        <th class="text-right w-32">Qty (Kg)</th>
                                        <th class="w-10"></th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr v-for="(item, index) in form.items" :key="index">
                                        <td>
                                            <BaseSelect v-model="item.product_id" :options="productOptions" optionLabel="label" optionValue="value" filter placeholder="Select Material..." fluid />
                                            <small v-if="form.errors[`items.${index}.product_id`]" class="text-rose-500 text-[10px] mt-1 block">{{ form.errors[`items.${index}.product_id`] }}</small>
                                        </td>
                                        <td>
                                            <BaseInputNumber v-model="item.quantity" :minFractionDigits="2" fluid :inputClass="'text-right font-bold'" />
                                            <small v-if="form.errors[`items.${index}.quantity`]" class="text-rose-500 text-[10px] mt-1 block text-right">{{ form.errors[`items.${index}.quantity`] }}</small>
                                        </td>
                                        <td class="text-center">
                                            <BaseDeleteButton @click="removeItem(index)" :disabled="form.items.length <= 1" />
                                        </td>
                                    </tr>
                                </tbody>
                                <tfoot>
                                    <tr class="bg-slate-50">
                                        <td class="text-[10px] font-bold uppercase text-slate-400 text-right">Total Batch Weight:</td>
                                        <td class="text-right font-black text-indigo-600 text-sm">
                                            {{ form.items.reduce((sum, i) => sum + Number(i.quantity || 0), 0).toFixed(0) }} Kg
                                        </td>
                                        <td></td>
                                    </tr>
                                </tfoot>
                            </table>
                        </div>
                    </div>
                </div>

                <div class="expansion-actions flex justify-end mt-8 pt-6 border-t border-slate-100">
                    <BaseFormActions
                        label="Save Mix Grade"
                        cancelLabel="Reset Form"
                        :loading="form.processing"
                        @submit="submit"
                        @reset="form.reset(); form.clearErrors()"
                    />
                </div>
            </div>
        </Transition>
    </div>
</template>

<style scoped>
.create-panel { background: white; border-radius: 16px; border: 1px solid #e2e8f0; box-shadow: 0 1px 3px rgba(0,0,0,0.05); overflow: hidden; transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1); }
.create-panel--open { border-color: #c7d2fe; box-shadow: 0 10px 30px -10px rgba(99,102,241,0.2), 0 4px 12px rgba(0,0,0,0.05); }
.create-panel__header { display: flex; align-items: center; justify-content: space-between; width: 100%; padding: 18px 24px; background: transparent; border: none; cursor: pointer; text-align: left; }
.create-panel__header:hover { background: #fafafa; }
.create-panel__icon { width: 44px; height: 44px; border-radius: 12px; background: linear-gradient(135deg, #eef2ff, #e0e7ff); display: flex; align-items: center; justify-content: center; flex-shrink: 0; box-shadow: inset 0 2px 4px rgba(255,255,255,0.8); }
.create-panel__toggle { width: 28px; height: 28px; border-radius: 50%; background: #eef2ff; display: flex; align-items: center; justify-content: center; color: #6366f1; transition: all 0.3s ease; }
.create-panel__toggle--open { transform: rotate(45deg); background: #6366f1; color: white; box-shadow: 0 4px 12px rgba(99,102,241,0.4); }
.create-panel__body { padding: 32px 24px; border-top: 1px solid #f1f5f9; background: linear-gradient(180deg, #fcfdff 0%, #ffffff 100%); }

.section-title { display: flex; align-items: center; gap: 8px; font-size: 10px; font-weight: 800; text-transform: uppercase; letter-spacing: 0.12em; color: #94a3b8; margin-bottom: 20px; }

.ratio-input-card { background: #f8fafc; border: 1px solid #eef2ff; border-radius: 14px; padding: 16px; border-style: dashed; }
.calc-btn { width: 100%; display: flex; align-items: center; justify-content: center; gap: 8px; padding: 10px; background: white; border: 1px solid #e2e8f0; border-radius: 10px; font-size: 10px; font-weight: 800; text-transform: uppercase; color: #6366f1; cursor: pointer; transition: all 0.2s; }
.calc-btn:hover { background: #6366f1; color: white; border-color: #6366f1; transform: translateY(-1px); box-shadow: 0 4px 12px rgba(99,102,241,0.2); }

.matrix-table-wrap { border: 1px solid #f1f5f9; border-radius: 16px; overflow: hidden; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.02); }
.matrix-table-wrap table th { padding: 12px 16px; background: #f8fafc; font-size: 9px; font-weight: 800; text-transform: uppercase; color: #94a3b8; border-bottom: 1px solid #f1f5f9; }
.matrix-table-wrap table td { padding: 8px 12px; border-bottom: 1px solid #f8fafc; }

.trash-btn { width: 32px; height: 32px; display: flex; align-items: center; justify-content: center; color: #cbd5e1; transition: color 0.2s; cursor: pointer; border: none; background: none; }
.trash-btn:hover:not(:disabled) { color: #f43f5e; }
.trash-btn:disabled { opacity: 0.3; cursor: not-allowed; }

.add-item-btn { display: flex; align-items: center; gap: 6px; padding: 6px 14px; border-radius: 8px; background: #f0f7ff; color: #0284c7; font-size: 9px; font-weight: 800; text-transform: uppercase; border: none; cursor: pointer; transition: all 0.2s; }
.add-item-btn:hover { background: #0284c7; color: white; }

.panel-slide-enter-active { transition: all 0.4s cubic-bezier(0.16, 1, 0.3, 1); }
.panel-slide-leave-active { transition: all 0.25s ease-out; }
.panel-slide-enter-from, .panel-slide-leave-to { opacity: 0; transform: translateY(-10px) scale(0.995); }
</style>
