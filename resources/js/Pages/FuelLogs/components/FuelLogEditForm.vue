<script setup lang="ts">
import { ref } from 'vue';
import { 
    SwatchIcon,
    PencilSquareIcon,
    PaperClipIcon,
    XMarkIcon
} from '@heroicons/vue/24/outline';
import BaseInput from '@/Components/Base/BaseInput.vue';
import BaseSelect from '@/Components/Base/BaseSelect.vue';
import BaseDatePicker from '@/Components/Base/BaseDatePicker.vue';
import BaseInputNumber from '@/Components/Base/BaseInputNumber.vue';
import BaseFormActions from '@/Components/Base/BaseFormActions.vue';
import BaseButton from '@/Components/Base/BaseButton.vue';

const props = defineProps<{
    form: any;
    logId: number;
    machineOptions: any[];
    driverOptions: any[];
    paymentMethodOptions: any[];
    calculatedTotal: string;
    attachmentFile: File | null;
    resetForm: () => void;
    submit: () => void;
}>();

const emit = defineEmits(['file-change', 'clear-file']);
const fileInput = ref<HTMLInputElement | null>(null);

const triggerFileInput = () => {
    fileInput.value?.click();
};

const handleFileChange = (e: Event) => {
    const target = e.target as HTMLInputElement;
    if (target.files && target.files.length > 0) {
        emit('file-change', target.files[0]);
    }
};

const clearFile = () => {
    if (fileInput.value) fileInput.value.value = '';
    emit('clear-file');
};
</script>

