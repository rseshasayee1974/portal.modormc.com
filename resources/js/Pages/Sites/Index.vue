<script setup lang="ts">
import AppLayout from '@/Layouts/AppLayout.vue';
import { Head, useForm, router } from '@inertiajs/vue3';
import { ref, computed, watch } from 'vue';
import Swal from 'sweetalert2';
import Toast from 'primevue/toast';
import { useToast } from 'primevue/usetoast';

import ModuleSubTopNav from '@/Navigation/ModuleSubTopNav.vue';
import SiteCreateForm from './components/SiteCreateForm.vue';
import SiteIndexList from './components/SiteIndexList.vue';
import { 
    MapPinIcon, 
    BuildingOffice2Icon, 
    CheckCircleIcon, 
    UserGroupIcon 
} from '@heroicons/vue/24/outline';

const props = defineProps<{
    sites: any[];
    filters: any;
    plants: any[];
    siteTypes: string[];
    isPrivileged: boolean;
    patrons: any[];
}>();

const toast = useToast();

const searchQuery = ref(props.filters?.search || '');
const editingId = ref<number | null>(null);
const expandedRows = ref<Record<number, boolean>>({});

const blankForm = () => ({
    plant_id: props.plants?.[0]?.id || null,
    patron_id: [] as number[],
    name: '',
    site_address_1: '',
    site_address_2: '',
    city: '',
    district: '',
    state: '',
    country: '',
    zipcode: '',
    code: '',
    type: props.isPrivileged ? (props.siteTypes?.[0] || '') : 'unloading',
    is_restricted: false,
    latitude: '',
    longitude: '',
    status: 'Active',
});

const createForm = useForm(blankForm());
const editForm = useForm(blankForm());

const resetCreateForm = () => {
    createForm.reset();
    createForm.clearErrors();
};

const resetEditForm = () => {
    editingId.value = null;
    expandedRows.value = {};
    editForm.reset();
    editForm.clearErrors();
};

const populateSiteForm = (form: any, site: any) => {
    form.plant_id = site.plant_id;
    form.patron_id = Array.isArray(site.patron_id) ? [...site.patron_id] : (site.patron_id ? [site.patron_id] : []);
    form.name = site.name;
    form.site_address_1 = site.site_address_1 || '';
    form.site_address_2 = site.site_address_2 || '';
    form.city = site.city || '';
    form.district = site.district || '';
    form.state = site.state || '';
    form.country = site.country || '';
    form.zipcode = site.zipcode || '';
    form.code = site.code || '';
    form.type = site.type;
    form.is_restricted = Boolean(site.is_restricted);
    form.latitude = site.latitude || '';
    form.longitude = site.longitude || '';
    form.status = site.status || 'Active';
};

// ── Handlers ──────────────────────────────────────────────────────────────

const submitCreate = () => {
    const isDuplicate = props.sites.some((site: any) => 
        site.name.toLowerCase() === createForm.name.toLowerCase() && 
        site.type === createForm.type && 
        site.plant_id === createForm.plant_id
    );

    if (isDuplicate) {
        createForm.setError('name', 'A site with this name already exists for the selected plant and type.');
        toast.add({
            severity: 'error',
            summary: 'Duplicate Site',
            detail: 'A site with this name already exists for the selected plant and type.',
            life: 3000
        });
        return;
    }

    createForm.post(route('sites.store'), {
        preserveScroll: true,
        onSuccess: () => {
            resetCreateForm();
            toast.add({ 
                severity: 'success', 
                summary: 'Site Registered', 
                detail: 'Logistic node has been successfully created.', 
                life: 1500 
            });
        },
    });
};

const submitEdit = () => {
    if (!editingId.value) return;
    
    const isDuplicate = props.sites.some((site: any) => 
        site.id !== editingId.value &&
        site.name.toLowerCase() === editForm.name.toLowerCase() && 
        site.type === editForm.type && 
        site.plant_id === editForm.plant_id
    );

    if (isDuplicate) {
        editForm.setError('name', 'A site with this name already exists for the selected plant and type.');
        toast.add({
            severity: 'error',
            summary: 'Duplicate Site',
            detail: 'A site with this name already exists for the selected plant and type.',
            life: 3000
        });
        return;
    }

    editForm.put(route('sites.update', editingId.value), {
        preserveScroll: true,
        onSuccess: () => {
            resetEditForm();
            toast.add({ 
                severity: 'success', 
                summary: 'Site Updated', 
                detail: 'Logistic node information matches the master directory.', 
                life: 1500 
            });
        },
    });
};

