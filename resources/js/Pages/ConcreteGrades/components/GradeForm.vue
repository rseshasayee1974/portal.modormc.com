<script setup lang="ts">
import { computed, watch } from 'vue';
import { 
    TrashIcon,
    PlusIcon,
    ArrowPathIcon,
    VariableIcon,
    CalculatorIcon,
    TableCellsIcon
} from '@heroicons/vue/24/outline';
import Swal from 'sweetalert2';

// PrimeVue
import BaseInput from '@/Components/Base/BaseInput.vue';
import BaseInputNumber from '@/Components/Base/BaseInputNumber.vue';
import BaseSelect from '@/Components/Base/BaseSelect.vue';
import ToggleSwitch from 'primevue/toggleswitch';
import Button from 'primevue/button';

const props = defineProps<{
    form: any;
    products: any[];
    submit: () => void;
    mode: 'create' | 'edit';
    title?: string;
    onReset?: () => void;
}>();

const productOptions = computed(() => props.products.map(p => ({ label: p.title, value: p.id })));
console.log(props.products);
watch(
    () => [props.form.cement_ratio, props.form.sand_ratio, props.form.aggregate_ratio],
    ([c, s, a]) => {
        const cVal = c || 0;
        const sVal = s || 0;
        const aVal = a || 0;
        if (cVal > 0 || sVal > 0 || aVal > 0) {
            props.form.concrete_ratio = `${cVal}:${sVal}:${aVal}`;
        }
    },
    { deep: true, immediate: true }
);

const addItem = () => {
    props.form.items.push({
        product_id: null,
        quantity: 0
    });
};

const removeItem = (index: number) => {
    if (props.form.items.length > 1) {
        props.form.items.splice(index, 1);
    }
};

const applyCalculatedProportions = () => {
    const c = props.form.cement_ratio || 0;
    const s = props.form.sand_ratio || 0;
    const a = props.form.aggregate_ratio || 0;
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
        props.form.items = items;
        Swal.fire({ toast: true, position: 'top-end', icon: 'success', title: 'Calculated weights applied', timer: 1500, showConfirmButton: false });
    }
};

const calcResults = computed(() => {
    const c = props.form.cement_ratio || 0;
    const s = props.form.sand_ratio || 0;
    const a = props.form.aggregate_ratio || 0;
    const total = c + s + a || 1;
    const factor = 1.54;

    const weights = {
        cement: ((c / total) * factor * 1440),
        sand: ((s / total) * factor * 1600),
        aggregate: ((a / total) * factor * 1550)
    };

    return {
        weights,
        totalWeight: weights.cement + weights.sand + weights.aggregate
    };
});

</script>