<template>
    <div class="p-2">
        <div class="flex items-center justify-between gap-4 mb-6 pb-4 border-b border-gray-100 dark:border-gray-800">
            <div class="flex items-center gap-2">
                <PencilSquareIcon class="w-5 h-5 text-indigo-500" />
                <span class="text-xs font-black uppercase text-gray-800 dark:text-gray-100 tracking-wider">
                    Edit Refuel Log: <span class="text-indigo-600 dark:text-indigo-400 font-bold">#{{ logId }}</span>
                </span>
            </div>
            <BaseButton 
                label="Close" 
                text 
                severity="secondary" 
                @click="resetForm" 
                size="small"
            />
        </div>

        <form @submit.prevent="submit" class="space-y-6">
            <div class="grid grid-cols-1 md:grid-cols-12 gap-5">
                <div class="col-span-12 md:col-span-4 field-group">
                    <label class="text-[10px] font-bold uppercase text-gray-400 tracking-widest mb-1.5 block">Machine / Vehicle *</label>
                    <BaseSelect v-model="form.machine_id" :options="machineOptions" optionLabel="label" optionValue="value" placeholder="Select Vehicle" :error="form.errors.machine_id" filter />
                </div>
                <div class="col-span-12 md:col-span-4 field-group">
                    <label class="text-[10px] font-bold uppercase text-gray-400 tracking-widest mb-1.5 block">Driver</label>
                    <BaseSelect v-model="form.driver_id" :options="driverOptions" optionLabel="label" optionValue="value" placeholder="Select Driver" :error="form.errors.driver_id" filter />
                </div>
                <div class="col-span-12 md:col-span-4 field-group">
                    <label class="text-[10px] font-bold uppercase text-gray-400 tracking-widest mb-1.5 block">Date & Time *</label>
                    <BaseDatePicker v-model="form.log_date" showTime hourFormat="24" placeholder="Select Date & Time" :error="form.errors.log_date" />
                </div>

                <div class="col-span-6 md:col-span-3 field-group">
                    <label class="text-[10px] font-bold uppercase text-gray-400 tracking-widest mb-1.5 block">Quantity (Liters) *</label>
                    <BaseInputNumber v-model="form.quantity" placeholder="0.00" :minFractionDigits="2" :maxFractionDigits="2" :error="form.errors.quantity" />
                </div>
                <div class="col-span-6 md:col-span-3 field-group">
                    <label class="text-[10px] font-bold uppercase text-gray-400 tracking-widest mb-1.5 block">Rate / Liter *</label>
                    <BaseInputNumber v-model="form.rate_per_liter" placeholder="0.00" :minFractionDigits="2" :maxFractionDigits="2" :error="form.errors.rate_per_liter" />
                </div>
                <div class="col-span-6 md:col-span-3 field-group">
                    <BaseInput :modelValue="calculatedTotal" label="Calculated Total Amount" disabled class="font-bold text-indigo-650 bg-slate-50 border-slate-100" />
                </div>
                <div class="col-span-6 md:col-span-3 field-group">
                    <label class="text-[10px] font-bold uppercase text-gray-400 tracking-widest mb-1.5 block">Odometer Reading (Km) *</label>
                    <BaseInputNumber v-model="form.odometer_reading" placeholder="0" :error="form.errors.odometer_reading" />
                </div>

                <div class="col-span-6 md:col-span-3 field-group">
                    <label class="text-[10px] font-bold uppercase text-gray-400 tracking-widest mb-1.5 block">Hourmeter (if applicable)</label>
                    <BaseInputNumber v-model="form.hourmeter_reading" placeholder="0" :error="form.errors.hourmeter_reading" />
                </div>
                <div class="col-span-6 md:col-span-3 field-group">
                    <BaseInput v-model="form.pump_name" label="Fuel Pump / Station" placeholder="Pump station name" :error="form.errors.pump_name" />
                </div>
                <div class="col-span-6 md:col-span-3 field-group">
                    <BaseInput v-model="form.bill_no" label="Bill / Receipt No" placeholder="Enter receipt number" :error="form.errors.bill_no" />
                </div>
                <div class="col-span-6 md:col-span-3 field-group">
                    <label class="text-[10px] font-bold uppercase text-gray-400 tracking-widest mb-1.5 block">Payment Method</label>
                    <BaseSelect v-model="form.payment_method" :options="paymentMethodOptions" optionLabel="label" optionValue="value" :error="form.errors.payment_method" />
                </div>

                <div class="col-span-12 md:col-span-8 field-group">
                    <BaseInput v-model="form.notes" label="Notes" placeholder="Additional refueling details..." :error="form.errors.notes" />
                </div>
                
                <div class="col-span-12 md:col-span-4 field-group flex flex-col justify-end">
                    <label class="text-[10px] font-bold uppercase text-gray-400 tracking-widest mb-1.5 block">Receipt Attachment</label>
                    <div class="flex items-center gap-3">
                        <input type="file" ref="fileInput" @change="handleFileChange" accept="image/*" class="hidden" />
                        
                        <button
                            type="button"
                            @click="triggerFileInput"
                            class="flex items-center gap-2 h-10 px-4 rounded-xl border border-dashed border-indigo-300 dark:border-indigo-800 hover:border-indigo-500 hover:bg-indigo-50/10 text-xs font-bold text-indigo-650 dark:text-indigo-400 transition-all cursor-pointer"
                        >
                            <PaperClipIcon class="w-4 h-4" />
                            {{ attachmentFile ? 'Replace Receipt' : 'Upload Receipt' }}
                        </button>
                        
                        <div v-if="attachmentFile" class="flex items-center gap-1.5 px-3 py-1 bg-indigo-50 dark:bg-indigo-950/20 text-indigo-650 dark:text-indigo-400 rounded-lg text-xs font-semibold">
                            <span class="max-w-[120px] truncate">{{ attachmentFile.name }}</span>
                            <button type="button" @click="clearFile" class="text-rose-500 hover:text-rose-700">
                                <XMarkIcon class="w-4 h-4 stroke-[3px]" />
                            </button>
                        </div>
                    </div>
                    
                    <div class="mt-3 flex items-center gap-2">
                        <input type="checkbox" v-model="form.delete_attachment" id="delete_attachment" class="rounded border-slate-300 text-indigo-600 focus:ring-indigo-500" />
                        <label for="delete_attachment" class="text-[10px] font-bold text-rose-500 uppercase tracking-widest cursor-pointer">
                            Remove receipt photo attachment
                        </label>
                    </div>
                </div>
            </div>

            <BaseFormActions 
                :loading="form.processing"
                update-label="Update Refuel Details"
                cancel-label="Discard Changes"
                mode="update"
                class="pt-6 border-t border-gray-100 dark:border-gray-800"
                @cancel="resetForm"
                @submit="submit"
            />
        </form>
    </div>
</template>
