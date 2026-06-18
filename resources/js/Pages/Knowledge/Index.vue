<script setup lang="ts">
import AppLayout from '@/Layouts/AppLayout.vue';
import ModuleSubTopNav from '@/Navigation/ModuleSubTopNav.vue';
import { ref, computed } from 'vue';
import { useForm, router } from '@inertiajs/vue3';
import Swal from 'sweetalert2';
import { useToast } from 'primevue/usetoast';
import KnowledgeBaseManager from '@/Components/AI/KnowledgeBaseManager.vue';
import ChatHistory from '@/Components/AI/ChatHistory.vue';

// Heroicons
import {
    BookOpenIcon,
    DocumentTextIcon,
    CheckCircleIcon,
    ServerStackIcon,
    TagIcon,
} from '@heroicons/vue/24/outline';

// PrimeVue
import BaseDataTable from '@/Components/Base/BaseDataTable.vue';
import Column from 'primevue/column';
import Button from 'primevue/button';
import Dialog from 'primevue/dialog';
import BaseInput from '@/Components/Base/BaseInput.vue';
import Textarea from 'primevue/textarea';
import BaseSelect from '@/Components/Base/BaseSelect.vue';
import Tag from 'primevue/tag';
import ToggleSwitch from 'primevue/toggleswitch';

interface Document {
    id: number;
    title: string;
    source_type: string;
    source_id: string | null;
    is_active: boolean;
    token_count: number;
    created_at: string;
}

const props = defineProps<{
    documents: Document[];
    sourceTypes: string[];
}>();

const toast = useToast();

// UI States
const showModal = ref(false);
const modalMode = ref<'create' | 'view'>('create');
const activeTab = ref<'text' | 'file'>('text');
const activePageTab = ref<'documents' | 'chat_logs'>('documents');

const onFileUploaded = (response: any) => {
    toast.add({ severity: 'success', summary: 'Uploaded', detail: response.message || 'File uploaded and chunked.' });
    closeModal();
    router.reload({ preserveState: false });
};

const onUploadError = (errorMsg: string) => {
    toast.add({ severity: 'error', summary: 'Upload Failed', detail: errorMsg });
};

// Client-Side DataTable Filters
const filters = ref({
    global: { value: null as string | null, matchMode: 'contains' },
    source_type: { value: null as string | null, matchMode: 'equals' },
});

const rows = computed(() => props.documents ?? []);

// Type selection options for local filtering
const typeFilterOptions = computed(() => {
    const list = props.sourceTypes.map(t => ({ label: t.toUpperCase(), value: t }));
    list.unshift({ label: 'ALL TYPES', value: null as any });
    return list;
});

// Options for creating a document
const typeOptions = [
    { label: 'FAQ (Frequently Asked Questions)', value: 'faq' },
    { label: 'SOP (Standard Operating Procedure)', value: 'sop' },
    { label: 'Product Specifications', value: 'product' },
    { label: 'Company Policy', value: 'policy' },
    { label: 'Email / Communication', value: 'email' },
    { label: 'Notes / Memo', value: 'notes' },
];

const form = useForm({
    title: '',
    source_type: 'faq',
    source_id: '',
    content: '',
});

const openCreateModal = () => {
    modalMode.value = 'create';
    activeTab.value = 'text';
    form.reset();
    form.clearErrors();
    showModal.value = true;
};

const closeModal = () => {
    showModal.value = false;
};

const submitForm = () => {
    form.post(route('knowledge.store'), {
        onSuccess: () => {
            toast.add({ severity: 'success', summary: 'Success', detail: 'Document indexed successfully' });
            closeModal();
        },
    });
};

const toggleActive = (doc: Document) => {
    router.patch(route('knowledge.toggle', doc.id), {}, {
        preserveScroll: true,
        onSuccess: () => {
            toast.add({ severity: 'success', summary: 'Updated', detail: 'Document status toggled' });
        },
    });
};

const reEmbed = (doc: Document) => {
    toast.add({ severity: 'info', summary: 'Processing', detail: 'Regenerating embedding...' });
    router.post(route('knowledge.re-embed', doc.id), {}, {
        preserveScroll: true,
        onSuccess: () => {
            toast.add({ severity: 'success', summary: 'Success', detail: 'Embedding regenerated successfully' });
        },
        onError: (errors) => {
            toast.add({ severity: 'error', summary: 'Error', detail: errors.error || 'Failed to regenerate embedding' });
        },
    });
};

