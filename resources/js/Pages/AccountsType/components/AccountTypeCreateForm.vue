<script setup lang="ts">
import { ref, computed } from 'vue';
import axios from 'axios';
import Dialog from 'primevue/dialog';
import Button from 'primevue/button';
import BaseInput from '@/Components/Base/BaseInput.vue';
import BaseSelect from '@/Components/Base/BaseSelect.vue';
import { useAccountsTypeStore } from '../useAccountsTypeStore';
import { useToast } from 'primevue/usetoast';

const props = defineProps<{
    show: boolean;
    accounts: any[];
}>();

const emit = defineEmits<{
    (e: 'update:show', val: boolean): void;
    (e: 'saved'): void;
}>();

const store = useAccountsTypeStore();
const toast = useToast();

const visible = computed({
    get: () => props.show,
    set: (val) => emit('update:show', val)
});

const defaultForm = () => ({
    title: '',
    code: '',
    account_id: null as number | null,
    parent_id: null as number | null,
    status: 1,
    processing: false,
    errors: { account_id: '', title: '', code: '' } as any,
});

const form = ref(defaultForm());

const accountOptions = computed(() =>
    (props.accounts || []).map(acc => ({ label: acc.title, value: acc.id }))
);

const parentTypeOptions = computed(() =>
    (store.accountTypes || []).map(at => ({ label: at.title, value: at.id }))
);

const handleAccountChange = async (accountId: number) => {
    const account = props.accounts.find(a => a.id === accountId);
    if (!account) return;

    try {
        const res = await axios.get(route('accounting.nextcode'), {
            params: { category: account.title, table: 'account_types' }
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
    form.value.errors = { account_id: '', title: '', code: '' };

    try {
        const response = await axios.post(route('accounttypes.store'), form.value);
        store.addAccountType(response.data.account_type);
        toast.removeAll();
        toast.add({ severity: 'success', summary: 'Success', detail: response.data.message, life: 3000 });
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
    <Dialog v-model:visible="visible" modal header="CREATE ACCOUNT TYPE" :style="{ width: '450px' }">
        <div class="flex flex-col gap-4 py-2">
            <div class="grid grid-cols-3 gap-4">
                <div class="flex flex-col gap-1">
                    <label for="code" class="text-xs font-semibold uppercase text-gray-500">Code</label>
                    <BaseInput id="code" v-model="form.code" fluid class="p-inputtext-sm" />
                    <small v-if="form.errors.code" class="text-red-500">{{ form.errors.code[0] }}</small>
                </div>
                <div class="flex flex-col gap-1 col-span-2">
                    <label for="title" class="text-xs font-semibold uppercase text-gray-500">Title</label>
                    <BaseInput id="title" v-model="form.title" fluid class="p-inputtext-sm" />
                    <small v-if="form.errors.title" class="text-red-500">{{ form.errors.title[0] }}</small>
                </div>
            </div>

            <div class="flex flex-col gap-1">
                <label class="text-xs font-semibold uppercase text-gray-500">Account Context</label>
                <BaseSelect 
                    v-model="form.account_id" 
                    :options="accountOptions" 
                    optionLabel="label" 
                    optionValue="value"
                    placeholder="Select Account..." 
                    fluid 
                    @change="handleAccountChange(form.account_id)"
                    class="p-select-sm"
                />
                <small v-if="form.errors.account_id" class="text-red-500">{{ form.errors.account_id[0] }}</small>
            </div>

            <div class="flex flex-col gap-1">
                <label class="text-xs font-semibold uppercase text-gray-500">Parent Type (Optional)</label>
                <BaseSelect 
                    v-model="form.parent_id" 
                    :options="parentTypeOptions" 
                    optionLabel="label" 
                    optionValue="value"
                    placeholder="Select Parent..." 
                    showClear 
                    fluid
                    class="p-select-sm"
                />
            </div>
        </div>

        <template #footer>
            <div class="flex gap-2 justify-end mt-4">
                <Button label="Cancel" text severity="secondary" @click="close" />
                <Button label="Save" :loading="form.processing" @click="submit" severity="primary" />
            </div>
        </template>
    </Dialog>
</template>
