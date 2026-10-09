<!--
Author: ragul-onemodo
Created: 2026-10-09 12:31:49 Asia/Calcutta (UTC+05:30)
-->
<script setup>
import { computed, ref } from 'vue';
import { useForm, usePage } from '@inertiajs/vue3';
import BaseInput from '@/Components/Base/BaseInput.vue';
import BaseSelect from '@/Components/Base/BaseSelect.vue';
import BaseDatePicker from '@/Components/Base/BaseDatePicker.vue';
import { usePermissions } from '@/Composables/usePermissions';
import { entityToday } from '@/Utils/entityDateTime';

const props = defineProps({ lead: { type: Object, default: null }, owners: Array, options: Object, modal: Boolean, formId: String });
const emit = defineEmits(['saved', 'cancel']);
const { can } = usePermissions();
const page = usePage();
const formElement = ref(null);
const fields = ['enquiry_date', 'contact_name', 'company_name', 'phone', 'email', 'address', 'source', 'status', 'priority', 'assigned_to', 'project_name', 'requirement', 'estimated_quantity', 'delivery_location', 'expected_value', 'expected_purchase_date', 'remarks', 'lost_reason'];
const defaults = { enquiry_date: entityToday(), contact_name: '', company_name: '', phone: '', email: '', address: '', source: 'Phone', status: 'New', priority: 'Medium', assigned_to: page.props.auth?.user?.id ?? null, project_name: '', requirement: '', estimated_quantity: null, delivery_location: '', expected_value: 0, expected_purchase_date: null, remarks: '', lost_reason: '' };
const form = useForm(Object.fromEntries(fields.map(key => [key, props.lead ? (props.lead[key] ?? defaults[key]) : defaults[key]])));
const requirementOptions = computed(() => {
    const choices = [...(props.options.requirements || [])];
    if (form.requirement && !choices.some(item => item.value === form.requirement)) {
        choices.push({ value: form.requirement, label: `${form.requirement} (saved requirement)` });
    }
    return choices;
});
const save = () => {
    if (form.processing) return;
    const settings = { preserveScroll: true, onSuccess: () => { emit('saved'); if (!props.lead) form.reset(); } };
    if (props.lead) form.put(route('crm.leads.update', props.lead.id), settings);
    else form.post(route('crm.leads.store'), settings);
};
const scrollToSection = section => formElement.value?.querySelector(`[data-section="${section}"]`)?.scrollIntoView({ behavior: 'smooth', block: 'start' });
defineExpose({ processing: computed(() => form.processing), errors: computed(() => form.errors), scrollToSection });
</script>

<template>
    <form ref="formElement" :id="formId" @submit.prevent="save" class="space-y-6" :class="{ 'crm-modal-form': modal }" :aria-busy="form.processing">
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4" :class="modal ? 'lg:grid-cols-6' : 'md:grid-cols-6'">
            <div v-if="modal" data-section="contact" class="crm-edit-section-title col-span-full"><span>01</span><div><h3>Contact Information</h3><p>Company and primary contact details</p></div></div>
            <BaseInput v-model="form.contact_name" label="Contact Name" :error="form.errors.contact_name" required />
            <BaseInput v-model="form.company_name" label="Company Name" :error="form.errors.company_name" />
            <BaseInput v-model="form.phone" label="Phone" placeholder="Phone or email required" :error="form.errors.phone" />
            <BaseInput v-model="form.email" type="email" label="Email" :error="form.errors.email" />
            <div v-if="modal" data-section="qualification" class="crm-edit-section-title col-span-full"><span>02</span><div><h3>Lead Qualification</h3><p>Ownership, source and current status</p></div></div>
            <BaseDatePicker v-model="form.enquiry_date" label="Enquiry Date" :error="form.errors.enquiry_date" required />
            <BaseSelect v-model="form.source" :options="options.sources" label="Lead Source" :error="form.errors.source" required />
            <BaseSelect v-model="form.status" :options="lead?.converted_at ? ['Converted'] : options.statuses.filter(s => s !== 'Converted')" label="Lead Status" :disabled="!!lead?.converted_at" :error="form.errors.status" required />
            <BaseSelect v-model="form.priority" :options="options.priorities" label="Priority" :error="form.errors.priority" required />
            <BaseSelect v-model="form.assigned_to" :options="owners" optionLabel="label" optionValue="value" label="Lead Owner" :disabled="!can('CRM_LEAD.ASSIGN')" :showClear="can('CRM_LEAD.ASSIGN')" :error="form.errors.assigned_to" />
            <div v-if="modal" data-section="project" class="crm-edit-section-title col-span-full"><span>03</span><div><h3>Project &amp; Requirements</h3><p>Site, delivery and expected business value</p></div></div>
            <BaseInput v-model="form.project_name" label="Project / Site Name" :error="form.errors.project_name" />
            <BaseInput v-model="form.expected_value" type="number" min="0" label="Expected Value (₹)" :error="form.errors.expected_value" required />
            <BaseDatePicker v-model="form.expected_purchase_date" label="Expected Purchase Date" :error="form.errors.expected_purchase_date" />
            <BaseSelect  v-model="form.requirement" :options="requirementOptions" optionLabel="label" optionValue="value" label="Mix Design Requirement" placeholder="Select a mix design" filter showClear :error="form.errors.requirement" />
            <BaseInput v-model="form.estimated_quantity" type="number" min="0" step="0.001" label="Estimated Quantity" :error="form.errors.estimated_quantity" />
            <BaseInput v-model="form.delivery_location" label="Delivery Location" :error="form.errors.delivery_location" />
        </div>
        <div v-if="modal" data-section="notes" class="crm-edit-section-title"><span>04</span><div><h3>Address &amp; Notes</h3><p>Additional context for the sales team</p></div></div>
        <div class="grid sm:grid-cols-2 gap-4">
            <label class="crm-label">Address<textarea v-model="form.address" rows="2" class="crm-textarea" /><span class="crm-error">{{ form.errors.address }}</span></label>
            <label class="crm-label">Remarks<textarea v-model="form.remarks" rows="2" class="crm-textarea" /><span class="crm-error">{{ form.errors.remarks }}</span></label>
            <label v-if="form.status === 'Unqualified'" class="crm-label sm:col-span-2">Reason for Unqualified Status *<textarea v-model="form.lost_reason" rows="2" required class="crm-textarea" /><span class="crm-error">{{ form.errors.lost_reason }}</span></label>
        </div>
        <div v-if="!modal" class="flex justify-end gap-3 border-t border-slate-100 pt-4">
            <!-- <button type="button" class="crm-button" @click="emit('cancel')">{{ lead ? 'Cancel' : 'Clear Form' }}</button> -->
            <button type="submit" class="crm-primary" :disabled="form.processing">{{ form.processing ? 'Saving…' : (lead ? 'Save Changes' : 'Create Lead') }}</button>
        </div>
    </form>
</template>
