<script setup lang="ts">
import { computed, ref } from 'vue';
import axios from 'axios';
import Select from 'primevue/select';
import Dialog from 'primevue/dialog';
import InputText from 'primevue/inputtext';
import Button from 'primevue/button';
import Message from 'primevue/message';

type TruckOption = { label: string; value: number };

const props = withDefaults(defineProps<{
    modelValue: number | null;
    options?: TruckOption[];
    label?: string;
    placeholder?: string;
    disabled?: boolean;
    error?: string;
    showClear?: boolean;
}>(), {
    options: () => [],
    placeholder: 'Select Truck',
});
const emit = defineEmits<{
    (event: 'update:modelValue', value: number | null): void;
    (event: 'created', option: TruckOption): void;
}>();

const select = ref();
const filterText = ref('');
const dialogVisible = ref(false);
const registration = ref('');
const saving = ref(false);
const registrationError = ref('');
const requestError = ref('');
const createdOptions = ref<TruckOption[]>([]);
const truckOptions = computed(() => [
    ...props.options,
    ...createdOptions.value.filter(option => !props.options.some(existing => existing.value === option.value)),
]);

const openCreate = () => {
    registration.value = filterText.value.trim().toUpperCase();
    registrationError.value = '';
    requestError.value = '';
    select.value?.hide();
    dialogVisible.value = true;
};

const createTruck = async () => {
    if (saving.value) return;
    registrationError.value = '';
    requestError.value = '';
    const value = registration.value.trim().toUpperCase();
    if (!value || value.length > 20) {
        registrationError.value = !value ? 'Enter the truck registration number.' : 'Registration must be at most 20 characters.';
        return;
    }

    saving.value = true;
    try {
        const { data } = await axios.post(route('machines.store'), {
            registration: value,
            vehicle_type: 'Truck',
            is_active: true,
        }, { headers: { Accept: 'application/json' } });
        const option = { label: data.machine.registration, value: data.machine.id };
        createdOptions.value.push(option);
        emit('created', option);
        emit('update:modelValue', option.value);
        dialogVisible.value = false;
    } catch (error: any) {
        registrationError.value = error.response?.data?.errors?.registration?.[0] || '';
        if (!registrationError.value) {
            requestError.value = error.response?.status === 403
                ? 'You do not have permission to create trucks.'
                : error.response?.data?.message || 'Could not create truck. Please try again.';
        }
    } finally {
        saving.value = false;
    }
};
</script>

<template>
    <div class="w-full space-y-1">
        <label v-if="label" class="block text-xs font-semibold text-slate-600">{{ label }}</label>
        <Select ref="select" :modelValue="modelValue" :options="truckOptions" optionLabel="label"
            optionValue="value" :placeholder="placeholder" :disabled="disabled" :invalid="!!error"
            :showClear="showClear" filter autoFilterFocus resetFilterOnHide fluid size="small"
            :aria-label="label || 'Truck'" @update:modelValue="emit('update:modelValue', $event)"
            @filter="filterText = $event.value" @hide="filterText = ''">
            <template #footer>
                <div class="p-2 border-t border-slate-200">
                    <Button type="button" label="Create truck" icon="pi pi-plus" severity="secondary"
                        variant="text" size="small" fluid @click="openCreate" />
                </div>
            </template>
        </Select>
        <small v-if="error" class="block text-red-600">{{ error }}</small>

        <Dialog v-model:visible="dialogVisible" header="Create truck" modal
            :style="{ width: '26rem', maxWidth: '95vw' }" :closable="!saving" :closeOnEscape="!saving">
            <div class="space-y-4">
                <div class="space-y-2">
                    <label class="block text-sm font-medium" >Truck registration number <span class="text-red-500">*</span></label>
                    <InputText v-model="registration" aria-label="Truck registration number" autofocus fluid
                        placeholder="e.g. TN01AB1234" maxlength="20" :disabled="saving"
                        :invalid="!!registrationError" @keydown.enter.prevent="createTruck" />
                    <small v-if="registrationError" class="block text-red-600">{{ registrationError }}</small>
                </div>
                <Message v-if="requestError" severity="error" :closable="false">{{ requestError }}</Message>
            </div>
            <template #footer>
                <Button type="button" label="Cancel" severity="secondary" variant="text" :disabled="saving"
                    @click="dialogVisible = false" />
                <Button type="button" label="Create & select" icon="pi pi-check" :loading="saving"
                    @click="createTruck" />
            </template>
        </Dialog>
    </div>
</template>
