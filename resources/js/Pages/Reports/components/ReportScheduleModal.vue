<script setup>
import { ref } from 'vue';
import axios from 'axios';
import Swal from 'sweetalert2';

const props = defineProps({
    isOpen: Boolean,
    reportType: String,
    reportParams: Object,
});

const emit = defineEmits(['close', 'saved']);

const emailRecipients = ref('');
const frequency = ref('daily');
const scheduleTime = ref('09:00');
const isSubmitting = ref(false);

const frequencyOptions = [
    { label: 'Daily', value: 'daily' },
    { label: 'Weekly', value: 'weekly' },
    { label: 'Monthly', value: 'monthly' }
];

const close = () => {
    emit('close');
};

const save = async () => {
    if (!emailRecipients.value) {
        Swal.fire({
            icon: 'error',
            title: 'Validation Error',
            text: 'Please enter at least one email recipient.'
        });
        return;
    }

    isSubmitting.value = true;
    try {
        await axios.post(route('reports.schedules.store'), {
            report_type: props.reportType,
            report_params: props.reportParams,
            email_recipients: emailRecipients.value,
            frequency: frequency.value,
            schedule_time: scheduleTime.value + ':00', // Append seconds
        });

        Swal.fire({
            toast: true,
            position: 'top-end',
            icon: 'success',
            title: 'Report schedule saved successfully.',
            showConfirmButton: false,
            timer: 2000
        });

        emit('saved');
        close();
    } catch (err) {
        console.error('Failed to save schedule:', err);
        Swal.fire({
            icon: 'error',
            title: 'Oops...',
            text: err.response?.data?.message || 'Failed to save report schedule.'
        });
    } finally {
        isSubmitting.value = false;
    }
};
</script>

<template>
    <div v-if="isOpen" class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 overflow-y-auto no-print">
        <div class="bg-white rounded-xl shadow-2xl border border-slate-200 w-full max-w-lg mx-4 overflow-hidden animate-in zoom-in duration-200">
            <!-- Modal Header -->
            <div class="bg-[#1d2d3e] text-white px-6 py-4 flex items-center justify-between border-b border-slate-700">
                <div class="flex items-center gap-2">
                    <svg class="w-5 h-5 text-indigo-400 stroke-2" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M6.75 3v2.25M17.25 3v2.25M3 18.75V7.5a2.25 2.25 0 012.25-2.25h13.5A2.25 2.25 0 0121 7.5v11.25m-18 0A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75m-18 0v-7.5A2.25 2.25 0 015.25 9h13.5A2.25 2.25 0 0121 11.25v7.5m-9-6h.008v.008H12v-.008zM12 15h.008v.008H12V15zm0 2.25h.008v.008H12v-.008zM9.75 15h.008v.008H9.75V15zm0 2.25h.008v.008H9.75v-.008zM7.5 15h.008v.008H7.5V15zm0 2.25h.008v.008H7.5v-.008zm6.75-4.5h.008v.008h-.008v-.008zm0 2.25h.008v.008h-.008v-.008zm2.25-4.5h.008v.008H16.5v-.008zm0 2.25h.008v.008H16.5V15z" />
                    </svg>
                    <h3 class="text-sm font-bold uppercase tracking-wider">Schedule Automatic Delivery</h3>
                </div>
                <button @click="close" class="text-slate-400 hover:text-white transition-colors">
                    <span class="text-xl">&times;</span>
                </button>
            </div>

            <!-- Modal Body -->
            <div class="p-6 space-y-5">
                <div class="rounded-lg bg-indigo-50/50 border border-indigo-100 p-4">
                    <h4 class="text-xs font-black uppercase text-indigo-700 tracking-wider">Selected Report Profile</h4>
                    <p class="text-xs text-slate-700 mt-1 capitalize font-semibold">
                        Type: {{ reportType.replace('_', ' ') }}
                    </p>
                    <p class="text-[10px] text-slate-400 mt-0.5">
                        The scheduler will compile this report at the designated time and email the spreadsheet.
                    </p>
                </div>

                <!-- Frequency -->
                <div>
                    <label class="block text-xs font-bold text-slate-500 uppercase tracking-widest mb-1.5">Delivery Frequency</label>
                    <select v-model="frequency" class="w-full text-xs font-semibold text-slate-700 bg-slate-50 border border-slate-200 rounded-lg p-2.5 focus:outline-none focus:ring-2 focus:ring-[#0064d2]">
                        <option v-for="opt in frequencyOptions" :key="opt.value" :value="opt.value">
                            {{ opt.label }}
                        </option>
                    </select>
                </div>

                <!-- Execution Time -->
                <div>
                    <label class="block text-xs font-bold text-slate-500 uppercase tracking-widest mb-1.5">Scheduled Delivery Time (24h format)</label>
                    <input type="time" v-model="scheduleTime" class="w-full text-xs font-semibold text-slate-700 bg-slate-50 border border-slate-200 rounded-lg p-2.5 focus:outline-none focus:ring-2 focus:ring-[#0064d2]" />
                </div>

                <!-- Recipients -->
                <div>
                    <label class="block text-xs font-bold text-slate-500 uppercase tracking-widest mb-1.5">Email Recipients (comma separated)</label>
                    <textarea 
                        v-model="emailRecipients" 
                        rows="3" 
                        placeholder="e.g. manager@plant.com, owner@mines.com"
                        class="w-full text-xs font-semibold text-slate-700 bg-slate-50 border border-slate-200 rounded-lg p-2.5 focus:outline-none focus:ring-2 focus:ring-[#0064d2]"
                    ></textarea>
                </div>
            </div>

            <!-- Modal Footer -->
            <div class="px-6 py-4 bg-slate-50 border-t border-slate-100 flex justify-end gap-2.5">
                <button 
                    @click="close" 
                    class="px-4 py-2 border border-slate-200 hover:bg-slate-100 text-xs font-bold text-slate-600 rounded-lg transition-all"
                >
                    Cancel
                </button>
                <button 
                    @click="save"
                    :disabled="isSubmitting"
                    class="px-5 py-2 bg-[#0064d2] hover:bg-[#0057b8] text-white text-xs font-bold rounded-lg transition-all flex items-center gap-1.5 shadow-sm"
                >
                    <span v-if="isSubmitting" class="w-3.5 h-3.5 border-2 border-white border-t-transparent rounded-full animate-spin"></span>
                    Save Schedule
                </button>
            </div>
        </div>
    </div>
</template>