<template>
    <form @submit.prevent="submit">
        <div class="space-y-8">
            
            <!-- Section 1: General Info -->
            <div class="bg-gray-50/50 dark:bg-slate-800/20 p-6 rounded-2xl border border-gray-100 dark:border-slate-800 shadow-sm transition-all duration-300">
                <div class="flex items-center gap-2 mb-6 border-b border-gray-100 dark:border-slate-800 pb-3">
                    <VariableIcon class="w-5 h-5 text-indigo-500" />
                    <h3 class="text-sm font-black text-gray-700 dark:text-gray-300 uppercase tracking-widest">Base Grade Configuration</h3>
                </div>
                
                <div class="grid grid-cols-1 md:grid-cols-12 gap-6">
                    <div class="md:col-span-3 flex flex-col gap-1.5">
                        <label class="text-[9px] font-black uppercase tracking-widest text-gray-400">Grade Name (Mix Identifier) *</label>
                        <BaseInput v-model="form.name" placeholder="e.g. M25 Supermix" class="w-full rounded-xl" :class="{'p-invalid': form.errors.name}" />
                        <small v-if="form.errors.name" class="p-error">{{ form.errors.name }}</small>
                    </div>
                    <div class="md:col-span-3 flex flex-col gap-1.5">
                        <label class="text-[9px] font-black uppercase tracking-widest text-gray-400">Reference Code</label>
                        <BaseInput v-model="form.concrete_code" placeholder="GRD-V4-25" class="w-full rounded-xl" :class="{'p-invalid': form.errors.concrete_code}" />
                        <small v-if="form.errors.concrete_code" class="p-error">{{ form.errors.concrete_code }}</small>
                    </div>
                    <!-- <div class="md:col-span-6 flex flex-col gap-1.5">
                        <label class="text-[9px] font-black uppercase tracking-widest text-gray-400">Status</label>
                        <div class="flex items-center h-10 gap-3">
                            <ToggleSwitch v-model="form.status" />
                            <span class="text-[10px] font-bold uppercase tracking-widest" :class="form.status ? 'text-emerald-500' : 'text-rose-500'">
                                {{ form.status ? 'ACTIVE' : 'INACTIVE' }}
                            </span>
                        </div>
                    </div> -->
                </div>
            </div>

            <!-- Section 2: Ratio & Weight Calculator -->
            <div class="bg-white dark:bg-slate-900 p-6 rounded-2xl border border-indigo-100 dark:border-indigo-900 shadow-xl shadow-indigo-500/5 relative overflow-hidden group">
                <div class="absolute top-0 right-0 p-4 opacity-5 group-hover:opacity-10 transition-opacity">
                    <CalculatorIcon class="w-24 h-24" />
                </div>

                <div class="flex flex-col md:flex-row justify-between items-start md:items-center mb-6 relative gap-4">
                    <div class="flex items-center gap-2">
                        <CalculatorIcon class="w-5 h-5 text-indigo-600" />
                        <h3 class="text-sm font-black text-indigo-900 dark:text-indigo-400 uppercase tracking-widest">Ratio & Weight Estimator</h3>
                    </div>
                    <div class="flex items-center gap-4">
                        <div class="bg-indigo-600 text-white px-3 py-1 rounded text-[10px] font-black tracking-widest flex items-center gap-2">
                            DRY VOLUME FACTOR: 1.54
                        </div>
                        <Button 
                            severity="primary" 
                            
                            class="text-[10px] font-bold uppercase tracking-widest shadow-lg shadow-indigo-500/20"
                            rounded
                            @click="applyCalculatedProportions"
                        >
                            <template #icon><ArrowPathIcon class="w-3 h-3 mr-2 font-bold" /></template>
                            <span>Populate Matrix</span>
                        </Button>
                    </div>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-8 relative">
                    <!-- Ratio Inputs -->
                    <div class="space-y-4">
                        <div class="grid grid-cols-3 gap-3">
                            <div class="flex flex-col gap-1.5">
                                <label class="text-[9px] font-black uppercase tracking-widest text-gray-400">Cement</label>
                                <BaseInputNumber v-model="form.cement_ratio" :minFractionDigits="2" class="w-full" placeholder="1" />
                            </div>
                            <div class="flex flex-col gap-1.5">
                                <label class="text-[9px] font-black uppercase tracking-widest text-gray-400">Sand</label>
                                <BaseInputNumber v-model="form.sand_ratio" :minFractionDigits="2" class="w-full" placeholder="1.5" />
                            </div>
                            <div class="flex flex-col gap-1.5">
                                <label class="text-[9px] font-black uppercase tracking-widest text-gray-400">Aggregate</label>
                                <BaseInputNumber v-model="form.aggregate_ratio" :minFractionDigits="2" class="w-full" placeholder="3" />
                            </div>
                        </div>
                        <div class="bg-gray-50 dark:bg-slate-950 rounded-xl p-4 flex justify-between items-center border border-dashed border-indigo-200 dark:border-indigo-900">
                             <span class="text-[10px] font-bold text-indigo-400 uppercase tracking-widest">Evaluated Ratio</span>
                             <span class="text-xl font-black text-indigo-900 dark:text-indigo-400 tracking-tighter">{{ form.concrete_ratio || '0:0:0' }}</span>
                        </div>
                    </div>

                    <!-- Calculated Weights -->
                    <div class="bg-indigo-900 rounded-2xl p-5 text-white flex flex-col justify-between">
                        <div class="flex justify-between items-start">
                            <div class="space-y-3 flex-1">
                                <div class="flex justify-between items-center border-b border-white/10 pb-2">
                                    <span class="text-[9px] font-bold opacity-60 uppercase tracking-widest">Cement</span>
                                    <span class="text-lg font-black">{{ calcResults.weights.cement.toFixed(0) }} <span class="text-[8px] opacity-50">Kg</span></span>
                                </div>
                                <div class="flex justify-between items-center border-b border-white/10 pb-2">
                                    <span class="text-[9px] font-bold opacity-60 uppercase tracking-widest">Sand (FA)</span>
                                    <span class="text-lg font-black">{{ calcResults.weights.sand.toFixed(0) }} <span class="text-[8px] opacity-50">Kg</span></span>
                                </div>
                                <div class="flex justify-between items-center border-b border-white/10 pb-2">
                                    <span class="text-[9px] font-bold opacity-60 uppercase tracking-widest">Agg (CA)</span>
                                    <span class="text-lg font-black">{{ calcResults.weights.aggregate.toFixed(0) }} <span class="text-[8px] opacity-50">Kg</span></span>
                                </div>
                            </div>
                        </div>
                        <div class="mt-4 pt-3 border-t border-white/20 flex justify-between items-end">
                            <div>
                                <span class="block text-[8px] font-black opacity-50 uppercase tracking-widest">Base Yield</span>
                                <span class="text-xs font-bold">1.0 m³ Mix</span>
                            </div>
                            <div class="text-right">
                                <span class="block text-[8px] font-black opacity-50 uppercase tracking-widest">Total Batch Weight</span>
                                <span class="text-2xl font-black text-amber-400">{{ calcResults.totalWeight.toFixed(0) }} <span class="text-[10px] opacity-70">Kg</span></span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Section 3: Ingredient Matrix -->
            <div @click.stop>
                <div class="flex justify-between items-center mb-4">
                    <div class="flex items-center gap-2">
                        <TableCellsIcon class="w-5 h-5 text-gray-500" />
                        <h3 class="text-sm font-black text-gray-700 dark:text-gray-300 uppercase tracking-widest">Final Material Matrix</h3>
                    </div>
                    <Button 
                        @click="addItem" 
                        severity="primary" text
                        class="text-[10px] font-black uppercase tracking-widest"
                    >
                        <template #icon><PlusIcon class="w-3 h-3 mr-2 font-bold" /></template>
                        <span>Append Raw Material</span>
                    </Button>
                </div>

                <div class="border border-gray-200 dark:border-slate-800 rounded-2xl overflow-hidden bg-white dark:bg-slate-900 shadow-sm ring-1 ring-gray-900/5">
                    <table class="w-full text-left">
                        <thead>
                            <tr class="bg-gray-50/80 dark:bg-slate-950 border-b border-gray-200 dark:border-slate-800">
                                <th class="px-6 py-4 text-[10px] font-black text-gray-500 uppercase tracking-widest">Specification</th>
                                <th class="px-6 py-4 text-[10px] font-black text-gray-500 uppercase tracking-widest text-right w-48">Design Qty (Kg/m³)</th>
                                <th class="px-6 py-4 w-16"></th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100 dark:divide-slate-800">
                            <tr v-for="(item, index) in form.items" :key="index" class="hover:bg-gray-50/50 dark:hover:bg-slate-800/20 transition-colors">
                                <td class="px-6 py-3">
                                    <BaseSelect v-model="item.product_id" :options="productOptions" optionLabel="label" optionValue="value" filter class="w-full rounded-xl" placeholder="Select material specification..."  />
                                    <small v-if="form.errors[`items.${index}.product_id`]" class="p-error">{{ form.errors[`items.${index}.product_id`] }}</small>
                                </td>
                                <td class="px-6 py-3">
                                    <BaseInputNumber v-model="item.quantity" :minFractionDigits="2" class="w-full font-black text-indigo-700 dark:text-indigo-400"  :inputClass="'text-right'" />
                                    <small v-if="form.errors[`items.${index}.quantity`]" class="p-error text-right block">{{ form.errors[`items.${index}.quantity`] }}</small>
                                </td>
                                <td class="px-6 py-3 text-right">
                                    <Button 
                                        severity="danger" text rounded  
                                        @click="removeItem(index)" 
                                        :disabled="form.items.length <= 1"
                                    >
                                        <template #icon><TrashIcon class="w-4 h-4" /></template>
                                    </Button>
                                </td>
                            </tr>
                        </tbody>
                        <tfoot>
                            <tr class="bg-indigo-50/30 dark:bg-indigo-900/10 font-black border-t-2 border-indigo-100 dark:border-indigo-900">
                                <td class="px-6 py-4 text-[10px] text-indigo-900 dark:text-indigo-400 uppercase tracking-widest text-right">Aggregate Mix Weight (Target)</td>
                                <td class="px-6 py-4 text-right text-indigo-900 dark:text-indigo-400 text-lg">
                                    {{ form.items.reduce((sum, i) => sum + (i.quantity || 0), 0).toFixed(0) }} <span class="text-[10px] opacity-60">Kg</span>
                                </td>
                                <td></td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            </div>

        </div>

        <!-- Footer Actions -->
        <div class="flex justify-end gap-4 mt-12 pt-6 border-t border-gray-100 dark:border-slate-800">
            <Button v-if="mode === 'edit'" @click="onReset" severity="secondary" text class="px-10 h-12 rounded-xl text-[10px] font-black uppercase tracking-widest">
                Discard Changes
            </Button>
            <Button 
                :severity="mode === 'create' ? 'primary' : 'warn'" 
                class="px-12 h-12 rounded-xl text-[10px] font-black uppercase tracking-widest shadow-xl transition-all hover:scale-[1.02]"
                @click="submit"
                :loading="form.processing"
                :label="mode === 'create' ? 'Seal & Save Grade' : 'Re-Seal Mix Grade'"
            />
        </div>
    </form>
</template>

<style scoped>
</style>

>

