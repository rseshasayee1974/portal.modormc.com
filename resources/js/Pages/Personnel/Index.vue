<script setup lang="ts">
import AppLayout from '@/Layouts/AppLayout.vue';
import ModuleSubTopNav from '@/Navigation/ModuleSubTopNav.vue';
import { useForm, usePage } from '@inertiajs/vue3';
import { ref, computed, onMounted, reactive, watch } from 'vue';
import Swal from 'sweetalert2';
import { 
    PencilSquareIcon, 
    TrashIcon,
    MagnifyingGlassIcon,
    ListBulletIcon
} from '@heroicons/vue/24/outline';
import PersonnelForm from './components/PersonnelForm.vue';
import PersonnelEditForm from './components/PersonnelEditForm.vue';

// PrimeVue
import BaseDataTable from '@/Components/Base/BaseDataTable.vue';
import Column from 'primevue/column';
import BaseInput from '@/Components/Base/BaseInput.vue';
import BaseButton from '@/Components/Base/BaseButton.vue';
import Tag from 'primevue/tag';

const page = usePage();

interface Contact {
    contact_id?: string;
    employee_id?: number;
    contact_type: string;
    contact_value: string;
    is_primary: boolean;
}

interface Patron {
    id: number;
    legal_name: string;
}

interface Personnel {
    id: number;
    first_name: string;
    last_name: string | null;
    employee_type: string | null;
    gender: string | null;
    date_of_birth: string | null;
    joining_date: string | null;
    status: string;
    contacts: Contact[];
    patrons: Patron[];
}

const props = defineProps<{
    personnel: Personnel[];
    patrons: Patron[];
    employeeTypes: string[];
    genders: string[];
    statuses: string[];
    contactTypes: string[];
    patronOptions?: any[]; // For backward compatibility
}>();

const editingId = ref<number | null>(null);
const searchQuery = ref('');
const activeTabCreate = ref('details');
const activeTabEdit = ref('details');
const isMounted = ref(false);
const expandedRows = ref<any[]>([]);

onMounted(() => {
    isMounted.value = true;
});

const filteredPersonnel = computed(() => {
    if (!searchQuery.value) return props.personnel;
    const q = searchQuery.value.toLowerCase();
    return props.personnel.filter((p: Personnel) => 
        p.first_name.toLowerCase().includes(q) ||
        (p.last_name && p.last_name.toLowerCase().includes(q)) ||
        (p.employee_type && p.employee_type.toLowerCase().includes(q))
    );
});

const getInitialForm = () => ({
    first_name: '',
    last_name: '',
    employee_type: null as string | null,
    gender: null as string | null,
    date_of_birth: null as string | null,
    joining_date: null as string | null,
    status: 'active',
    contacts: [] as Contact[],
    patron_ids: [] as number[],
});

const createForm = useForm(getInitialForm());
const editForm = useForm(getInitialForm());

const resetEditForm = () => {
    editingId.value = null;
    expandedRows.value = [];
    editForm.reset();
    editForm.clearErrors();
    activeTabEdit.value = 'details';
};

const editPersonnel = (p: Personnel) => {
    editingId.value = p.id;
    editForm.id = p.id;
    editForm.first_name = p.first_name;
    editForm.last_name = p.last_name || '';
    editForm.employee_type = p.employee_type;
    editForm.gender = p.gender;
    editForm.date_of_birth = p.date_of_birth;
    editForm.joining_date = p.joining_date;
    editForm.status = p.status;
    editForm.contacts = JSON.parse(JSON.stringify(p.contacts));
    editForm.patron_ids = p.patrons.map(patron => patron.id);
};

const submitCreate = () => {
    createForm.post(route('personnel.store'), {
        onSuccess: () => {
            createForm.reset();
            createForm.clearErrors();
            activeTabCreate.value = 'details';
        },
    });
};

const submitEdit = () => {
    if (editingId.value) {
        editForm.put(route('personnel.update', editingId.value), {
            onSuccess: () => resetEditForm(),
        });
    }
};

const deletePersonnel = (id: number) => {
    Swal.fire({
        title: 'Are you sure?',
        text: "You won't be able to revert this!",
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#4f46e5',
        cancelButtonColor: '#ef4444',
        confirmButtonText: 'Yes, delete it!'
    }).then((result) => {
        if (result.isConfirmed) {
            createForm.delete(route('personnel.destroy', id), {
                onSuccess: () => Swal.fire('Deleted!', 'Personnel record has been deleted.', 'success')
            });
        }
    });
};

const addContact = (f: any) => {
    f.contacts.push({
        contact_type: '',
        contact_value: '',
        is_primary: f.contacts.length === 0
    });
};

const removeContact = (f: any, index: number) => {
    f.contacts.splice(index, 1);
};

const employeeTypeOptions = computed(() => (props.employeeTypes || []).map(t => ({ label: t, value: t })));
const genderOptions = computed(() => (props.genders || []).map(t => ({ label: t, value: t })));
const statusOptions = computed(() => (props.statuses || []).map(t => ({ label: t.toUpperCase(), value: t })));
const contactTypeOptions = computed(() => (props.contactTypes || []).map(t => ({ label: t, value: t })));
const patronOptions = computed(() => (props.patrons || []).map(p => ({ label: p.legal_name, value: p.id })));

// Watch for flash messages
watch(
    () => page.props.flash,
    (flash: any) => {
        if (flash?.success) {
            Swal.fire({
                toast: true,
                position: 'top-end',
                showConfirmButton: false,
                timer: 3000,
                timerProgressBar: true,
                icon: 'success',
                title: flash.success
            });
        }
    },
    { immediate: true, deep: true }
);

