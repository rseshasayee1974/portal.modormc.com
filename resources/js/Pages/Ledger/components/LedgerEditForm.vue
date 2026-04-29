<script setup lang="ts">
import { ref, computed, watch } from 'vue';
import axios from 'axios';
import Dialog from 'primevue/dialog';
import Button from 'primevue/button';
import BaseInput from '@/Components/Base/BaseInput.vue';
import BaseSelect from '@/Components/Base/BaseSelect.vue';
import ToggleSwitch from 'primevue/toggleswitch';
import Textarea from 'primevue/textarea';
import { useToast } from 'primevue/usetoast';
import { useLedgerStore, Ledger } from '../useLedgerStore';

const props = defineProps<{
    show: boolean;
    mode: 'edit' | 'view';
    ledger: Ledger | null;
    accountTypes: any[];
}>();

const emit = defineEmits<{
    (e: 'update:show', val: boolean): void;
    (e: 'saved'): void;
}>();

const toast = useToast();
const store = useLedgerStore();

const visible = computed({
    get: () => props.show,
    set: (val) => emit('update:show', val)
});

const form = ref({
    title: '',
    code: '',
    is_pnl: false,
    account_type_id: null as number | null,
    description: '',
    notes: '',
    status: 1,
    processing: false,
    errors: {} as any,
});

watch(() => props.ledger, (l) => {
    if (l) {
        form.value = {
            title: l.title || '',
            code: l.code || '',
            is_pnl: !!l.is_pnl,
            account_type_id: l.account_type_id,
            description: l.description || '',
            notes: l.notes || '',
            status: l.status,
            processing: false,
            errors: {},
        };
    }
}, { immediate: true });

const accountTypeOptions = computed(() =>
    (props.accountTypes || []).map(at => ({ label: at.title, value: at.id }))
);

const submit = async () => {
    if (!props.ledger) return;

    form.value.processing = true;
    form.value.errors = {};

    try {
        const response = await axios.put(route('ledgers.update', props.ledger.id), form.value);
        store.updateLedger(response.data.ledger);
        toast.removeAllGroups();
        toast.add({ severity: 'success', summary: 'Success', detail: response.data.message || 'Ledger updated successfully' , life: 1000 });
        emit('saved');
        close();
    } catch (error: any) {
        if (error.response?.data?.errors) {
            form.value.errors = error.response.data.errors;
        } else {
            toast.removeAllGroups();
            toast.add({ severity: 'error', summary: 'Error', detail: 'An unexpected error occurred.', life: 1000 });
        }
    } finally {
        form.value.processing = false;
    }
};

const close = () => {
    visible.value = false;
};
</script>

<template>
    <Dialog v-model:visible="visible" modal :header="mode.toUpperCase() + ' LEDGER'" :style="{ width: '500px' }" class="p-fluid">
        <div class="grid grid-cols-12 gap-4 py-4">
            <div class="col-span-12 md:col-span-4 flex flex-col gap-1">
                <label class="text-[10px] font-bold uppercase text-gray-400">Account Code</label>
                <BaseInput v-model="form.code" placeholder="e.g. 5101" :disabled="mode === 'view'" :error="form.errors.code ? form.errors.code[0] : ''" />
            </div>
            <div class="col-span-12 md:col-span-8 flex flex-col gap-1">
                <label class="text-[10px] font-bold uppercase text-gray-400">Ledger Name</label>
                <BaseInput v-model="form.title" placeholder="Petty Cash / Office Rent" :disabled="mode === 'view'" :error="form.errors.title ? form.errors.title[0] : ''" />
            </div>
            <div class="col-span-12 flex flex-col gap-1">
                <label class="text-[10px] font-bold uppercase text-gray-400">Account Type</label>
                <BaseSelect 
                    v-model="form.account_type_id" 
                    :options="accountTypeOptions" 
                    optionLabel="label" 
                    optionValue="value" 
                    placeholder="Select Group" 
                    :disabled="mode === 'view'" 
                    filter 
                    :error="form.errors.account_type_id ? form.errors.account_type_id[0] : ''"
                />
            </div>
            <div class="col-span-12 flex items-center gap-4 p-3 bg-gray-50/50 rounded">
                <ToggleSwitch v-model="form.is_pnl" :disabled="mode === 'view'" />
                <div class="flex flex-col">
                    <span class="text-xs font-bold text-gray-700">Impact Profit & Loss</span>
                    <span class="text-[9px] text-gray-400">Determines if this ledger reflects on income statement.</span>
                </div>
            </div>
            <div class="col-span-12 flex flex-col gap-1">
                <label class="text-[10px] font-bold uppercase text-gray-400">Description</label>
                <Textarea v-model="form.description" rows="2" placeholder="Purpose of this account..." :disabled="mode === 'view'" />
                <small v-if="form.errors.description" class="text-red-500">{{ form.errors.description[0] }}</small>
            </div>
        </div>

        <template #footer>
            <div class="flex gap-2 justify-end mt-4 pt-4 border-t">
                <Button :label="mode === 'view' ? 'Close' : 'Cancel'" text severity="secondary" @click="close" />
                <Button v-if="mode !== 'view'" label="Save Ledger" icon="pi pi-check" :loading="form.processing" @click="submit" />
            </div>
        </template>
    </Dialog>
</template>
