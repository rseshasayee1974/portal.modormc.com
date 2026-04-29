<script setup lang="ts">
import { ref, computed } from 'vue';
import { useForm } from '@inertiajs/vue3';
import axios from 'axios';
import BaseInput from '@/Components/Base/BaseInput.vue';
import BaseInputNumber from '@/Components/Base/BaseInputNumber.vue';
import BaseSelect from '@/Components/Base/BaseSelect.vue';
import BaseFormActions from '@/Components/Base/BaseFormActions.vue';
import BaseDeleteButton from '@/Components/Base/BaseDeleteButton.vue';
import {
    PlusIcon,
    BeakerIcon,
    VariableIcon,
    TableCellsIcon
} from '@heroicons/vue/24/outline';
import Swal from 'sweetalert2';

const props = defineProps<{
    products: any[];
    units: any[];
    partners: any[];
    defaultUomId?: number | null;
    designTypes: any[];
}>();

const emit = defineEmits(['created']);

const isOpen = ref(true);
const toggle = () => { isOpen.value = !isOpen.value; };

const partnerOptions = computed(() => props.partners.map(p => ({ label: p.legal_name, value: p.id })));
const productOptions = computed(() => props.products.map(p => ({ label: p.title, value: p.id })));
const unitOptions = computed(() => props.units.map(u => ({ label: u.unit_code, value: u.id })));
const typeOptions = computed(() => props.designTypes.map(t => ({ label: t.name, value: t.name })));
const fallbackUomId = computed(() => props.defaultUomId ?? props.units[0]?.id ?? null);

const blankItem = () => ({
    product_id: null as number | null,
    uom_id: fallbackUomId.value,
    rate: 0,
    actual_quantity: 0,
    cross_quantity: 0,
    variation_quantity: 0
});

const form = useForm({
    partner_id: null as number | null,
    design_name: '',
    design_code: '',
    design_type: '',
    unit_id: fallbackUomId.value,
    rate_per_qty: 0,
    items: [blankItem()] as any[]
});

const addItem = () => form.items.push(blankItem());
const removeItem = (index: number) => { if (form.items.length > 1) form.items.splice(index, 1); };

const handleGradeChange = async () => {
    const value = form.design_type;
    if (!value) return;
    const grade = props.designTypes.find(t => t.name === value);
    if (!grade) return;
    if (!form.design_name) form.design_name = `${grade.name} Mix`;
    try {
        const response = await axios.get(route('mixdesigns.gradeingredients', grade.id));
        if (response.data.items?.length > 0) {
            form.items = response.data.items.map((item: any) => ({
                product_id: item.product_id,
                uom_id: item.uom_id ?? fallbackUomId.value,
                actual_quantity: item.actual_quantity,
                rate: item.rate,
                cross_quantity: item.cross_quantity,
                variation_quantity: 0
            }));
            Swal.fire({ toast: true, position: 'top-end', showConfirmButton: false, timer: 2000, icon: 'info', title: `Loaded ingredients for ${grade.name}` });
        }
    } catch (e) { console.error(e); }
};

