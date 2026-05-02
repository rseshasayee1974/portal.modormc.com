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

const props = defineProps<{
    sites: any[];
    filters: any;
    plants: any[];
    siteTypes: string[];
    isPrivileged: boolean;
}>();

const toast = useToast();

const searchQuery = ref(props.filters?.search || '');
const editingId = ref<number | null>(null);
const expandedRows = ref<Record<number, boolean>>({});

const blankForm = () => ({
    plant_id: props.plants?.[0]?.id || null,
    name: '',
    site_address_1: '',
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
    form.name = site.name;
    form.site_address_1 = site.site_address_1 || '';
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
    createForm.post(route('sites.store'), {
        preserveScroll: true,
        onSuccess: () => {
            resetCreateForm();
            toast.add({ 
                severity: 'success', 
                summary: 'Site Registered', 
                detail: 'Logistic node has been successfully created.', 
                life: 3000 
            });
        },
    });
};

const submitEdit = () => {
    if (!editingId.value) return;
    
    editForm.put(route('sites.update', editingId.value), {
        preserveScroll: true,
        onSuccess: () => {
            const sid = editingId.value;
            resetEditForm();
            toast.add({ 
                severity: 'success', 
                summary: 'Site Updated', 
                detail: 'Logistic node information matches the master directory.', 
                life: 3000 
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
                        life: 3000 
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
        (s.code?.toLowerCase().includes(q))
    );
});

// Auto-generate code based on name for new records
watch(() => createForm.name, (newName) => {
    // Only auto-generate if name exists and code is currently empty
    if (newName && !createForm.code) {
        const prefix = newName.trim().charAt(0).toUpperCase();
        if (prefix) {
            // Find the highest existing number in codes to ensure true increment, 
            // or fallback to sites length + 1 if format doesn't match
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

// No server-side handlers needed for client-side search.
</script>

<template>
    <AppLayout title="Logistic Nodes Directory">
        <template #header>
            <ModuleSubTopNav />
        </template>

        <Toast />
        <Head title="Logistic Sites | Site Management" />

        <main class="max-w-full mx-auto p-2 space-y-6 animate-in fade-in duration-700">
            <!-- TOP: CREATE FORM SECTION -->
            <section class="max-w-7xl mx-auto w-full">
                <SiteCreateForm 
                    :form="createForm" 
                    :plants="plants"
                    :site-types="siteTypes"
                    :is-privileged="isPrivileged"
                    @save="submitCreate"
                    @reset="resetCreateForm"
                />
            </section>

            <section>
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
/* Global PrimeVue Overrides for Premium Luk */
.p-toast {
    @apply !opacity-100;
}
.p-toast-message {
    @apply !rounded-2xl !border-none !shadow-2xl;
}
</style>