</script>

<template>
    <AppLayout title="Personnel Management">
         <template #header>
            <ModuleSubTopNav />
        </template>

        <div class="py-6">
            <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
                <div class="space-y-8">
                    
                    <!-- Creation Form Container -->
                    <div id="top-form-container">
                        <PersonnelForm 
                            :form="createForm"
                            v-model:activeTab="activeTabCreate"
                            :employeeTypeOptions="employeeTypeOptions"
                            :genderOptions="genderOptions"
                            :statusOptions="statusOptions"
                            :contactTypeOptions="contactTypeOptions"
                            :patronOptions="patronOptions"
                            :resetForm="() => { activeTabCreate = 'details'; createForm.reset(); }"
                            :addContact="() => addContact(createForm)"
                            :removeContact="(index: number) => removeContact(createForm, index)"
                            :submit="submitCreate"
                        />
                    </div>

                    <!-- Personnel Directory -->
                    <div class="bg-white dark:bg-slate-900  rounded-xl">
                        <!-- <div class="flex flex-col md:flex-row justify-between items-center mb-10 gap-6">
                            <div class="flex items-center gap-3">
                                <ListBulletIcon class="w-7 h-7 text-indigo-500" />
                                <h3 class="text-2xl font-black text-gray-800 dark:text-gray-100 tracking-tighter uppercase leading-none">Personnel Directory</h3>
                            </div>
                            <div class="relative w-full md:w-96">
                                <span class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                    <MagnifyingGlassIcon class="h-5 w-5 text-gray-400" />
                                </span>
                                <BaseInput v-model="searchQuery" placeholder="Search Personnel..." class="w-full pl-10 rounded-full" />
                            </div>
                        </div> -->

                        <BaseDataTable 
                            :value="filteredPersonnel" 
                            v-model:expandedRows="expandedRows"
                            dataKey="id"
                            stripedRows 
                            heading="Personnel Directory"
                            headingIcon="UserGroupIcon"
                            showSearch showSerial
                            paginator
                            :rows="10" 
                            :totalRecords="filteredPersonnel.length"
                            class="p-datatable-sm"
                            showExport
                            exportFilename="personnel-directory-report"
                        >
                            <template #toolbar>
                                <div class="flex items-center gap-2 px-3 py-1 bg-slate-50 rounded-lg border border-slate-100">
                                    <span class="text-[10px] font-black text-slate-400 uppercase tracking-widest">{{ filteredPersonnel.length }} total personnel</span>
                                </div>
                            </template>
                            <!-- <Column expander style="width: 3rem" /> -->
                            <Column header="Name">
                                <template #body="slotProps">
                                    <div class="font-bold text-indigo-600 dark:text-indigo-400">
                                        {{ slotProps.data.first_name }} {{ slotProps.data.last_name || '' }}
                                    </div>
                                </template>
                            </Column>
                            <Column header="Type / Status">
                                <template #body="slotProps">
                                    <div class="flex flex-col gap-1">
                                        <div class="text-sm font-semibold">{{ slotProps.data.employee_type || '-' }}</div>
                                        <Tag 
                                            :severity="slotProps.data.status === 'active' ? 'success' : 'danger'" 
                                            :value="slotProps.data.status.toUpperCase()"
                                            rounded
                                            class="w-fit"
                                        />
                                    </div>
                                </template>
                            </Column>
                            <Column header="Contacts">
                                <template #body="slotProps">
                                    <Tag severity="info" :value="`${slotProps.data.contacts?.length || 0} Contacts`" rounded />
                                </template>
                            </Column>
                            <Column header="Patrons">
                                <template #body="slotProps">
                                    <Tag severity="warn" :value="`${slotProps.data.patrons?.length || 0} Linked Patrons`" rounded />
                                </template>
                            </Column>
                            <Column header="Actions" alignFrozen="right" frozen>
                                <template #body="slotProps">
                                    <div class="flex justify-end gap-2">
                                        <BaseButton 
                                            icon="pi pi-pencil" 
                                            severity="info" 
                                            text 
                                            rounded 
                                            @click="editPersonnel(slotProps.data); expandedRows = [slotProps.data]"
                                        />
                                        <BaseButton 
                                            icon="pi pi-trash" 
                                            severity="danger" 
                                            text 
                                            rounded 
                                            @click="deletePersonnel(slotProps.data.id)"
                                        />
                                    </div>
                                </template>
                            </Column>
                            <template #expansion="slotProps">
                                <div class="p-4 border rounded-xl bg-gray-50/50 dark:bg-slate-800/50">
                                    <PersonnelEditForm 
                                        :form="editForm"
                                        :personnelId="slotProps.data.id"
                                        :activeTab="activeTabEdit"
                                        @update:activeTab="(val: string) => activeTabEdit = val"
                                        :employeeTypeOptions="employeeTypeOptions"
                                        :genderOptions="genderOptions"
                                        :statusOptions="statusOptions"
                                        :contactTypeOptions="contactTypeOptions"
                                        :patronOptions="patronOptions"
                                        :resetForm="resetEditForm"
                                        :addContact="() => addContact(editForm)"
                                        :removeContact="(index: number) => removeContact(editForm, index)"
                                        :submit="submitEdit"
                                    />
                                </div>
                            </template>
                        </BaseDataTable>
                    </div>
                </div>
            </div>
        </div>
    </AppLayout>
</template>

<style scoped>
:deep(.p-datatable-thead > tr > th) {
    @apply bg-gray-50 dark:bg-gray-700/50 text-gray-600 dark:text-gray-300 font-bold uppercase text-[10px] tracking-wider py-4;
}
</style>