const submit = () => {
    form.clearErrors();
    let hasErrors = false;

    if (!form.partner_id) { form.setError('partner_id', 'Partner is required.'); hasErrors = true; }
    if (!form.design_name?.trim()) { form.setError('design_name', 'Design Name is required.'); hasErrors = true; }

    if (form.items.length === 0) {
        form.setError('items', 'At least one ingredient is required.');
        hasErrors = true;
    } else {
        form.items.forEach((item, i) => {
            if (!item.product_id) { form.setError(`items.${i}.product_id`, 'Material required.'); hasErrors = true; }
            if (!item.uom_id) { form.setError(`items.${i}.uom_id`, 'UOM required.'); hasErrors = true; }
            if (!item.actual_quantity || item.actual_quantity <= 0) { form.setError(`items.${i}.actual_quantity`, 'Qty > 0'); hasErrors = true; }
             if (!item.cross_quantity || item.cross_quantity <= 0) { form.setError(`items.${i}.cross_quantity`, 'Qty > 0'); hasErrors = true; }
        });
    }

    if (hasErrors) return;

    form.post(route('mixdesigns.store'), {
        onSuccess: () => {
            form.reset();
            form.items = [blankItem()];
            emit('created');
            Swal.fire({ toast: true, position: 'top-end', icon: 'success', title: 'Mix Design created', timer: 2500, showConfirmButton: false });
        }
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
                    <p class="text-xs font-bold text-gray-700 uppercase tracking-widest">Create New Mix Design</p>
                    <p class="text-[11px] text-gray-400 font-medium mt-0.5">Define ingredient matrix, rates and partner assignment</p>
                </div>
            </div>
            <div class="create-panel__toggle" :class="{ 'create-panel__toggle--open': isOpen }">
                <PlusIcon class="w-4 h-4" />
            </div>
        </button>

        <div class="create-panel__body" v-show="isOpen">
            <div class="grid grid-cols-12 gap-6 mb-8">
                <div class="col-span-12 lg:col-span-4">
                    <div class="section-title"><VariableIcon class="w-3.5 h-3.5" /><span>Identification</span></div>
                    <div class="space-y-4">
                        <div>
                            <BaseSelect v-model="form.partner_id" :options="partnerOptions" optionLabel="label" optionValue="value" filter placeholder="Select Partner *" fluid />
                            <small v-if="form.errors.partner_id" class="err-msg">{{ form.errors.partner_id }}</small>
                        </div>
                        <BaseInput v-model="form.design_name" label="Design Name *" placeholder="e.g. M25 Standard Pump" :error="form.errors.design_name" />
                        <BaseInput v-model="form.design_code" label="Internal Code" placeholder="DM-001" :error="form.errors.design_code" />
                        <div>
                            <BaseSelect v-model="form.design_type" :options="typeOptions" optionLabel="label" optionValue="value" @change="handleGradeChange" filter placeholder="Concrete Grade" fluid />
                        </div>
                        <div class="grid grid-cols-2 gap-3">
                            <div>
                                <BaseSelect v-model="form.unit_id" :options="unitOptions" optionLabel="label" optionValue="value" placeholder="Selling Unit" fluid />
                            </div>
                            <BaseInputNumber v-model="form.rate_per_qty"   :minFractionDigits="2" placeholder="0.00" fluid />
                        </div>
                    </div>
                </div>

                <!-- Ingredient Matrix -->
                <div class="col-span-12 lg:col-span-8">
                    <div class="flex justify-between items-center mb-3">
                        <div class="section-title"><TableCellsIcon class="w-3.5 h-3.5" /><span>Ingredient Matrix (per 1 m³)</span></div>
                        <button type="button" @click="addItem" class="add-btn-mini">
                            <PlusIcon class="w-3 h-3" /><span>Add Row</span>
                        </button>
                    </div>
                    <small v-if="form.errors.items" class="err-msg mb-2 block">{{ form.errors.items }}</small>

                    <div class="matrix-wrap">
                        <table class="w-full">
                            <thead>
                                <tr>
                                    <th>Material</th>
                                    <th class="w-24 text-center">UOM</th>
                                    <th class="w-28 text-right">Target Qty</th>
                                    <th class="w-28 text-right">Gross Qty</th>
                                    <!-- <th class="w-28 text-right">Rate</th>
                                    <th class="w-28 text-right">Total</th> -->
                                    <th class="w-8"></th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-for="(item, index) in form.items" :key="index">
                                    <td>
                                        <BaseSelect v-model="item.product_id" :options="productOptions" optionLabel="label" optionValue="value" filter placeholder="Select Material" fluid />
                                        <small v-if="form.errors[`items.${index}.product_id`]" class="err-msg">{{ form.errors[`items.${index}.product_id`] }}</small>
                                    </td>
                                    <td>
                                        <BaseSelect v-model="item.uom_id" :options="unitOptions" optionLabel="label" optionValue="value" placeholder="UOM" fluid />
                                        <small v-if="form.errors[`items.${index}.uom_id`]" class="err-msg">{{ form.errors[`items.${index}.uom_id`] }}</small>
                                    </td>
                                    <td>
                                        <BaseInputNumber v-model="item.actual_quantity" readonly :minFractionDigits="3" placeholder="0.0000" fluid :inputClass="'text-right'" />
                                        <small v-if="form.errors[`items.${index}.actual_quantity`]" class="err-msg text-right block">{{ form.errors[`items.${index}.actual_quantity`] }}</small>
                                    </td>
                                    <td>
                                        <BaseInputNumber v-model="item.cross_quantity" :minFractionDigits="3" placeholder="0.0000" fluid :inputClass="'text-right'" />
                                        <small v-if="form.errors[`items.${index}.cross_quantity`]" class="err-msg text-right block">{{ form.errors[`items.${index}.cross_quantity`] }}</small>
                                    </td>
                                    <!-- <td>
                                        <BaseInputNumber v-model="item.rate" :minFractionDigits="2" placeholder="0.00" fluid :inputClass="'text-right'" />
                                    </td>
                                    <td class="text-right font-mono font-bold text-slate-600 text-xs px-2">
                                        ₹{{ ((item.cross_quantity || 0) * (item.rate || 0)).toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 }) }}
                                    </td> -->
                                    <td>
                                        <BaseDeleteButton @click="removeItem(index)" :disabled="form.items.length <= 1" />
                                    </td>
                                </tr>
                            </tbody>
                            <!-- <tfoot>
                                <tr>
                                    <td colspan="3" class="text-right text-[10px] font-bold text-slate-400 uppercase px-2 py-3">Total Cost per m³:</td>
                                    <td class="text-right font-black text-indigo-600 px-2">
                                        ₹{{ form.items.reduce((s, i) => s + (Number(i.cross_quantity || 0) * Number(i.rate || 0)), 0).toLocaleString(undefined, { minimumFractionDigits: 2 }) }}
                                    </td>
                                    <td></td>
                                </tr>
                            </tfoot> -->
                        </table>
                    </div>
                </div>
            </div>

            
        </div>
        <div class="flex justify-end py-6 border-t border-slate-100">
                <BaseFormActions label="Save Mix Design" :loading="form.processing" @submit="submit" @reset="() => { form.reset(); form.items = [blankItem()]; }" />
            </div>
    </div>
</template>

<style scoped>

</style>