const deleteSite = (id: number) => {
    Swal.fire({
        title: 'Delete Site?',
        text: 'This action is irreversible and may affect linked logistics data.',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#ef4444',
        cancelButtonColor: '#64748b',
        confirmButtonText: 'Yes, delete permanently',
        cancelButtonText: 'Cancel'
    }).then((result) => {
        if (result.isConfirmed) {
            router.delete(route('sites.destroy', id), {
                preserveScroll: true,
                onSuccess: () => {
                    toast.add({ 
                        severity: 'info', 
                        summary: 'Deleted', 
                        detail: 'Site record has been purged successfully.', 
                        life: 1500 
                    });
                }
            });
        }
    });
};

const handleExpandedRowsUpdate = (newExpandedRows: any) => {
    const siteId = Object.keys(newExpandedRows).map(Number).find(id => !expandedRows.value[id]);
    
    if (siteId) {
        const site = props.sites.find((s: any) => s.id === siteId);
        if (site) {
            editingId.value = siteId;
            editForm.clearErrors();
            populateSiteForm(editForm, site);
            expandedRows.value = { [siteId]: true };
        }
    } else {
        editingId.value = null;
        expandedRows.value = {};
    }
};

const filteredSites = computed(() => {
    if (!searchQuery.value) return props.sites;
    const q = searchQuery.value.toLowerCase();
    return props.sites.filter((s: any) => 
        (s.name?.toLowerCase().includes(q)) || 
        (s.code?.toLowerCase().includes(q)) ||
        (s.type?.toLowerCase().includes(q)) ||
        (s.site_address_1?.toLowerCase().includes(q)) ||
        (s.city?.toLowerCase().includes(q)) ||
        (s.state?.toLowerCase().includes(q)) ||
        (s.patron?.legal_name?.toLowerCase().includes(q))
    );
});

// Auto-generate code based on name for new records
watch(() => createForm.name, (newName) => {
    if (newName && !createForm.code) {
        const prefix = newName.trim().charAt(0).toUpperCase();
        if (prefix) {
            let nextNum = props.sites.length + 1;
            
            const existingNums = props.sites
                .map(s => {
                    const match = s.code?.match(/\d+$/);
                    return match ? parseInt(match[0]) : 0;
                })
                .filter(n => n > 0);
            
            if (existingNums.length > 0) {
                nextNum = Math.max(...existingNums) + 1;
            }

            const sequence = nextNum.toString().padStart(3, '0');
            createForm.code = `${prefix}-${sequence}`;
        }
    }
});

// Executive KPI metrics computed from master site list
const metrics = computed(() => {
    const list = props.sites || [];
    const total = list.length;
    const active = list.filter((s: any) => s.status === 'Active' || !s.status).length;
    const unloading = list.filter((s: any) => s.type === 'unloading').length;
    const customerLinked = list.filter((s: any) => (s.patron_id && s.patron_id.length > 0) || s.patron).length;

    return { total, active, unloading, customerLinked };
});
</script>

