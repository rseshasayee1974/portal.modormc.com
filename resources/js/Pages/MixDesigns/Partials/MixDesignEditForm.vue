<script setup lang="ts">
import { computed } from 'vue';
import { useForm } from '@inertiajs/vue3';
import axios from 'axios';
import BaseInput from '@/Components/Base/BaseInput.vue';
import BaseInputNumber from '@/Components/Base/BaseInputNumber.vue';
import BaseSelect from '@/Components/Base/BaseSelect.vue';
import BaseFormActions from '@/Components/Base/BaseFormActions.vue';
import BaseDeleteButton from '@/Components/Base/BaseDeleteButton.vue';
import {
    PlusIcon,
    VariableIcon,
    TableCellsIcon
} from '@heroicons/vue/24/outline';
import Swal from 'sweetalert2';

const props = defineProps<{
    design: any;
    products: any[];
    units: any[];
    partners: any[];
    defaultUomId?: number | null;
    designTypes: any[];
}>();

const emit = defineEmits(['saved', 'cancel']);

const partnerOptions = computed(() => props.partners.map(p => ({ label: p.legal_name, value: p.id })));
const productOptions = computed(() => props.products.map(p => ({ label: p.title, value: p.id })));
const unitOptions = computed(() => props.units.map(u => ({ label: u.unit_code, value: u.id })));
const typeOptions = computed(() => props.designTypes.map(t => ({ label: t.name, value: t.name })));
const fallbackUomId = computed(() => props.defaultUomId ?? props.units[0]?.id ?? null);

const form = useForm({
    partner_id: props.design.partner_id,
    design_name: props.design.design_name,
    design_code: props.design.design_code || '',
    design_type: props.design.design_type || '',
    unit_id: props.design.unit_id,
    rate_per_qty: parseFloat(props.design.rate_per_qty || '0'),
    items: props.design.items.map((item: any) => ({
        product_id: item.product_id,
        uom_id: item.uom_id ?? props.defaultUomId ?? props.units[0]?.id ?? null,
        rate: parseFloat(item.rate || 0),
        actual_quantity: parseFloat(item.actual_quantity || 0),
        cross_quantity: parseFloat(item.cross_quantity || 0),
        variation_quantity: parseFloat(item.variation_quantity || 0),
    }))
});

const blankItem = () => ({
    product_id: null as number | null,
    uom_id: fallbackUomId.value,
    rate: 0,
    actual_quantity: 0,
    cross_quantity: 0,
    variation_quantity: 0
});

const addItem = () => form.items.push(blankItem());
const removeItem = (index: number) => { if (form.items.length > 1) form.items.splice(index, 1); };

const handleGradeChange = async () => {
    const value = form.design_type;
    if (!value) return;
    const grade = props.designTypes.find((t: any) => t.name === value);
    if (!grade) return;
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
        form.items.forEach((item: any, i: number) => {
            if (!item.product_id) { form.setError(`items.${i}.product_id`, 'Material required.'); hasErrors = true; }
            if (!item.uom_id) { form.setError(`items.${i}.uom_id`, 'UOM required.'); hasErrors = true; }
            if (!item.actual_quantity || item.actual_quantity <= 0) { form.setError(`items.${i}.actual_quantity`, 'Qty > 0'); hasErrors = true; }
        });
    }

    if (hasErrors) return;

    form.put(route('mixdesigns.update', props.design.id), {
        onSuccess: () => {
            emit('saved');
            Swal.fire({ toast: true, position: 'top-end', icon: 'success', title: 'Mix Design updated', timer: 2000, showConfirmButton: false });
        }
    });
};
</script>

<template>
    <div class="edit-form-inner">
        <div class="grid grid-cols-12 gap-6">
            <!-- Left: Identification -->
            <div class="col-span-12 lg:col-span-4 space-y-4">
                <div class="section-title"><VariableIcon class="w-3.5 h-3.5" /><span>Identification</span></div>

                <div>
                    <BaseSelect v-model="form.partner_id" :options="partnerOptions" optionLabel="label" optionValue="value" filter placeholder="Select Partner *" fluid />
                    <small v-if="form.errors.partner_id" class="err-msg">{{ form.errors.partner_id }}</small>
                </div>
                <BaseInput v-model="form.design_name" label="Design Name *" placeholder="e.g. M25 Standard Pump" :error="form.errors.design_name" />
                <BaseInput v-model="form.design_code" label="Internal Code" placeholder="DM-001" :error="form.errors.design_code" />
                <div>
                    <BaseSelect v-model="form.design_type" label="Design Type" :options="typeOptions" optionLabel="label" optionValue="value" placeholder="Concrete Grade" fluid disabled />
                </div>
                <div class="grid grid-cols-2 gap-3">
                    
                    <BaseSelect v-model="form.unit_id" label="Unit" :options="unitOptions" class="!h-8" optionLabel="label" optionValue="value" placeholder="Selling Unit" fluid />
                    <BaseInputNumber v-model="form.rate_per_qty" label="Rate per m³"  :minFractionDigits="2" placeholder="0.00" fluid />
                </div>
            </div>

            <!-- Right: Ingredient Matrix -->
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
                                </td>
                                <!-- <td>
                                    <BaseInputNumber v-model="item.rate" :minFractionDigits="2" placeholder="0.00" fluid :inputClass="'text-right'" />
                                </td>
                                <td class="text-right font-mono font-bold text-slate-600 text-xs px-2">
                                    ₹{{ ((item.actual_quantity || 0) * (item.rate || 0)).toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 }) }}
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
                                    ₹{{ form.items.reduce((s, i) => s + (Number(i.actual_quantity || 0) * Number(i.rate || 0)), 0).toLocaleString(undefined, { minimumFractionDigits: 2 }) }}
                                </td>
                                <td></td>
                            </tr>
                        </tfoot> -->
                    </table>
                </div>
            </div>
        </div>

        <div class="flex justify-end gap-3 mt-8 pt-6 border-t border-indigo-100">
            <BaseFormActions
                label="Update Mix Design"
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
.section-title { display: flex; align-items: center; gap: 8px; font-size: 10px; font-weight: 800; text-transform: uppercase; letter-spacing: 0.1em; color: #64748b; margin-bottom: 14px; }

.add-btn-mini { background: #6366f1; color: white; border: none; padding: 5px 12px; border-radius: 6px; font-size: 9px; font-weight: 800; text-transform: uppercase; display: flex; align-items: center; gap: 4px; cursor: pointer; }
.add-btn-mini:hover { background: #4f46e5; }

.matrix-wrap { border: 1px solid #e2e8f0; border-radius: 12px; overflow: hidden; background: white; }
.matrix-wrap table th { padding: 9px 10px; background: #f8faff; font-size: 8px; font-weight: 800; text-transform: uppercase; color: #94a3b8; border-bottom: 1px solid #f1f5f9; }
.matrix-wrap table td { padding: 4px 6px; border-bottom: 1px solid #fafafa; }
.matrix-wrap tfoot td { padding: 10px 8px; background: #f8faff; border-top: 1px solid #e2e8f0; }

.err-msg { color: #ef4444; font-size: 10px; margin-top: 2px; display: block; }
</style>
