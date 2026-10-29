<!--
Author: ragul-onemodo
Created: 2026-10-29 14:00:41 Asia/Calcutta (UTC+05:30)
-->
<script setup>
import { computed, reactive, ref } from 'vue';
import { router } from '@inertiajs/vue3';
import axios from 'axios';
import Dialog from 'primevue/dialog';
import Button from 'primevue/button';
import Message from 'primevue/message';
import { useToast } from 'primevue/usetoast';
import { usePermissions } from '@/Composables/usePermissions';
import BaseCreatableSelect from '@/Components/Base/BaseCreatableSelect.vue';
import BaseInput from '@/Components/Base/BaseInput.vue';

defineOptions({ inheritAttrs: false });
const props = defineProps({
    modelValue: { default: null },
    options: { type: Array, default: () => [] },
    optionLabel: { type: String, default: 'label' },
    optionValue: { type: String, default: 'value' },
    patronTypes: { type: Array, default: () => ['Customer'] },
    reloadProp: { type: String, default: 'patrons' },
    disabled: { type: Boolean, default: false },
});
const emit = defineEmits(['update:modelValue', 'change', 'created']);
const { can } = usePermissions();
const toast = useToast();
const visible = ref(false);
const saving = ref(false);
const errors = ref({});
const failure = ref('');
const additions = ref([]);
const form = reactive({ legal_name: '', gstin: '', aadhar_number: '', contact_mobile: '' });
const selectOptions = computed(() => [
    ...props.options,
    ...additions.value.filter(patron => !props.options.some(option => option[props.optionValue] === patron.id))
        .map(patron => ({ ...patron, [props.optionLabel]: patron.legal_name, [props.optionValue]: patron.id })),
]);

const openCreate = (name = '') => {
    if (!can('PATRON.create') || props.disabled || saving.value) return;
    Object.assign(form, { legal_name: name.trim(), gstin: '', aadhar_number: '', contact_mobile: '' });
    errors.value = {};
    failure.value = '';
    visible.value = true;
};

const save = async () => {
    if (saving.value || !can('PATRON.create') || props.disabled) return;
    saving.value = true;
    errors.value = {};
    failure.value = '';
    try {
        const response = await axios.post(route('patrons.store'), {
            ...form,
            legal_name: form.legal_name.trim(),
            patron_type: [...props.patronTypes],
            operational_status: 'active', status: true, displayed: true,
        }, { headers: { Accept: 'application/json' } });
        const patron = response.data.patron;
        additions.value.push(patron);
        visible.value = false;
        emit('created', patron);
        toast.add({ severity: 'success', summary: 'Patron Created', detail: patron.legal_name, life: 2000 });
        // Refresh the owning form's lookup before selecting so dependent fields can resolve the patron.
        router.reload({
            only: [props.reloadProp], preserveState: true, preserveScroll: true,
            onFinish: () => {
                emit('update:modelValue', patron.id);
                emit('change', { value: patron.id });
                saving.value = false;
            },
        });
    } catch (error) {
        errors.value = error.response?.data?.errors || {};
        failure.value = Object.keys(errors.value).length ? ''
            : error.response?.data?.message || 'Could not create patron. Please try again.';
        saving.value = false;
    }
};
</script>

<template>
    <BaseCreatableSelect v-bind="$attrs" :modelValue="modelValue" :options="selectOptions"
        :optionLabel="optionLabel" :optionValue="optionValue" :disabled="disabled"
        :canCreate="can('PATRON.create')" :creating="saving" allowEmptyCreate createLabel="Create Patron"
        @update:modelValue="emit('update:modelValue', $event)" @change="emit('change', $event)" @create="openCreate">
        <template v-for="(_, name) in $slots" #[name]="slotProps">
            <slot :name="name" v-bind="slotProps || {}" />
        </template>
    </BaseCreatableSelect>
    <Dialog v-model:visible="visible" header="Create Patron" modal appendTo="body"
        :style="{ width: '32rem' }" :breakpoints="{ '640px': '95vw' }"
        :closable="!saving" :closeOnEscape="!saving">
        <form class="flex flex-col gap-4" @submit.stop.prevent="save">
            <p class="text-sm text-slate-500">{{ patronTypes.join(' / ') }} · Save and select without leaving this form.</p>
            <Message v-if="failure" severity="error" :closable="false">{{ failure }}</Message>
            <BaseInput v-model="form.legal_name" label="Patron Name" required :disabled="saving" :error="errors.legal_name" />
            <p class="text-sm text-slate-500">Provide either GSTIN or Aadhaar Number.</p>
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <BaseInput v-model="form.gstin" label="GSTIN" :disabled="saving" :error="errors.gstin" />
                <BaseInput v-model="form.aadhar_number" label="Aadhaar Number" :disabled="saving" :error="errors.aadhar_number" />
            </div>
            <BaseInput v-model="form.contact_mobile" label="Mobile Number (optional)" :disabled="saving" :error="errors.contact_mobile" />
            <Message v-if="errors.patron_type || errors.operational_status" severity="error" :closable="false">
                {{ errors.patron_type || errors.operational_status }}
            </Message>
            <div class="flex justify-end gap-2">
                <Button label="Cancel" text severity="secondary" :disabled="saving" @click="visible = false" />
                <Button label="Create & Select" icon="pi pi-plus" type="submit" :loading="saving" />
            </div>
        </form>
    </Dialog>
</template>