<template>
    <AppLayout title="Logistic Nodes Directory">
        <template #header>
            <ModuleSubTopNav />
        </template>

        <Toast />
        <Head title="Logistic Sites | Site Management" />

        <main class="w-full space-y-6">
            
            <!-- EXECUTIVE KPI STATS CARDS -->
            <div class="grid grid-cols-2 sm:grid-cols-4 gap-3">
                
                <!-- Total Sites -->
                <div class="bg-white dark:bg-slate-800 rounded-xl p-3.5 border border-slate-200 dark:border-slate-700 shadow-xs flex items-center justify-between">
                    <div>
                        <span class="text-[10px] font-bold uppercase tracking-wider text-slate-400 dark:text-slate-500 block">Total Sites</span>
                        <div class="mt-0.5 flex items-baseline gap-1.5">
                            <span class="text-xl font-black text-slate-900 dark:text-slate-100">{{ metrics.total }}</span>
                            <span class="text-[10px] text-slate-400 font-medium">Nodes</span>
                        </div>
                    </div>
                    <div class="w-9 h-9 rounded-xl bg-indigo-50 dark:bg-indigo-950/60 text-indigo-600 dark:text-indigo-400 flex items-center justify-center">
                        <MapPinIcon class="w-5 h-5" />
                    </div>
                </div>

                <!-- Active Nodes -->
                <div class="bg-white dark:bg-slate-800 rounded-xl p-3.5 border border-slate-200 dark:border-slate-700 shadow-xs flex items-center justify-between">
                    <div>
                        <span class="text-[10px] font-bold uppercase tracking-wider text-emerald-600 dark:text-emerald-400 block">Active Nodes</span>
                        <div class="mt-0.5 flex items-baseline gap-1.5">
                            <span class="text-xl font-black text-emerald-600 dark:text-emerald-400">{{ metrics.active }}</span>
                            <span class="text-[10px] text-emerald-600/70 font-medium">Online</span>
                        </div>
                    </div>
                    <div class="w-9 h-9 rounded-xl bg-emerald-50 dark:bg-emerald-950/60 text-emerald-600 dark:text-emerald-400 flex items-center justify-center">
                        <CheckCircleIcon class="w-5 h-5" />
                    </div>
                </div>

                <!-- Delivery Sites (Unloading) -->
                <div class="bg-white dark:bg-slate-800 rounded-xl p-3.5 border border-slate-200 dark:border-slate-700 shadow-xs flex items-center justify-between">
                    <div>
                        <span class="text-[10px] font-bold uppercase tracking-wider text-indigo-600 dark:text-indigo-400 block">Delivery Bays</span>
                        <div class="mt-0.5 flex items-baseline gap-1.5">
                            <span class="text-xl font-black text-indigo-600 dark:text-indigo-400">{{ metrics.unloading }}</span>
                            <span class="text-[10px] text-indigo-600/70 font-medium">Unloading</span>
                        </div>
                    </div>
                    <div class="w-9 h-9 rounded-xl bg-blue-50 dark:bg-blue-950/60 text-blue-600 dark:text-blue-400 flex items-center justify-center">
                        <BuildingOffice2Icon class="w-5 h-5" />
                    </div>
                </div>

                <!-- Customer Linked -->
                <div class="bg-white dark:bg-slate-800 rounded-xl p-3.5 border border-slate-200 dark:border-slate-700 shadow-xs flex items-center justify-between">
                    <div>
                        <span class="text-[10px] font-bold uppercase tracking-wider text-purple-600 dark:text-purple-400 block">Dedicated Sites</span>
                        <div class="mt-0.5 flex items-baseline gap-1.5">
                            <span class="text-xl font-black text-purple-600 dark:text-purple-400">{{ metrics.customerLinked }}</span>
                            <span class="text-[10px] text-purple-600/70 font-medium">Customer-tied</span>
                        </div>
                    </div>
                    <div class="w-9 h-9 rounded-xl bg-purple-50 dark:bg-purple-950/60 text-purple-600 dark:text-purple-400 flex items-center justify-center">
                        <UserGroupIcon class="w-5 h-5" />
                    </div>
                </div>

            </div>

            <!-- TOP: CREATE FORM SECTION -->
            <section class="w-full">
                <SiteCreateForm 
                    :form="createForm" 
                    :plants="plants"
                    :site-types="siteTypes"
                    :is-privileged="isPrivileged"
                    :patrons="patrons"
                    @save="submitCreate"
                    @reset="resetCreateForm"
                />
            </section>

            <!-- DIRECTORY LIST SECTION -->
            <section class="w-full">
                <SiteIndexList 
                    :sites="filteredSites"
                    :search-query="searchQuery"
                    :expanded-rows="expandedRows"
                    :editing-id="editingId"
                    :edit-form="editForm"
                    :plants="plants"
                    :site-types="siteTypes"
                    :is-privileged="isPrivileged"
                    :errors="editForm.errors"
                    :processing="editForm.processing"
                    :patrons="patrons"
                    @update:search-query="searchQuery = $event"
                    @update:expanded-rows="handleExpandedRowsUpdate"
                    @delete="deleteSite"
                    @submit-edit="submitEdit"
                    @cancel-edit="resetEditForm"
                />
            </section>

        </main>
    </AppLayout>
</template>

<style>
.p-toast {
    @apply !opacity-100;
}
.p-toast-message {
    @apply !rounded-2xl !border-none !shadow-2xl;
}
</style>
