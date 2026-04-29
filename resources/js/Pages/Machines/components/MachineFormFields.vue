<script setup lang="ts">
import { ref } from 'vue';
import BaseInput from '@/Components/Base/BaseInput.vue';
import BaseSelect from '@/Components/Base/BaseSelect.vue';
import DatePicker from 'primevue/datepicker';
import BaseInputNumber from '@/Components/Base/BaseInputNumber.vue';
import { 
    PlusIcon, TrashIcon, DocumentChartBarIcon, 
    BanknotesIcon, Cog6ToothIcon
} from '@heroicons/vue/24/outline';

const props = defineProps<{
    form: any;
    vehicleOptions: any[];
    docTypeOptions: any[];
    transportOwnerOptions: any[];
    addDocument: () => void;
    removeDocument: (index: number) => void;
    addLoan: () => void;
    removeLoan: (index: number) => void;
}>();
console.log(props.transportOwnerOptions);
const activeTab = ref<'specs' | 'compliance' | 'finance'>('specs');
</script>

<template>
    <div class="flex flex-col gap-6">
        <!-- Navigation Pills -->
        <div class="flex items-center gap-1 bg-slate-100 rounded-sm p-1 w-fit border border-slate-200">
            <button
                v-for="tab in [
                    { key: 'specs', label: 'Specifications', icon: Cog6ToothIcon },
                    { key: 'compliance', label: 'Compliance', icon: DocumentChartBarIcon },
                    { key: 'finance', label: 'Financials', icon: BanknotesIcon },
                ]"
                :key="tab.key"
                type="button"
                @click="activeTab = tab.key as any"
                :class="[
                    'flex items-center gap-2 px-5 py-2 text-[10px] font-black uppercase tracking-widest rounded-sm transition-all duration-200',
                    activeTab === tab.key
                        ? 'bg-white text-indigo-600 shadow-sm border border-slate-200'
                        : 'text-slate-500 hover:text-slate-700 hover:bg-slate-200/50'
                ]"
            >
                <component :is="tab.icon" class="w-3 h-3" />
                {{ tab.label }}
            </button>
        </div>

        <!-- Form Content Sections -->
        <div class="form-content">
            <!-- Section: Specifications -->
            <div v-show="activeTab === 'specs'" class="grid grid-cols-1 md:grid-cols-12 gap-5 animate-in fade-in slide-in-from-top-1">
                <div class="col-span-12 md:col-span-3 field-group">
                    <label class="field-label">Classification</label>
                    <BaseSelect 
                        v-model="form.vehicle_type" 
                        :options="vehicleOptions" 
                        optionLabel="label" 
                        optionValue="value" 
                        placeholder="Select machine type" 
                    />
                </div>
                <div class="col-span-12 md:col-span-3 field-group">
                    <BaseInput 
                        v-model="form.registration" 
                        label="Registration ID *"
                        placeholder="E.g. UP 15 AH 1234"
                        :error="form.errors.registration"
                    />
                </div>
                <div class="col-span-12 md:col-span-3 field-group">
                    <BaseInput 
                        v-model="form.vehicle_model" 
                        label="Model Details"
                        placeholder="Ashok Leyland 2518"
                    />
                </div>

                <div class="col-span-12 md:col-span-3 field-group">
                    <label class="field-label">Owner / Transporter</label>
                    <BaseSelect 
                        v-model="form.owner_id" 
                        :options="transportOwnerOptions" 
                        optionLabel="label" 
                        optionValue="value" 
                        placeholder="Select owner" 
                    />
                </div>

                <div class="col-span-6 md:col-span-3 field-group">
                    <label class="field-label">Make Year</label>
                    <BaseInputNumber v-model="form.make_year" :useGrouping="false" placeholder="2024" />
                </div>

                <div class="col-span-6 md:col-span-3 field-group">
                    <label class="field-label">Capacity (Tons)</label>
                    <BaseInputNumber v-model="form.capacity" placeholder="25" />
                </div>

                <div class="col-span-12 md:col-span-3 field-group">
                    <BaseInput 
                        v-model="form.engine_no" 
                        label="Engine Number"
                        placeholder="Enter engine record"
                    />
                </div>

                <div class="col-span-12 md:col-span-3 field-group">
                    <BaseInput 
                        v-model="form.chassis_no" 
                        label="Chassis Number"
                        placeholder="Enter chassis/serial number"
                    />
                </div>
            </div>

            <!-- Section: Compliance Documents -->
            <div v-show="activeTab === 'compliance'" class="flex flex-col gap-4 animate-in fade-in slide-in-from-top-1">
                <div v-if="form.documents.length === 0" class="py-12 border-2 border-dashed border-slate-100 rounded-xl flex flex-col items-center justify-center bg-slate-50/30">
                    <DocumentChartBarIcon class="w-10 h-10 text-slate-200 mb-2" />
                    <p class="text-[10px] font-black text-slate-400 uppercase tracking-widest">No Documents Registered</p>
                    <button type="button" @click="addDocument" class="mt-3 px-6 py-2 bg-indigo-600 text-white rounded-lg text-[9px] font-black uppercase tracking-widest">Add First Doc</button>
                </div>
                
                <div v-for="(doc, index) in form.documents" :key="index" class="p-5 bg-white border border-slate-100 rounded-xl group hover:border-indigo-100 transition-all relative">
                    <button type="button" @click="removeDocument(index)" class="absolute top-4 right-4 text-slate-300 hover:text-red-500 transition-colors">
                        <TrashIcon class="w-3.5 h-3.5" />
                    </button>
                    <div class="grid grid-cols-12 gap-5">
                        <div class="col-span-12 md:col-span-4 field-group">
                            <label class="field-label">Doc Type</label>
                            <BaseSelect v-model="doc.type" :options="docTypeOptions" optionLabel="label" optionValue="value" />
                        </div>
                        <div class="col-span-6 md:col-span-4 field-group">
                            <label class="field-label">Issue Date</label>
                            <DatePicker v-model="doc.issue_date" dateFormat="yy-mm-dd" class="!w-full h-10" />
                        </div>
                        <div class="col-span-6 md:col-span-4 field-group">
                            <label class="field-label">Expiry Date</label>
                            <DatePicker v-model="doc.expiry_date" dateFormat="yy-mm-dd" class="!w-full h-10" />
                        </div>
                    </div>
                </div>
                
                <button v-if="form.documents.length > 0" type="button" @click="addDocument" class="flex items-center gap-2 text-[10px] font-black text-indigo-600 uppercase tracking-widest self-center py-2 px-4 hover:bg-indigo-50 rounded-lg transition-colors">
                    <PlusIcon class="w-3.5 h-3.5 stroke-[3px]" /> Append More Records
                </button>
            </div>

            <!-- Section: Financials -->
            <div v-show="activeTab === 'finance'" class="flex flex-col gap-4 animate-in fade-in slide-in-from-top-1">
                <div v-if="form.loans.length === 0" class="py-12 border-2 border-dashed border-indigo-50/50 rounded-xl flex flex-col items-center justify-center bg-indigo-50/10">
                    <BanknotesIcon class="w-12 h-12 text-indigo-100 mb-2" />
                    <p class="text-[10px] font-black text-slate-400 uppercase tracking-widest">Financial Tracking Not Active</p>
                    <button type="button" @click="addLoan" class="mt-4 px-8 py-2.5 bg-white border border-indigo-100 rounded-lg text-[9px] font-black uppercase tracking-widest text-indigo-600 hover:bg-indigo-600 hover:text-white transition-all">Initialize Financing</button>
                </div>

                <div v-for="(loan, index) in form.loans" :key="index" class="p-6 border border-slate-100 bg-white rounded-xl relative group hover:border-indigo-100 transition-all">
                    <button type="button" @click="removeLoan(index)" class="absolute top-4 right-4 text-slate-300 hover:text-red-500 transition-colors">
                        <TrashIcon class="w-4 h-4" />
                    </button>
                    <div class="grid grid-cols-12 gap-5">
                        <div class="col-span-12 md:col-span-4 field-group">
                            <label class="field-label">Principal Amount</label>
                            <BaseInputNumber v-model="loan.loan_amount" mode="currency" currency="INR" locale="en-IN" class="!w-full h-10" />
                        </div>
                        <div class="col-span-12 md:col-span-4 field-group">
                            <label class="field-label">Monthly EMI</label>
                            <BaseInputNumber v-model="loan.emi_amount" mode="currency" currency="INR" locale="en-IN" class="!w-full h-10" />
                        </div>
                        <div class="col-span-12 md:col-span-4 field-group">
                            <label class="field-label">Tenure (Months)</label>
                            <BaseInputNumber v-model="loan.tenure_months" placeholder="36" class="!w-full h-10" />
                        </div>
                        <div class="col-span-12 md:col-span-6 field-group">
                            <label class="field-label">Start Date</label>
                            <DatePicker v-model="loan.start_date" dateFormat="yy-mm-dd" class="!w-full h-10" />
                        </div>
                        <div class="col-span-12 md:col-span-6 field-group">
                            <label class="field-label">End Date</label>
                            <DatePicker v-model="loan.end_date" dateFormat="yy-mm-dd" class="!w-full h-10" />
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>

<style scoped>
:deep(.p-datepicker-input) {
    @apply h-10 text-sm font-bold border-slate-200 rounded-md !bg-white;
}
</style>

<style scoped>
:deep(.p-select) {
    @apply !bg-white dark:!bg-slate-900;
}
:deep(.p-inputtext) {
    @apply !bg-white dark:!bg-slate-900;
}
</style>
