<script setup lang="ts">
import { ref, computed } from 'vue';
import axios from 'axios';
import Dialog from 'primevue/dialog';
import Button from 'primevue/button';
import BaseInput from '@/Components/Base/BaseInput.vue';
import BaseSelect from '@/Components/Base/BaseSelect.vue';
import ToggleSwitch from 'primevue/toggleswitch';
import Textarea from 'primevue/textarea';
import { useToast } from 'primevue/usetoast';
import { useLedgerStore } from '../useLedgerStore';

const props = defineProps<{
    show: boolean;
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

const defaultForm = () => ({
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

const form = ref(defaultForm());

const accountTypeOptions = computed(() =>
    (props.accountTypes || []).map(at => ({ label: at.title, value: at.id }))
);

const handleTypeChange = async (typeId: number) => {
    const type = props.accountTypes.find(t => t.id === typeId);
    if (!type || !type.account) return;

    const category = type.account.title.toUpperCase();
    form.value.is_pnl = (category === 'INCOME' || category === 'EXPENSE');

    try {
        const res = await axios.get(route('accounting.nextcode'), {
            params: { category: category, table: 'ledgers' }
        });
        if (res.data.code) {
            form.value.code = res.data.code;
        }
    } catch (e) {
        console.warn('Code gen failed', e);
    }
};

const submit = async () => {
    form.value.processing = true;
    form.value.errors = {};

    try {
        const response = await axios.post(route('ledgers.store'), form.value);
        store.addLedger(response.data.ledger);
        toast.removeAll();
        toast.add({ severity: 'success', summary: 'Success', detail: response.data.message || 'Ledger created successfully' });
        emit('saved');
        close();
    } catch (error: any) {
        if (error.response?.data?.errors) {
            form.value.errors = error.response.data.errors;
        } else {
            toast.removeAll();
            toast.add({ severity: 'error', summary: 'Error', detail: 'An unexpected error occurred.', life: 3000 });
        }
    } finally {
        form.value.processing = false;
    }
};

const close = () => {
    visible.value = false;
    form.value = defaultForm();
};
</script>

<template>
    <Dialog v-model:visible="visible" modal header="CREATE LEDGER" :style="{ width: '500px' }" class="p-fluid">
        <div class="grid grid-cols-12 gap-4 py-4">
            <div class="col-span-12 md:col-span-4 flex flex-col gap-1">
                <label class="text-[10px] font-bold uppercase text-gray-400">Account Code</label>
                <BaseInput v-model="form.code" placeholder="e.g. 5101" :error="form.errors.code ? form.errors.code[0] : ''" />
            </div>
            <div class="col-span-12 md:col-span-8 flex flex-col gap-1">
                <label class="text-[10px] font-bold uppercase text-gray-400">Ledger Name</label>
                <BaseInput v-model="form.title" placeholder="Petty Cash / Office Rent" :error="form.errors.title ? form.errors.title[0] : ''" />
            </div>
            <div class="col-span-12 flex flex-col gap-1">
                <label class="text-[10px] font-bold uppercase text-gray-400">Account Type</label>
                <BaseSelect 
                    v-model="form.account_type_id" 
                    :options="accountTypeOptions" 
                    optionLabel="label" 
                    optionValue="value" 
                    placeholder="Select Group" 
                    @change="handleTypeChange(form.account_type_id)" 
                    filter 
                    :error="form.errors.account_type_id ? form.errors.account_type_id[0] : ''"
                />
            </div>
            <div class="col-span-12 flex items-center gap-4 p-3 bg-gray-50/50 rounded">
                <ToggleSwitch v-model="form.is_pnl" />
                <div class="flex flex-col">
                    <span class="text-xs font-bold text-gray-700">Impact Profit & Loss</span>
                    <span class="text-[9px] text-gray-400">Determines if this ledger reflects on income statement.</span>
                </div>
            </div>
            <div class="col-span-12 flex flex-col gap-1">
                <label class="text-[10px] font-bold uppercase text-gray-400">Description</label>
                <Textarea v-model="form.description" rows="2" placeholder="Purpose of this account..." />
                <small v-if="form.errors.description" class="text-red-500">{{ form.errors.description[0] }}</small>
            </div>
        </div>

        <template #footer>
            <div class="flex gap-2 justify-end mt-4 pt-4 border-t">
                <Button label="Cancel" text severity="secondary" @click="close" />
                <Button label="Save Ledger" icon="pi pi-check" :loading="form.processing" @click="submit" />
            </div>
        </template>
    </Dialog>
</template>