const deleteDoc = (doc: Document) => {
    Swal.fire({
        title: 'Delete from Knowledge Base?',
        text: `Are you sure you want to delete "${doc.title}"? This will remove its search index.`,
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#fe0000',
        confirmButtonText: 'Yes, delete it!',
    }).then((result) => {
        if (result.isConfirmed) {
            router.delete(route('knowledge.destroy', doc.id), {
                preserveScroll: true,
                onSuccess: () => {
                    toast.add({ severity: 'success', summary: 'Deleted', detail: 'Document removed' });
                },
            });
        }
    });
};

const formatDate = (value: string): string => {
    if (!value) return 'N/A';
    return new Date(value).toLocaleString('en-IN', {
        day: '2-digit',
        month: 'short',
        year: 'numeric',
    });
};

const getSourceTypeColor = (type: string): string => {
    switch (type.toLowerCase()) {
        case 'faq': return 'success';
        case 'sop': return 'info';
        case 'product': return 'warn';
        case 'policy': return 'danger';
        default: return 'secondary';
    }
};
</script>

<template>
    <AppLayout title="Knowledge Base">
        <template #header>
            <ModuleSubTopNav />
        </template>

        <div class="py-6 max-w-[1600px] mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex flex-col md:flex-row md:items-center justify-between gap-4 mb-6">
                <div class="flex items-center gap-4">
                    <div class="p-3 bg-indigo-50 dark:bg-indigo-950/50 rounded-2xl border border-indigo-100 dark:border-indigo-900">
                        <BookOpenIcon class="w-6 h-6 text-indigo-600 dark:text-indigo-400" />
                    </div>
                    <div>
                        <p class="text-[10px] font-black uppercase tracking-[0.24em] text-slate-400">Retrieval-Augmented Generation (RAG)</p>
                        <h1 class="text-2xl font-black tracking-tight text-slate-900 dark:text-white">Knowledge Base</h1>
                    </div>
                </div>
            </div>

            <!-- Page Tab Switcher -->
            <div class="flex border-b border-slate-200 dark:border-gray-700 mb-6">
                <button
                    @click="activePageTab = 'documents'"
                    :class="['px-5 py-3 text-xs font-black uppercase tracking-widest border-b-2 transition-all', activePageTab === 'documents' ? 'border-indigo-600 text-indigo-600' : 'border-transparent text-slate-400 hover:text-slate-650']"
                >
                    Knowledge Documents
                </button>
                <button
                    @click="activePageTab = 'chat_logs'"
                    :class="['px-5 py-3 text-xs font-black uppercase tracking-widest border-b-2 transition-all', activePageTab === 'chat_logs' ? 'border-indigo-600 text-indigo-600' : 'border-transparent text-slate-400 hover:text-slate-650']"
                >
                    Customer Chat Logs
                </button>
            </div>

            <div v-if="activePageTab === 'documents'">
                <!-- Stats Block -->
                <div class="grid grid-cols-1 sm:grid-cols-3 gap-4 mb-6">
                <div class="bg-white dark:bg-gray-800 rounded-2xl border border-slate-200 dark:border-gray-700 p-5 flex items-center gap-4 shadow-sm">
                    <div class="w-12 h-12 rounded-xl bg-indigo-50 dark:bg-indigo-950 flex items-center justify-center border border-indigo-100 dark:border-indigo-900">
                        <ServerStackIcon class="w-6 h-6 text-indigo-600 dark:text-indigo-400" />
                    </div>
                    <div>
                        <p class="text-[10px] font-black uppercase tracking-widest text-slate-400">Total Documents</p>
                        <p class="text-2xl font-black text-slate-800 dark:text-white">{{ rows.length }}</p>
                    </div>
                </div>

                <div class="bg-white dark:bg-gray-800 rounded-2xl border border-slate-200 dark:border-gray-700 p-5 flex items-center gap-4 shadow-sm">
                    <div class="w-12 h-12 rounded-xl bg-emerald-50 dark:bg-emerald-950 flex items-center justify-center border border-emerald-100 dark:border-emerald-900">
                        <CheckCircleIcon class="w-6 h-6 text-emerald-600 dark:text-emerald-400" />
                    </div>
                    <div>
                        <p class="text-[10px] font-black uppercase tracking-widest text-slate-400">Active / Indexed</p>
                        <p class="text-2xl font-black text-slate-800 dark:text-white">
                            {{ rows.filter(r => r.is_active).length }}
                        </p>
                    </div>
                </div>

                <div class="bg-white dark:bg-gray-800 rounded-2xl border border-slate-200 dark:border-gray-700 p-5 flex items-center gap-4 shadow-sm">
                    <div class="w-12 h-12 rounded-xl bg-amber-50 dark:bg-amber-950 flex items-center justify-center border border-amber-100 dark:border-amber-900">
                        <TagIcon class="w-6 h-6 text-amber-600 dark:text-amber-400" />
                    </div>
                    <div>
                        <p class="text-[10px] font-black uppercase tracking-widest text-slate-400">Source Categories</p>
                        <p class="text-2xl font-black text-slate-800 dark:text-white">{{ props.sourceTypes.length }}</p>
                    </div>
                </div>
            </div>

            <!-- DataTable Block -->
            <div class="bg-white dark:bg-gray-800 rounded-2xl border border-slate-200 dark:border-gray-700 shadow-sm overflow-hidden">
                <BaseDataTable
                    :value="rows"
                    v-model:filters="filters"
                    :globalFilterFields="['title', 'source_type', 'content']"
                    showSearch
                    showSerial
                    heading="Document Index"
                    headingIcon="BookOpenIcon"
                    :rows="15"
                >
                    <template #toolbar>
                        <div class="flex items-center gap-3">
                            <BaseSelect v-model="filters.source_type.value" :options="typeFilterOptions" optionLabel="label" optionValue="value" placeholder="All Categories" class="w-48 shadow-sm" />
                            <Button label="Add Document" icon="pi pi-plus" size="small" class="p-button-raised" @click="openCreateModal" />
                        </div>
                    </template>

                    <Column field="title" header="Document Title" sortable>
                        <template #body="slotProps">
                            <div class="font-semibold text-slate-800 dark:text-slate-200 text-sm">
                                {{ slotProps.data.title }}
                            </div>
                            <div class="text-[10px] text-slate-400 dark:text-slate-500 font-mono">
                                ID: {{ slotProps.data.source_id || 'N/A' }}
                            </div>
                        </template>
                    </Column>

                    <Column field="source_type" header="Category" sortable style="width: 150px">
                        <template #body="slotProps">
                            <Tag :value="slotProps.data.source_type.toUpperCase()" :severity="getSourceTypeColor(slotProps.data.source_type)" />
                        </template>
                    </Column>

                    <Column field="token_count" header="Token Estimate" sortable style="width: 150px">
                        <template #body="slotProps">
                            <span class="bg-slate-100 dark:bg-slate-700 text-slate-700 dark:text-slate-300 px-2.5 py-1 rounded-full font-mono text-[10px]">
                                {{ slotProps.data.token_count }} tokens
                            </span>
                        </template>
                    </Column>

                    <Column field="is_active" header="Active Search Index" style="width: 150px">
                        <template #body="slotProps">
                            <div class="flex items-center gap-2">
                                <ToggleSwitch v-model="slotProps.data.is_active" @change="toggleActive(slotProps.data)" />
                                <span class="font-medium" :class="slotProps.data.is_active ? 'text-emerald-600 dark:text-emerald-400' : 'text-slate-400'">
                                    {{ slotProps.data.is_active ? 'Indexed' : 'Paused' }}
                                </span>
                            </div>
                        </template>
                    </Column>

                    <Column field="created_at" header="Indexed On" sortable style="width: 150px">
                        <template #body="slotProps">
                            <span class="text-slate-500 dark:text-slate-400 font-mono">{{ formatDate(slotProps.data.created_at) }}</span>
                        </template>
                    </Column>

                    <Column header="Actions" class="text-right" style="width: 150px">
                        <template #body="slotProps">
                            <div class="flex justify-end gap-2">
                                <Button icon="pi pi-refresh" text rounded v-tooltip.top="'Regenerate Embedding'" @click="reEmbed(slotProps.data)" severity="info" />
                                <Button icon="pi pi-trash" text rounded v-tooltip.top="'Delete Document'" @click="deleteDoc(slotProps.data)" severity="danger" />
                            </div>
                        </template>
                    </Column>

                    <template #empty>
                        <div class="p-8 text-center text-slate-500 dark:text-slate-400 flex flex-col items-center justify-center gap-2">
                            <DocumentTextIcon class="w-12 h-12 text-slate-300 dark:text-slate-600" />
                            <p class="font-semibold text-base">No Knowledge Documents Found</p>
                            <p class="text-xs">Add standard operating procedures, FAQ lists, or product specs to bootstrap the AI agent's RAG context.</p>
                        </div>
                    </template>
                </BaseDataTable>
            </div>
            </div> <!-- End of activePageTab === 'documents' -->

            <div v-else-if="activePageTab === 'chat_logs'">
                <ChatHistory />
            </div>
        </div>

        <!-- Add Knowledge Base Document Modal -->
        <Dialog v-model:visible="showModal" modal :header="modalMode === 'create' ? 'ADD KNOWLEDGE DOCUMENT' : 'VIEW DOCUMENT'" :style="{ width: '800px' }">
            <!-- Tabs (only show when creating a new document) -->
            <div v-if="modalMode === 'create'" class="flex border-b border-slate-100 dark:border-slate-800 mb-6">
                <button
                    @click="activeTab = 'text'"
                    :class="['px-4 py-2.5 text-xs font-black uppercase tracking-widest border-b-2 transition-all', activeTab === 'text' ? 'border-indigo-600 text-indigo-600' : 'border-transparent text-slate-400 hover:text-slate-600']"
                >
                    Manual Text
                </button>
                <button
                    @click="activeTab = 'file'"
                    :class="['px-4 py-2.5 text-xs font-black uppercase tracking-widest border-b-2 transition-all', activeTab === 'file' ? 'border-indigo-600 text-indigo-600' : 'border-transparent text-slate-400 hover:text-slate-600']"
                >
                    File Upload (PDF / DOCX / TXT)
                </button>
            </div>

            <!-- Content Area -->
            <div v-if="activeTab === 'text' || modalMode === 'view'">
                <div class="grid grid-cols-2 gap-4 py-4 text-sm font-sans">
                    <div class="flex flex-col gap-2 col-span-2">
                        <label class="text-xs font-semibold uppercase text-gray-500 dark:text-gray-400">Document Title</label>
                        <BaseInput v-model="form.title" fluid autofocus placeholder="e.g., Late Invoice Policy or M30 Concrete Recipe" />
                        <small v-if="form.errors.title" class="text-red-500">{{ form.errors.title }}</small>
                    </div>

                    <div class="flex flex-col gap-2">
                        <label class="text-xs font-semibold uppercase text-gray-500 dark:text-gray-400">Source Category</label>
                        <BaseSelect v-model="form.source_type" :options="typeOptions" optionLabel="label" optionValue="value" placeholder="Select Type" fluid />
                        <small v-if="form.errors.source_type" class="text-red-500">{{ form.errors.source_type }}</small>
                    </div>

                    <div class="flex flex-col gap-2">
                        <label class="text-xs font-semibold uppercase text-gray-500 dark:text-gray-400">Reference Source ID (Optional)</label>
                        <BaseInput v-model="form.source_id" placeholder="e.g. policy-102 or doc-05" fluid />
                        <small v-if="form.errors.source_id" class="text-red-500">{{ form.errors.source_id }}</small>
                    </div>

                    <div class="flex flex-col gap-2 col-span-2">
                        <label class="text-xs font-semibold uppercase text-gray-500 dark:text-gray-400">Document Content (Text Chunk)</label>
                        <Textarea v-model="form.content" rows="12" class="w-full text-xs font-sans rounded-lg border border-slate-200 dark:border-gray-700 p-3 bg-white dark:bg-gray-900 focus:border-indigo-500" placeholder="Paste or type the full raw text content of the policy, procedure, catalog or FAQ here..." />
                        <small v-if="form.errors.content" class="text-red-500">{{ form.errors.content }}</small>
                    </div>
                </div>
            </div>

            <div v-else-if="activeTab === 'file'">
                <div class="py-4">
                    <KnowledgeBaseManager @uploaded="onFileUploaded" @error="onUploadError" />
                </div>
            </div>
            
            <template #footer v-if="activeTab === 'text' || modalMode === 'view'">
                <div class="flex gap-2 justify-end mt-4">
                    <Button label="Cancel" text severity="secondary" @click="closeModal" />
                    <Button label="Index Document" icon="pi pi-check" :loading="form.processing" @click="submitForm" />
                </div>
            </template>
        </Dialog>
    </AppLayout>
</template>
